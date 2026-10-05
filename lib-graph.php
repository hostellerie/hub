<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_graphIdentityKey($type, $id)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);

    return $type === '' || $id === '' ? '' : $type . ':' . $id;
}

function HUB_graphNode($type, $id)
{
    return array(
        'type' => HUB_normalizeObjectType($type),
        'id' => HUB_normalizeObjectId($id),
    );
}

/**
 * Return immediate graph neighbors for one stable Hub identity.
 *
 * @param string $type
 * @param string $id
 * @param bool $includeDisabled
 * @return array
 */
function HUB_graphNeighbors($type, $id, $includeDisabled = false)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);
    if ($type === '' || $id === '') {
        return array('parents' => array(), 'children' => array(), 'edges' => array());
    }

    $parents = array();
    $children = array();
    $edges = array();

    foreach (HUB_findPillarsForItem($type, $id, $includeDisabled) as $pillar) {
        if (!is_array($pillar) || empty($pillar['source_type']) || empty($pillar['source_id'])) {
            continue;
        }

        $parent = HUB_graphNode($pillar['source_type'], $pillar['source_id']);
        $key = HUB_graphIdentityKey($parent['type'], $parent['id']);
        if ($key === '') {
            continue;
        }

        $parents[$key] = $parent;
        $edges['down:' . $key . '>' . $type . ':' . $id] = array(
            'from' => $parent,
            'to' => HUB_graphNode($type, $id),
            'kind' => isset($relation['relation_role']) ? HUB_normalizeRelationRole($relation['relation_role']) : 'related',
        );
    }

    $pillar = HUB_findPillar($type, $id);
    if (is_array($pillar) && ($includeDisabled || !empty($pillar['is_enabled']))) {
        foreach (HUB_getRelations((int) $pillar['id'], $includeDisabled) as $relation) {
            if (!is_array($relation) || empty($relation['item_type']) || empty($relation['item_id'])) {
                continue;
            }

            $child = HUB_graphNode($relation['item_type'], $relation['item_id']);
            $key = HUB_graphIdentityKey($child['type'], $child['id']);
            if ($key === '') {
                continue;
            }

            $children[$key] = $child;
            $edges['down:' . $type . ':' . $id . '>' . $key] = array(
                'from' => HUB_graphNode($type, $id),
                'to' => $child,
                'kind' => 'related',
            );
        }
    }

    ksort($parents, SORT_STRING);
    ksort($children, SORT_STRING);
    ksort($edges, SORT_STRING);

    return array(
        'parents' => array_values($parents),
        'children' => array_values($children),
        'edges' => array_values($edges),
    );
}

/**
 * Traverse Hub's approved relationship graph around one object.
 *
 * Traversal is bidirectional, depth-bounded and cycle-safe. The same content
 * identity may legitimately appear in several approved contexts, so traversal
 * never assumes a single parent.
 *
 * @param string $type
 * @param string $id
 * @param int $maxDepth
 * @param bool $includeDisabled
 * @return array
 */
function HUB_graphContext($type, $id, $maxDepth = 4, $includeDisabled = false)
{
    $start = HUB_graphNode($type, $id);
    $startKey = HUB_graphIdentityKey($start['type'], $start['id']);
    $maxDepth = max(0, min(16, (int) $maxDepth));

    if ($startKey === '') {
        return array(
            'start' => $start,
            'max_depth' => $maxDepth,
            'nodes' => array(),
            'edges' => array(),
        );
    }

    $nodes = array(
        $startKey => array(
            'type' => $start['type'],
            'id' => $start['id'],
            'depth' => 0,
        ),
    );
    $edges = array();
    $queue = array(array($start['type'], $start['id'], 0));
    $expanded = array();

    while (!empty($queue)) {
        $current = array_shift($queue);
        $currentType = (string) $current[0];
        $currentId = (string) $current[1];
        $depth = (int) $current[2];
        $currentKey = HUB_graphIdentityKey($currentType, $currentId);

        if ($currentKey === '' || isset($expanded[$currentKey]) || $depth >= $maxDepth) {
            continue;
        }
        $expanded[$currentKey] = true;

        $neighbors = HUB_graphNeighbors($currentType, $currentId, $includeDisabled);

        foreach ($neighbors['edges'] as $edge) {
            $fromKey = HUB_graphIdentityKey($edge['from']['type'], $edge['from']['id']);
            $toKey = HUB_graphIdentityKey($edge['to']['type'], $edge['to']['id']);
            if ($fromKey === '' || $toKey === '') {
                continue;
            }

            $edgeKey = $fromKey . '>' . $toKey . ':' . (string) $edge['kind'];
            $edges[$edgeKey] = $edge;

            foreach (array($edge['from'], $edge['to']) as $node) {
                $nodeKey = HUB_graphIdentityKey($node['type'], $node['id']);
                if ($nodeKey === '' || isset($nodes[$nodeKey])) {
                    continue;
                }

                $nodes[$nodeKey] = array(
                    'type' => $node['type'],
                    'id' => $node['id'],
                    'depth' => $depth + 1,
                );
                $queue[] = array($node['type'], $node['id'], $depth + 1);
            }
        }
    }

    ksort($nodes, SORT_STRING);
    ksort($edges, SORT_STRING);

    return array(
        'start' => $start,
        'max_depth' => $maxDepth,
        'nodes' => array_values($nodes),
        'edges' => array_values($edges),
    );
}
