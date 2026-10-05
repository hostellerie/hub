<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_normalizeObjectType($type)
{
    $type = strtolower(trim((string) $type));
    $type = preg_replace('/[^a-z0-9_-]+/', '', $type);

    return substr($type, 0, 64);
}

function HUB_normalizeObjectId($id)
{
    $id = trim((string) $id);

    return substr($id, 0, 128);
}

function HUB_getPillar($pillarId)
{
    global $_TABLES;

    $pillarId = (int) $pillarId;
    if ($pillarId < 1) {
        return false;
    }

    $result = DB_query(
        "SELECT * FROM {$_TABLES['hub_pillars']} WHERE id = " . $pillarId . " LIMIT 1",
        1
    );
    if ($result === false || DB_numRows($result) < 1) {
        return false;
    }

    return DB_fetchArray($result);
}

/**
 * Structural roles owned by Hub relationship edges.
 *
 * These describe graph position only. Editorial function (guide, tutorial,
 * reference, etc.) is intentionally a separate future concern.
 *
 * @return array
 */
function HUB_relationRoles()
{
    return array(
        'related' => 'Related',
        'sub-pillar' => 'Sub-pillar',
        'satellite' => 'Satellite',
        'support' => 'Support',
    );
}

/**
 * Normalize one Hub structural relationship role.
 *
 * @param string $role
 * @return string
 */
function HUB_normalizeRelationRole($role)
{
    $role = strtolower(trim((string) $role));
    $roles = HUB_relationRoles();

    return isset($roles[$role]) ? $role : 'related';
}

function HUB_findPillar($sourceType, $sourceId)
{
    global $_TABLES;

    $sourceType = HUB_normalizeObjectType($sourceType);
    $sourceId = HUB_normalizeObjectId($sourceId);
    if ($sourceType === '' || $sourceId === '') {
        return false;
    }

    $result = DB_query(
        "SELECT * FROM {$_TABLES['hub_pillars']} "
        . "WHERE source_type = '" . DB_escapeString($sourceType) . "' "
        . "AND source_id = '" . DB_escapeString($sourceId) . "' LIMIT 1",
        1
    );
    if ($result === false || DB_numRows($result) < 1) {
        return false;
    }

    return DB_fetchArray($result);
}

