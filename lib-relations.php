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

function HUB_savePillar($pillarId, $sourceType, $sourceId, $titleOverride = '', $isEnabled = 1, $ownerId = 0)
{
    global $_TABLES, $_USER;

    $pillarId = (int) $pillarId;
    $sourceType = HUB_normalizeObjectType($sourceType);
    $sourceId = HUB_normalizeObjectId($sourceId);
    $titleOverride = trim((string) $titleOverride);
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
    $titleSql = DB_escapeString(substr($titleOverride, 0, 255));

    if ($pillarId > 0) {
        DB_query(
            "UPDATE {$_TABLES['hub_pillars']} SET "
            . "source_type = '" . $typeSql . "', "
            . "source_id = '" . $idSql . "', "
            . "title_override = '" . $titleSql . "', "
            . "is_enabled = " . $isEnabled . ", "
            . "modified = " . $now . " "
            . "WHERE id = " . $pillarId,
            1
        );

        return DB_error() ? false : $pillarId;
    }

    DB_query(
        "INSERT INTO {$_TABLES['hub_pillars']} "
        . "(source_type, source_id, title_override, is_enabled, created, modified, owner_id) VALUES ("
        . "'" . $typeSql . "', '" . $idSql . "', '" . $titleSql . "', "
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

function HUB_saveRelation($relationId, $pillarId, $itemType, $itemId, $position = 0, $isEnabled = 1, $ownerId = 0)
{
    global $_TABLES, $_USER;

    $relationId = (int) $relationId;
    $pillarId = (int) $pillarId;
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);
    $position = max(0, min(65535, (int) $position));
    $isEnabled = $isEnabled ? 1 : 0;

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

    if ($relationId > 0) {
        DB_query(
            "UPDATE {$_TABLES['hub_relations']} SET "
            . "pillar_id = " . $pillarId . ", "
            . "item_type = '" . $typeSql . "', "
            . "item_id = '" . $idSql . "', "
            . "position = " . $position . ", "
            . "is_enabled = " . $isEnabled . ", "
            . "modified = " . $now . " "
            . "WHERE id = " . $relationId,
            1
        );

        return DB_error() ? false : $relationId;
    }

    DB_query(
        "INSERT INTO {$_TABLES['hub_relations']} "
        . "(pillar_id, item_type, item_id, position, is_enabled, created, modified, owner_id) VALUES ("
        . $pillarId . ", '" . $typeSql . "', '" . $idSql . "', " . $position . ", "
        . $isEnabled . ", " . $now . ", " . $now . ", " . (int) $ownerId . ")",
        1
    );

    if (DB_error()) {
        return false;
    }

    return (int) DB_insertId();
}

function HUB_deleteRelation($relationId)
{
    global $_TABLES;

    $relationId = (int) $relationId;
    if ($relationId < 1) {
        return false;
    }

    DB_query("DELETE FROM {$_TABLES['hub_relations']} WHERE id = " . $relationId, 1);

    return !DB_error();
}

function HUB_relationObjectTypes()
{
    $types = array('article', 'staticpages');

    if (function_exists('HUB_auditCachedRows')) {
        $meta = array();
        $rows = HUB_auditCachedRows(false, 600, $meta);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['object_types']) && is_array($row['object_types'])) {
                foreach ($row['object_types'] as $type) {
                    $type = HUB_normalizeObjectType($type);
                    if ($type !== '') {
                        $types[] = $type;
                    }
                }
            }

            $caps = isset($row['caps']) && is_array($row['caps']) ? $row['caps'] : array();
            if (!empty($caps['item_info']) && !empty($row['plugin'])) {
                $pluginType = HUB_normalizeObjectType($row['plugin']);
                if ($pluginType !== '') {
                    $types[] = $pluginType;
                }
            }
        }
    }

    $types = array_values(array_unique($types));
    sort($types, SORT_STRING);

    return $types;
}

function HUB_resolveObject($type, $id)
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

    $info = PLG_getItemInfo($type, $id, 'id,title,url');
    if (is_array($info) && count($info) >= 3 && (string) $info[0] !== '') {
        $resolved['exists'] = true;
        $resolved['status'] = 'resolved';
        $resolved['id'] = (string) $info[0];
        $resolved['title'] = (string) $info[1] !== '' ? (string) $info[1] : $id;
        $resolved['url'] = (string) $info[2];
        return $resolved;
    }

    $resolved['diagnostic'] = $resolved['provider_available']
        ? 'The provider is available but did not resolve this object identity.'
        : 'No loaded Item Info provider resolved this object type. The plugin may be disabled, unavailable, or may not expose Item Info.';

    return $resolved;
}
