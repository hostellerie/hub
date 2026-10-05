<?php

if (isset($_SERVER['PHP_SELF'])
    && strpos(strtolower((string) $_SERVER['PHP_SELF']), 'services.inc.php') !== false
) {
    die('This file can not be used on its own.');
}

function HUB_SERVICE_authorized()
{
    return function_exists('SEC_hasRights') && SEC_hasRights('hub.admin');
}

function HUB_SERVICE_denied(&$output, &$svc_msg)
{
    $output = array();
    $svc_msg = array('Hub service access denied.');

    return defined('PLG_RET_PERMISSION_DENIED') ? PLG_RET_PERMISSION_DENIED : -2;
}

function HUB_SERVICE_ok($value, &$output, &$svc_msg)
{
    $output = $value;
    $svc_msg = array();

    return defined('PLG_RET_OK') ? PLG_RET_OK : 0;
}

/**
 * hub.affected.read
 *
 * Required arguments:
 * - type: stable Geeklog/provider object type
 * - id: stable object id
 *
 * Optional arguments:
 * - include_disabled: include disabled pillars/relations in the result
 */
function service_affected_read_hub($args, &$output, &$svc_msg)
{
    if (!HUB_SERVICE_authorized()) {
        return HUB_SERVICE_denied($output, $svc_msg);
    }

    $args = is_array($args) ? $args : array();
    $type = isset($args['type']) ? HUB_normalizeObjectType($args['type']) : '';
    $id = isset($args['id']) ? HUB_normalizeObjectId($args['id']) : '';
    $includeDisabled = !empty($args['include_disabled']);

    if ($type === '' || $id === '') {
        $output = array();
        $svc_msg = array('Hub affected-context service requires type and id.');

        return defined('PLG_RET_ERROR') ? PLG_RET_ERROR : -1;
    }

    $contexts = HUB_getAffectedContexts($type, $id, $includeDisabled);

    return HUB_SERVICE_ok(array(
        'capability' => 'hub.affected.read',
        'object' => array(
            'type' => $type,
            'id' => $id,
        ),
        'count' => count($contexts),
        'contexts' => $contexts,
    ), $output, $svc_msg);
}


/**
 * hub.context.read
 *
 * Required arguments:
 * - type: stable Geeklog/provider object type
 * - id: stable object id
 *
 * Optional arguments:
 * - depth: traversal depth, clamped to 0..16, defaults to 4
 * - include_disabled: include disabled pillars/relations
 */
function service_context_read_hub($args, &$output, &$svc_msg)
{
    if (!HUB_SERVICE_authorized()) {
        return HUB_SERVICE_denied($output, $svc_msg);
    }

    $args = is_array($args) ? $args : array();
    $type = isset($args['type']) ? HUB_normalizeObjectType($args['type']) : '';
    $id = isset($args['id']) ? HUB_normalizeObjectId($args['id']) : '';
    $depth = isset($args['depth']) ? (int) $args['depth'] : 4;
    $includeDisabled = !empty($args['include_disabled']);

    if ($type === '' || $id === '') {
        $output = array();
        $svc_msg = array('Hub context service requires type and id.');

        return defined('PLG_RET_ERROR') ? PLG_RET_ERROR : -1;
    }

    $context = HUB_graphContext($type, $id, $depth, $includeDisabled);

    return HUB_SERVICE_ok(array(
        'capability' => 'hub.context.read',
        'object' => array(
            'type' => $type,
            'id' => $id,
        ),
        'context' => $context,
    ), $output, $svc_msg);
}


/**
 * hub.related.read
 *
 * Return only the immediate approved Hub neighbors of one stable identity.
 */
function service_related_read_hub($args, &$output, &$svc_msg)
{
    if (!HUB_SERVICE_authorized()) {
        return HUB_SERVICE_denied($output, $svc_msg);
    }

    $args = is_array($args) ? $args : array();
    $type = isset($args['type']) ? HUB_normalizeObjectType($args['type']) : '';
    $id = isset($args['id']) ? HUB_normalizeObjectId($args['id']) : '';
    $includeDisabled = !empty($args['include_disabled']);

    if ($type === '' || $id === '') {
        $output = array();
        $svc_msg = array('Hub related service requires type and id.');

        return defined('PLG_RET_ERROR') ? PLG_RET_ERROR : -1;
    }

    $neighbors = HUB_graphNeighbors($type, $id, $includeDisabled);

    return HUB_SERVICE_ok(array(
        'capability' => 'hub.related.read',
        'object' => array('type' => $type, 'id' => $id),
        'parents' => $neighbors['parents'],
        'children' => $neighbors['children'],
        'edges' => $neighbors['edges'],
    ), $output, $svc_msg);
}

/**
 * hub.pillar.read
 *
 * Read one Hub-owned pillar definition and its approved relation identities.
 * Accepts either pillar_id or a stable source type + id.
 */
function service_pillar_read_hub($args, &$output, &$svc_msg)
{
    if (!HUB_SERVICE_authorized()) {
        return HUB_SERVICE_denied($output, $svc_msg);
    }

    $args = is_array($args) ? $args : array();
    $includeDisabled = !empty($args['include_disabled']);
    $pillar = false;

    if (!empty($args['pillar_id'])) {
        $pillar = HUB_getPillar((int) $args['pillar_id']);
    } else {
        $type = isset($args['type']) ? HUB_normalizeObjectType($args['type']) : '';
        $id = isset($args['id']) ? HUB_normalizeObjectId($args['id']) : '';
        if ($type !== '' && $id !== '') {
            $pillar = HUB_findPillar($type, $id);
        }
    }

    if (!is_array($pillar) || (!$includeDisabled && empty($pillar['is_enabled']))) {
        $output = array();
        $svc_msg = array('Hub pillar was not found or is not enabled.');

        return defined('PLG_RET_ERROR') ? PLG_RET_ERROR : -1;
    }

    $relations = HUB_getRelations((int) $pillar['id'], $includeDisabled);
    $items = array();

    foreach ($relations as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $items[] = array(
            'type' => isset($relation['item_type']) ? (string) $relation['item_type'] : '',
            'id' => isset($relation['item_id']) ? (string) $relation['item_id'] : '',
            'position' => isset($relation['position']) ? (int) $relation['position'] : 0,
            'is_enabled' => !empty($relation['is_enabled']),
        );
    }

    return HUB_SERVICE_ok(array(
        'capability' => 'hub.pillar.read',
        'pillar' => array(
            'pillar_id' => (int) $pillar['id'],
            'source_type' => (string) $pillar['source_type'],
            'source_id' => (string) $pillar['source_id'],
            'is_enabled' => !empty($pillar['is_enabled']),
        ),
        'relations' => $items,
    ), $output, $svc_msg);
}