function HUB_getPillars($includeDisabled = true)
{
    global $_TABLES;

    $sql = "SELECT * FROM {$_TABLES['hub_pillars']}";
    if (!$includeDisabled) {
        $sql .= " WHERE is_enabled = 1";
    }
    $sql .= " ORDER BY modified DESC, id DESC";

    $rows = array();
    $result = DB_query($sql, 1);
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function HUB_savePillar($pillarId, $sourceType, $sourceId, $isEnabled = 1, $ownerId = 0)
{
    global $_TABLES, $_USER;

    $pillarId = (int) $pillarId;
    $sourceType = HUB_normalizeObjectType($sourceType);
    $sourceId = HUB_normalizeObjectId($sourceId);
    $isEnabled = $isEnabled ? 1 : 0;

    if ($sourceType === '' || $sourceId === '') {
        return false;
    }

    if ($ownerId < 1) {
        $ownerId = !empty($_USER['uid']) ? (int) $_USER['uid'] : 2;
    }

    $now = time();
    $typeSql = DB_escapeString($sourceType);
    $idSql = DB_escapeString($sourceId);

    if ($pillarId > 0) {
        DB_query(
            "UPDATE {$_TABLES['hub_pillars']} SET "
            . "source_type = '" . $typeSql . "', "
            . "source_id = '" . $idSql . "', "
            . "is_enabled = " . $isEnabled . ", "
            . "modified = " . $now . " "
            . "WHERE id = " . $pillarId,
            1
        );

        return DB_error() ? false : $pillarId;
    }

    DB_query(
        "INSERT INTO {$_TABLES['hub_pillars']} "
        . "(source_type, source_id, is_enabled, created, modified, owner_id) VALUES ("
        . "'" . $typeSql . "', '" . $idSql . "', "
        . $isEnabled . ", " . $now . ", " . $now . ", " . (int) $ownerId . ")",
        1
    );

    if (DB_error()) {
        return false;
    }

    return (int) DB_insertId();
}

function HUB_deletePillar($pillarId)
{
    global $_TABLES;

    $pillarId = (int) $pillarId;
    if ($pillarId < 1) {
        return false;
    }

    DB_query("DELETE FROM {$_TABLES['hub_relations']} WHERE pillar_id = " . $pillarId, 1);
    if (DB_error()) {
        return false;
    }

    DB_query("DELETE FROM {$_TABLES['hub_pillars']} WHERE id = " . $pillarId, 1);

    return !DB_error();
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $_TABLES;

    $pillarId = (int) $pillarId;
    $rows = array();
    if ($pillarId < 1) {
        return $rows;
    }

    $sql = "SELECT * FROM {$_TABLES['hub_relations']} WHERE pillar_id = " . $pillarId;
    if (!$includeDisabled) {
        $sql .= " AND is_enabled = 1";
    }
    $sql .= " ORDER BY position ASC, id ASC";

    $result = DB_query($sql, 1);
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function HUB_invalidateRelationshipCaches($pillarId, $itemType = '', $itemId = '')
{
    $pillarId = (int) $pillarId;
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);

    if (function_exists('CACHE_remove_instance')) {
        if ($itemType === 'article' && $itemId !== '') {
            CACHE_remove_instance('article__' . $itemId . '_');
        }

        $pillar = HUB_getPillar($pillarId);
        if ($pillar && isset($pillar['source_type'], $pillar['source_id'])
            && (string) $pillar['source_type'] === 'staticpages'
            && (string) $pillar['source_id'] !== ''
        ) {
            CACHE_remove_instance('staticpage__' . (string) $pillar['source_id'] . '__');
        }
    }
}

function HUB_saveRelation($relationId, $pillarId, $itemType, $itemId, $position = 0, $isEnabled = 1, $ownerId = 0, $relationRole = 'related')
{
    global $_TABLES, $_USER;

    $relationId = (int) $relationId;
    $pillarId = (int) $pillarId;
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);
    $position = max(0, min(65535, (int) $position));
    $isEnabled = $isEnabled ? 1 : 0;
    $relationRole = HUB_normalizeRelationRole($relationRole);

    $oldRelation = null;
    if ($relationId > 0) {
        $oldResult = DB_query(
            "SELECT pillar_id, item_type, item_id FROM {$_TABLES['hub_relations']} WHERE id = " . $relationId,
            1
        );
        if ($oldResult !== false) {
            $oldRelation = DB_fetchArray($oldResult);
        }
    }

    $pillar = HUB_getPillar($pillarId);
    if ($pillarId < 1 || $itemType === '' || $itemId === '' || !$pillar) {
        return false;
    }
    if ($itemType === (string) $pillar['source_type'] && $itemId === (string) $pillar['source_id']) {
        return false;
    }

    if ($ownerId < 1) {
        $ownerId = !empty($_USER['uid']) ? (int) $_USER['uid'] : 2;
    }

    $now = time();
    $typeSql = DB_escapeString($itemType);
    $idSql = DB_escapeString($itemId);
    $roleSql = DB_escapeString($relationRole);

    if ($relationId > 0) {
        DB_query(
            "UPDATE {$_TABLES['hub_relations']} SET "
            . "pillar_id = " . $pillarId . ", "
            . "item_type = '" . $typeSql . "', "
            . "item_id = '" . $idSql . "', "
            . "relation_role = '" . $roleSql . "', "
            . "position = " . $position . ", "
            . "is_enabled = " . $isEnabled . ", "
            . "modified = " . $now . " "
            . "WHERE id = " . $relationId,
            1
        );

        if (DB_error()) {
            return false;
        }

        if (is_array($oldRelation)) {
            HUB_invalidateRelationshipCaches(
                isset($oldRelation['pillar_id']) ? (int) $oldRelation['pillar_id'] : 0,
                isset($oldRelation['item_type']) ? (string) $oldRelation['item_type'] : '',
                isset($oldRelation['item_id']) ? (string) $oldRelation['item_id'] : ''
            );
        }
        HUB_invalidateRelationshipCaches($pillarId, $itemType, $itemId);

        return $relationId;
    }

    DB_query(
        "INSERT INTO {$_TABLES['hub_relations']} "
        . "(pillar_id, item_type, item_id, relation_role, position, is_enabled, created, modified, owner_id) VALUES ("
        . $pillarId . ", '" . $typeSql . "', '" . $idSql . "', '" . $roleSql . "', " . $position . ", "
        . $isEnabled . ", " . $now . ", " . $now . ", " . (int) $ownerId . ")",
        1
    );

    if (DB_error()) {
        return false;
    }

    HUB_invalidateRelationshipCaches($pillarId, $itemType, $itemId);

    return (int) DB_insertId();
}

