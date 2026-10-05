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
