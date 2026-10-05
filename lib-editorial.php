<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Build a deterministic read-only summary of Hub's approved editorial graph.
 *
 * This is intentionally structural, not diagnostic. Integrity/SEO health,
 * unresolved objects, orphan discovery and inferred recommendations remain
 * separate concerns and are not silently mixed into this surface.
 *
 * @param bool $includeDisabled
 * @return array
 */
function HUB_editorialSummary($includeDisabled = false)
{
    $pillars = HUB_getPillars($includeDisabled);

    $summary = array(
        'schema' => 1,
        'pillars' => 0,
        'relations' => 0,
        'roles' => array(
            'related' => 0,
            'sub-pillar' => 0,
            'satellite' => 0,
            'support' => 0,
        ),
        'providers' => array(),
        'multi_parent_items' => 0,
        'nested_pillars' => 0,
        'pillar_items' => array(),
    );

    $parentsByItem = array();
    $pillarIdentities = array();

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $pillarId = (int) $pillar['id'];
        $sourceType = HUB_normalizeObjectType($pillar['source_type']);
        $sourceId = HUB_normalizeObjectId($pillar['source_id']);
        if ($pillarId < 1 || $sourceType === '' || $sourceId === '') {
            continue;
        }

        $summary['pillars']++;
        $pillarKey = HUB_graphIdentityKey($sourceType, $sourceId);
        if ($pillarKey !== '') {
            $pillarIdentities[$pillarKey] = true;
        }

        $relations = HUB_getRelations($pillarId, $includeDisabled);
        $item = array(
            'pillar_id' => $pillarId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'relations' => 0,
            'roles' => array(
                'related' => 0,
                'sub-pillar' => 0,
                'satellite' => 0,
                'support' => 0,
            ),
            'providers' => array(),
        );

        foreach ($relations as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $type = HUB_normalizeObjectType($relation['item_type']);
            $id = HUB_normalizeObjectId($relation['item_id']);
            if ($type === '' || $id === '') {
                continue;
            }

            $role = isset($relation['relation_role'])
                ? HUB_normalizeRelationRole($relation['relation_role'])
                : 'related';

            $summary['relations']++;
            $summary['roles'][$role]++;
            $summary['providers'][$type] = isset($summary['providers'][$type])
                ? $summary['providers'][$type] + 1 : 1;

            $item['relations']++;
            $item['roles'][$role]++;
            $item['providers'][$type] = isset($item['providers'][$type])
                ? $item['providers'][$type] + 1 : 1;

            $key = HUB_graphIdentityKey($type, $id);
            if ($key !== '') {
                if (!isset($parentsByItem[$key])) {
                    $parentsByItem[$key] = array();
                }
                $parentsByItem[$key][$pillarId] = true;
            }
        }

        ksort($item['providers'], SORT_STRING);
        $summary['pillar_items'][] = $item;
    }

    foreach ($parentsByItem as $key => $parents) {
        if (count($parents) > 1) {
            $summary['multi_parent_items']++;
        }
        if (isset($pillarIdentities[$key])) {
            $summary['nested_pillars']++;
        }
    }

    ksort($summary['providers'], SORT_STRING);
    usort($summary['pillar_items'], function ($left, $right) {
        if ((int) $left['pillar_id'] === (int) $right['pillar_id']) {
            return 0;
        }
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1 : 1;
    });

    return $summary;
}


/**
 * Build a deterministic provider-neutral inventory from approved Hub relations.
 *
 * This inventory exposes only Hub-owned structure and stable identities. It
 * deliberately avoids provider metadata resolution, link-health diagnostics
 * and inferred recommendations.
 *
 * @param bool $includeDisabled
 * @return array
 */
function HUB_editorialInventory($includeDisabled = false)
{
    $pillars = HUB_getPillars($includeDisabled);
    $pillarIdentities = array();
    $parentsByItem = array();

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $key = HUB_graphIdentityKey($pillar['source_type'], $pillar['source_id']);
        if ($key !== '') {
            $pillarIdentities[$key] = (int) $pillar['id'];
        }

        foreach (HUB_getRelations((int) $pillar['id'], $includeDisabled) as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $itemKey = HUB_graphIdentityKey($relation['item_type'], $relation['item_id']);
            if ($itemKey === '') {
                continue;
            }

            if (!isset($parentsByItem[$itemKey])) {
                $parentsByItem[$itemKey] = array();
            }
            $parentsByItem[$itemKey][(int) $pillar['id']] = true;
        }
    }

    $inventory = array(
        'schema' => 1,
        'pillars' => array(),
    );

    foreach ($pillars as $pillar) {
        if (!is_array($pillar)
            || empty($pillar['id'])
            || empty($pillar['source_type'])
            || empty($pillar['source_id'])
        ) {
            continue;
        }

        $pillarId = (int) $pillar['id'];
        $sourceType = HUB_normalizeObjectType($pillar['source_type']);
        $sourceId = HUB_normalizeObjectId($pillar['source_id']);
        if ($pillarId < 1 || $sourceType === '' || $sourceId === '') {
            continue;
        }

        $entry = array(
            'pillar_id' => $pillarId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'is_enabled' => !empty($pillar['is_enabled']),
            'items' => array(),
        );

        foreach (HUB_getRelations($pillarId, $includeDisabled) as $relation) {
            if (!is_array($relation)
                || empty($relation['item_type'])
                || empty($relation['item_id'])
            ) {
                continue;
            }

            $type = HUB_normalizeObjectType($relation['item_type']);
            $id = HUB_normalizeObjectId($relation['item_id']);
            if ($type === '' || $id === '') {
                continue;
            }

            $key = HUB_graphIdentityKey($type, $id);
            $entry['items'][] = array(
                'type' => $type,
                'id' => $id,
                'relation_role' => isset($relation['relation_role'])
                    ? HUB_normalizeRelationRole($relation['relation_role'])
                    : 'related',
                'position' => isset($relation['position']) ? (int) $relation['position'] : 0,
                'is_enabled' => !empty($relation['is_enabled']),
                'is_nested_pillar' => isset($pillarIdentities[$key]),
                'nested_pillar_id' => isset($pillarIdentities[$key])
                    ? (int) $pillarIdentities[$key] : 0,
                'parent_count' => isset($parentsByItem[$key])
                    ? count($parentsByItem[$key]) : 0,
            );
        }

        usort($entry['items'], function ($left, $right) {
            if ((int) $left['position'] !== (int) $right['position']) {
                return (int) $left['position'] < (int) $right['position'] ? -1 : 1;
            }

            $leftKey = (string) $left['type'] . ':' . (string) $left['id'];
            $rightKey = (string) $right['type'] . ':' . (string) $right['id'];

            return strcmp($leftKey, $rightKey);
        });

        $inventory['pillars'][] = $entry;
    }

    usort($inventory['pillars'], function ($left, $right) {
        if ((int) $left['pillar_id'] === (int) $right['pillar_id']) {
            return 0;
        }
        return (int) $left['pillar_id'] < (int) $right['pillar_id'] ? -1 : 1;
    });

    return $inventory;
}