function HUB_deleteRelation($relationId)
{
    global $_TABLES;

    $relationId = (int) $relationId;
    if ($relationId < 1) {
        return false;
    }

    $relation = null;
    $result = DB_query(
        "SELECT pillar_id, item_type, item_id FROM {$_TABLES['hub_relations']} WHERE id = " . $relationId,
        1
    );
    if ($result !== false) {
        $relation = DB_fetchArray($result);
    }

    DB_query("DELETE FROM {$_TABLES['hub_relations']} WHERE id = " . $relationId, 1);
    if (DB_error()) {
        return false;
    }

    if (is_array($relation)) {
        HUB_invalidateRelationshipCaches(
            isset($relation['pillar_id']) ? (int) $relation['pillar_id'] : 0,
            isset($relation['item_type']) ? (string) $relation['item_type'] : '',
            isset($relation['item_id']) ? (string) $relation['item_id'] : ''
        );
    }

    return true;
}


/**
 * Migrate Hub-owned stable references when a provider changes an object ID.
 *
 * The provider remains authoritative for its content. Hub updates only its own
 * pillar/relation identities. A complete preflight is performed first so an
 * existing target identity never causes a partial logical migration.
 *
 * @param string $itemType
 * @param string $oldId
 * @param string $newId
 * @return array
 */
function HUB_migrateObjectIdentity($itemType, $oldId, $newId)
{
    global $_TABLES;

    $itemType = HUB_normalizeObjectType($itemType);
    $oldId = HUB_normalizeObjectId($oldId);
    $newId = HUB_normalizeObjectId($newId);

    $result = array(
        'changed' => false,
        'pillar_updated' => false,
        'relations_updated' => 0,
        'collisions' => array(),
        'error' => '',
    );

    if ($itemType === '' || $oldId === '' || $newId === '' || $oldId === $newId) {
        return $result;
    }

    $typeSql = DB_escapeString($itemType);
    $oldSql = DB_escapeString($oldId);
    $newSql = DB_escapeString($newId);

    $oldPillar = HUB_findPillar($itemType, $oldId);
    if (is_array($oldPillar)) {
        $newPillar = HUB_findPillar($itemType, $newId);
        if (is_array($newPillar) && (int) $newPillar['id'] !== (int) $oldPillar['id']) {
            $result['collisions'][] = array(
                'kind' => 'pillar',
                'pillar_id' => (int) $oldPillar['id'],
                'target_pillar_id' => (int) $newPillar['id'],
            );
        }
    }

    $relations = array();
    $relationResult = DB_query(
        "SELECT id, pillar_id FROM {$_TABLES['hub_relations']} "
        . "WHERE item_type = '" . $typeSql . "' "
        . "AND item_id = '" . $oldSql . "' "
        . "ORDER BY id ASC",
        1
    );

    if ($relationResult === false) {
        $result['error'] = 'Unable to inspect existing Hub relations for identity migration.';
        return $result;
    }

    while ($relation = DB_fetchArray($relationResult)) {
        if (!is_array($relation)) {
            continue;
        }

        $relationId = isset($relation['id']) ? (int) $relation['id'] : 0;
        $pillarId = isset($relation['pillar_id']) ? (int) $relation['pillar_id'] : 0;
        if ($relationId < 1 || $pillarId < 1) {
            continue;
        }

        $relations[] = array(
            'id' => $relationId,
            'pillar_id' => $pillarId,
        );

        $collisionResult = DB_query(
            "SELECT id FROM {$_TABLES['hub_relations']} "
            . "WHERE pillar_id = " . $pillarId . " "
            . "AND item_type = '" . $typeSql . "' "
            . "AND item_id = '" . $newSql . "' "
            . "AND id <> " . $relationId . " LIMIT 1",
            1
        );

        if ($collisionResult === false) {
            $result['error'] = 'Unable to validate Hub relation identity migration.';
            return $result;
        }

        if (DB_numRows($collisionResult) > 0) {
            $collision = DB_fetchArray($collisionResult);
            $result['collisions'][] = array(
                'kind' => 'relation',
                'relation_id' => $relationId,
                'pillar_id' => $pillarId,
                'target_relation_id' => is_array($collision) && isset($collision['id'])
                    ? (int) $collision['id']
                    : 0,
            );
        }
    }

    // Do not partially rewrite Hub's graph when the target identity already
    // exists. The old references remain visible through integrity diagnostics.
    if (!empty($result['collisions'])) {
        return $result;
    }

    $now = time();

    if (is_array($oldPillar)) {
        DB_query(
            "UPDATE {$_TABLES['hub_pillars']} SET "
            . "source_id = '" . $newSql . "', modified = " . $now . " "
            . "WHERE id = " . (int) $oldPillar['id'],
            1
        );
        if (DB_error()) {
            $result['error'] = 'Unable to migrate Hub pillar identity.';
            return $result;
        }
        $result['pillar_updated'] = true;
        $result['changed'] = true;
    }

    if (!empty($relations)) {
        DB_query(
            "UPDATE {$_TABLES['hub_relations']} SET "
            . "item_id = '" . $newSql . "', modified = " . $now . " "
            . "WHERE item_type = '" . $typeSql . "' "
            . "AND item_id = '" . $oldSql . "'",
            1
        );
        if (DB_error()) {
            $result['error'] = 'Unable to migrate Hub relation identities.';
            return $result;
        }

        $result['relations_updated'] = count($relations);
        $result['changed'] = true;
    }

    return $result;
}

function HUB_relationObjectTypes()
{
    global $_PLUGINS;

    $types = array(
        'article' => true,
        'staticpages' => true,
    );

    if (is_array($_PLUGINS)) {
        foreach ($_PLUGINS as $plugin) {
            $plugin = HUB_normalizeObjectType($plugin);
            if ($plugin === '') {
                continue;
            }

            if (function_exists('plugin_getiteminfo_' . $plugin)) {
                $types[$plugin] = true;
            }
        }
    }

    $types = array_keys($types);
    sort($types, SORT_STRING);

    return $types;
}

function HUB_relationCollectionSupported($type)
{
    $type = HUB_normalizeObjectType($type);
    if ($type === '' || !function_exists('PLG_getItemInfo')) {
        return false;
    }

    if (function_exists('HUB_contentContractEvidence')) {
        $evidence = HUB_contentContractEvidence($type);
        if (!empty($evidence['collection_declared']) || !empty($evidence['collection_source'])) {
            return true;
        }
    }

    return false;
}

function HUB_relationNormalizeInfoRecord($record, $fallbackId = '')
{
    $normalized = array(
        'id' => (string) $fallbackId,
        'title' => (string) $fallbackId,
        'url' => '',
    );

    if (!is_array($record)) {
        return $normalized;
    }

    if (isset($record['id']) || isset($record['title']) || isset($record['url'])) {
        if (isset($record['id']) && (string) $record['id'] !== '') {
            $normalized['id'] = (string) $record['id'];
        }
        if (isset($record['title']) && (string) $record['title'] !== '') {
            $normalized['title'] = (string) $record['title'];
        }
        if (isset($record['url'])) {
            $normalized['url'] = (string) $record['url'];
        }
        if ($normalized['title'] === '' && $normalized['id'] !== '') {
            $normalized['title'] = $normalized['id'];
        }

        return $normalized;
    }

    if (isset($record[0]) && (string) $record[0] !== '') {
        $normalized['id'] = (string) $record[0];
    }
    if (isset($record[1]) && (string) $record[1] !== '') {
        $normalized['title'] = (string) $record[1];
    }
    if (isset($record[2])) {
        $normalized['url'] = (string) $record[2];
    }
    if ($normalized['title'] === '' && $normalized['id'] !== '') {
        $normalized['title'] = $normalized['id'];
    }

    return $normalized;
}

function HUB_relationCoreArticleOptions($limit = 100)
{
    global $_TABLES;

    $limit = max(1, min(200, (int) $limit));
    if (empty($_TABLES['stories'])) {
        return array();
    }

    $sql = "SELECT s.sid, s.title FROM {$_TABLES['stories']} AS s "
         . "WHERE s.draft_flag = 0 AND s.date <= NOW() ";

    if (function_exists('COM_getPermSQL')) {
        $sql .= COM_getPermSQL('AND', 0, 2, 's');
    }
    if (function_exists('COM_getLangSQL')) {
        $sql .= COM_getLangSQL('sid', 'AND', 's');
    }

    $sql .= " ORDER BY s.date DESC, s.title ASC LIMIT " . $limit;

    $rows = array();
    $result = DB_query($sql, 1);
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (!is_array($row) || empty($row['sid'])) {
            continue;
        }

        $sid = (string) $row['sid'];
        $title = isset($row['title']) && (string) $row['title'] !== ''
            ? (string) $row['title']
            : $sid;

        $rows[] = array(
            'id' => $sid,
            'title' => $title,
            'url' => '',
        );
    }

    return $rows;
}

function HUB_relationObjectOptions($type, $limit = 100)
{
    $type = HUB_normalizeObjectType($type);
    $limit = max(1, min(200, (int) $limit));

    if ($type === 'article') {
        $articles = HUB_relationCoreArticleOptions($limit);

        return array(
            'supported' => true,
            'items' => $articles,
            'message' => empty($articles)
                ? 'No published article is currently selectable.'
                : '',
        );
    }

    if (!HUB_relationCollectionSupported($type)) {
        return array(
            'supported' => false,
            'items' => array(),
            'message' => 'This provider does not expose the shared Item Info collection contract. Enter the item ID manually.',
        );
    }

    try {
        $items = PLG_getItemInfo(
            $type,
            '*',
            'id,title,url',
            0,
            array('limit' => $limit, 'order' => 'modified-desc')
        );
    } catch (Exception $e) {
        $items = array();
    }

    if (!is_array($items)) {
        $items = array();
    }

    $normalized = array();
    foreach ($items as $record) {
        $item = HUB_relationNormalizeInfoRecord($record);
        $item['id'] = HUB_normalizeObjectId($item['id']);
        if ($item['id'] === '') {
            continue;
        }
        if ($item['title'] === '') {
            $item['title'] = $item['id'];
        }
        $normalized[$item['id']] = $item;
    }

    uasort($normalized, function ($left, $right) {
        return strcasecmp((string) $left['title'], (string) $right['title']);
    });

    return array(
        'supported' => true,
        'items' => array_values($normalized),
        'message' => empty($normalized)
            ? 'The provider supports collections but returned no selectable items.'
            : '',
    );
}

function HUB_resolveObject($type, $id, $uid = 0)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);

    $resolved = array(
        'type' => $type,
        'id' => $id,
        'title' => $id,
        'url' => '',
        'exists' => false,
        'status' => 'unresolved',
        'diagnostic' => '',
        'provider_available' => false,
    );

    if ($type === '' || $id === '') {
        $resolved['diagnostic'] = 'Invalid or empty type + id identity.';
        return $resolved;
    }

    if (!function_exists('PLG_getItemInfo')) {
        $resolved['diagnostic'] = 'Geeklog Item Info dispatcher is unavailable.';
        return $resolved;
    }

    $callback = 'plugin_getiteminfo_' . $type;
    $resolved['provider_available'] = function_exists($callback);

    $uid = max(0, (int) $uid);
    $info = PLG_getItemInfo($type, $id, 'id,title,url', $uid);
    if (is_array($info) && !empty($info)) {
        $normalized = HUB_relationNormalizeInfoRecord($info, $id);
        if ($normalized['id'] !== '') {
            $resolved['exists'] = true;
            $resolved['status'] = 'resolved';
            $resolved['id'] = $normalized['id'];
            $resolved['title'] = $normalized['title'] !== '' ? $normalized['title'] : $id;
            $resolved['url'] = $normalized['url'];
        }
    }

    // Some legacy providers only return reliable values when Item Info
    // properties are requested separately. Keep the combined request as the
    // preferred contract, then fill missing public fields through the same
    // permission-aware Geeklog API.
    if ($resolved['provider_available']) {
        if ($resolved['title'] === $id || $resolved['title'] === '') {
            $title = PLG_getItemInfo($type, $id, 'title', $uid);
            if (is_string($title) && $title !== '') {
                $resolved['title'] = $title;
                $resolved['exists'] = true;
                $resolved['status'] = 'resolved';
            }
        }

        if ($resolved['url'] === '') {
            $url = PLG_getItemInfo($type, $id, 'url', $uid);
            if (is_string($url) && $url !== '') {
                $resolved['url'] = $url;
                $resolved['exists'] = true;
                $resolved['status'] = 'resolved';
            }
        }
    }

    if ($resolved['exists']) {
        return $resolved;
    }

    $resolved['diagnostic'] = $resolved['provider_available']
        ? 'The provider is available but did not resolve this object identity for the current user.'
        : 'No loaded Item Info provider resolved this object type. The plugin may be disabled, unavailable, or may not expose Item Info.';

    return $resolved;
}


function HUB_findPillarsForItem($itemType, $itemId, $includeDisabled = false)
{
    global $_TABLES;

    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);
    if ($itemType === '' || $itemId === '') {
        return array();
    }

    $sql = "SELECT p.* FROM {$_TABLES['hub_relations']} AS r "
         . "INNER JOIN {$_TABLES['hub_pillars']} AS p ON p.id = r.pillar_id "
         . "WHERE r.item_type = '" . DB_escapeString($itemType) . "' "
         . "AND r.item_id = '" . DB_escapeString($itemId) . "'";

    if (!$includeDisabled) {
        $sql .= " AND r.is_enabled = 1 AND p.is_enabled = 1";
    }

    $sql .= " ORDER BY p.modified DESC, p.id DESC";

    $rows = array();
    $result = DB_query($sql, 1);
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function HUB_publicText($key)
{
    global $_CONF;

    $language = isset($_CONF['language']) ? strtolower((string) $_CONF['language']) : 'english';

    $strings = array(
        'english' => array(
            'related_content' => 'Related content',
            'part_of' => 'Part of',
        ),
        'french' => array(
            'related_content' => 'Contenus liés',
            'part_of' => 'Dans ce dossier',
        ),
        'german' => array(
            'related_content' => 'Verwandte Inhalte',
            'part_of' => 'Teil von',
        ),
        'italian' => array(
            'related_content' => 'Contenuti correlati',
            'part_of' => 'Fa parte di',
        ),
        'spanish' => array(
            'related_content' => 'Contenido relacionado',
            'part_of' => 'Forma parte de',
        ),
    );

    $family = 'english';
    foreach (array_keys($strings) as $candidate) {
        if (strpos($language, $candidate) === 0) {
            $family = $candidate;
            break;
        }
    }

    return isset($strings[$family][$key]) ? $strings[$family][$key] : $key;
}

function HUB_pillarRenderDiagnostics($sourceType, $sourceId, $uid = 0)
{
    $diagnostics = array(
        'pillar_found' => false,
        'pillar_enabled' => false,
        'relation_count' => 0,
        'renderable_count' => 0,
        'relations' => array(),
    );

    $pillar = HUB_findPillar($sourceType, $sourceId);
    if (!$pillar) {
        return $diagnostics;
    }

    $diagnostics['pillar_found'] = true;
    $diagnostics['pillar_enabled'] = !empty($pillar['is_enabled']);
    if (!$diagnostics['pillar_enabled']) {
        return $diagnostics;
    }

    $relations = HUB_getRelations($pillar['id'], false);
    $diagnostics['relation_count'] = count($relations);

    foreach ($relations as $relation) {
        $resolved = HUB_resolveObject($relation['item_type'], $relation['item_id'], $uid);
        $renderable = !empty($resolved['exists']) && !empty($resolved['url']);
        if ($renderable) {
            $diagnostics['renderable_count']++;
        }

        $diagnostics['relations'][] = array(
            'type' => (string) $relation['item_type'],
            'id' => (string) $relation['item_id'],
            'relation_role' => isset($relation['relation_role'])
                ? HUB_normalizeRelationRole($relation['relation_role'])
                : 'related',
            'renderable' => $renderable,
            'title' => (string) $resolved['title'],
            'url' => (string) $resolved['url'],
            'diagnostic' => $renderable ? '' : (string) $resolved['diagnostic'],
        );
    }

    return $diagnostics;
}

function HUB_renderPillarRelations($sourceType, $sourceId)
{
    $pillar = HUB_findPillar($sourceType, $sourceId);
    if (!$pillar || empty($pillar['is_enabled'])) {
        return '';
    }

    $links = array();
    foreach (HUB_getRelations($pillar['id'], false) as $relation) {
        $resolved = HUB_resolveObject($relation['item_type'], $relation['item_id']);
        if (empty($resolved['exists']) || empty($resolved['url'])) {
            continue;
        }

        $links[] = '<li class="hub-related-item hub-related-type-'
            . htmlspecialchars($relation['item_type'], ENT_QUOTES, 'UTF-8')
            . '"><a href="'
            . htmlspecialchars($resolved['url'], ENT_QUOTES, 'UTF-8')
            . '">'
            . htmlspecialchars($resolved['title'], ENT_QUOTES, 'UTF-8')
            . '</a></li>';
    }

    if (empty($links)) {
        return '';
    }

    return '<section class="hub-context-block hub-related-content" aria-label="'
        . htmlspecialchars(HUB_publicText('related_content'), ENT_QUOTES, 'UTF-8')
        . '"><div class="hub-context-title">'
        . htmlspecialchars(HUB_publicText('related_content'), ENT_QUOTES, 'UTF-8')
        . '</div><ul class="hub-context-list">'
        . implode('', $links)
        . '</ul></section>';
}

function HUB_itemDisplayBacklinkTypes()
{
    return array(
        'forum',
        'documents',
        'videos',
        'maps',
        'mediagallery',
        'polls',
    );
}

function HUB_backlinkIntegrationStatus($itemType)
{
    $itemType = HUB_normalizeObjectType($itemType);

    if ($itemType === 'story') {
        $itemType = 'article';
    }

    if ($itemType === 'article') {
        return array(
            'supported' => true,
            'mode' => 'core-template-fallback',
            'label' => 'Core article fallback',
            'detail' => 'Full article pages expose PLG_templateSetVars() and Hub appends one server-rendered backlink inside the article body.',
        );
    }

    if ($itemType === 'staticpages') {
        return array(
            'supported' => true,
            'mode' => 'staticpage-template-hook',
            'label' => 'Static Page template hook',
            'detail' => 'Hub integrates directly with the Static Pages template hook and does not require PLG_itemDisplay() for its current public pillar rendering.',
        );
    }

    if (in_array($itemType, HUB_itemDisplayBacklinkTypes(), true)) {
        return array(
            'supported' => true,
            'mode' => 'generic-itemdisplay-provider',
            'label' => 'Generic PLG_itemDisplay provider hook',
            'detail' => 'Hub supplies the pillar backlink through plugin_itemdisplay_hub() when the provider calls PLG_itemDisplay($id, $type) on its full public item view.',
        );
    }

    return array(
        'supported' => false,
        'mode' => 'provider-hook-unconfirmed',
        'label' => 'Provider hook not confirmed',
        'detail' => 'Hub can render the forward link on the pillar, but no confirmed generic public placement hook is available for this provider. Hub does not use provider-specific DOM or private-table fallbacks.',
    );
}

function HUB_renderItemPillarBacklinks($itemType, $itemId)
{
    $links = array();

    foreach (HUB_findPillarsForItem($itemType, $itemId, false) as $pillar) {
        $resolved = HUB_resolveObject($pillar['source_type'], $pillar['source_id']);
        if (empty($resolved['exists']) || empty($resolved['url'])) {
            continue;
        }

        // Public backlink anchors should describe the actual target page.
        // Keep title_override for Hub administration/pillar presentation, but
        // do not let it silently replace the resolved source title in links.
        $title = (string) $resolved['title'];
        if ($title === '') {
            $title = (string) $pillar['source_id'];
        }

        $links[] = '<li><a href="'
            . htmlspecialchars($resolved['url'], ENT_QUOTES, 'UTF-8')
            . '">'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</a></li>';
    }

    if (empty($links)) {
        return '';
    }

    return '<aside class="hub-context-block hub-pillar-backlinks" aria-label="'
        . htmlspecialchars(HUB_publicText('part_of'), ENT_QUOTES, 'UTF-8')
        . '"><div class="hub-context-title">'
        . htmlspecialchars(HUB_publicText('part_of'), ENT_QUOTES, 'UTF-8')
        . '</div><ul class="hub-context-list">'
        . implode('', $links)
        . '</ul></aside>';
}
