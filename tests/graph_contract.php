<?php

$_SERVER['PHP_SELF'] = 'tests/graph_contract.php';

$hubGraphPillars = array(
    'staticpages:hub-a' => array('id' => 1, 'source_type' => 'staticpages', 'source_id' => 'hub-a', 'is_enabled' => 1),
    'staticpages:pillar-b' => array('id' => 2, 'source_type' => 'staticpages', 'source_id' => 'pillar-b', 'is_enabled' => 1),
    'staticpages:hub-c' => array('id' => 3, 'source_type' => 'staticpages', 'source_id' => 'hub-c', 'is_enabled' => 1),
);

$hubGraphRelations = array(
    1 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-a', 'is_enabled' => 1),
    ),
    2 => array(
        array('item_type' => 'article', 'item_id' => 'leaf-1', 'relation_role' => 'satellite', 'is_enabled' => 1),
        array('item_type' => 'staticpages', 'item_id' => 'hub-a', 'is_enabled' => 1),
    ),
    3 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'is_enabled' => 1),
    ),
);

function HUB_normalizeObjectType($type)
{
    return strtolower(trim((string) $type));
}

function HUB_normalizeObjectId($id)
{
    return trim((string) $id);
}

function HUB_normalizeRelationRole($role)
{
    $role = strtolower(trim((string) $role));
    return in_array($role, array('related', 'sub-pillar', 'satellite', 'support'), true)
        ? $role
        : 'related';
}

function HUB_findPillar($type, $id)
{
    global $hubGraphPillars;
    $key = (string) $type . ':' . (string) $id;
    return isset($hubGraphPillars[$key]) ? $hubGraphPillars[$key] : false;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $hubGraphRelations;
    $rows = isset($hubGraphRelations[(int) $pillarId]) ? $hubGraphRelations[(int) $pillarId] : array();

    if ($includeDisabled) {
        return $rows;
    }

    return array_values(array_filter($rows, function ($row) {
        return !empty($row['is_enabled']);
    }));
}

function HUB_findPillarsForItem($type, $id, $includeDisabled = false)
{
    global $hubGraphPillars, $hubGraphRelations;

    $out = array();
    foreach ($hubGraphRelations as $pillarId => $relations) {
        foreach ($relations as $relation) {
            if ((string) $relation['item_type'] !== (string) $type
                || (string) $relation['item_id'] !== (string) $id
                || (!$includeDisabled && empty($relation['is_enabled']))
            ) {
                continue;
            }

            foreach ($hubGraphPillars as $pillar) {
                if ((int) $pillar['id'] === (int) $pillarId
                    && ($includeDisabled || !empty($pillar['is_enabled']))
                ) {
                    $out[] = $pillar;
                }
            }
        }
    }

    return $out;
}

require_once dirname(__DIR__) . '/lib-graph.php';

function hubGraphAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$neighbors = HUB_graphNeighbors('staticpages', 'pillar-b');
hubGraphAssert(count($neighbors['parents']) === 2, 'sub-pillar can belong to multiple parent contexts');
hubGraphAssert(count($neighbors['children']) === 2, 'pillar exposes all approved child relations');
$edgeKinds = array();
foreach ($neighbors['edges'] as $edge) {
    $edgeKinds[] = $edge['kind'];
}
hubGraphAssert(in_array('satellite', $edgeKinds, true), 'graph preserves structural satellite role');

$context = HUB_graphContext('staticpages', 'pillar-b', 4);
hubGraphAssert($context['start']['type'] === 'staticpages', 'graph context preserves start type');
hubGraphAssert($context['start']['id'] === 'pillar-b', 'graph context preserves start id');
hubGraphAssert(count($context['nodes']) === 5, 'graph traversal reaches parents, descendants and cycle peer once');

$keys = array();
foreach ($context['nodes'] as $node) {
    $keys[] = $node['type'] . ':' . $node['id'];
}
sort($keys);

hubGraphAssert(in_array('staticpages:hub-a', $keys, true), 'graph reaches first parent hub');
hubGraphAssert(in_array('staticpages:hub-c', $keys, true), 'graph reaches second parent hub');
hubGraphAssert(in_array('article:leaf-1', $keys, true), 'graph reaches descendant satellite');
hubGraphAssert(count(array_keys($keys, 'staticpages:pillar-b', true)) === 1, 'cycle protection keeps each node unique');

$shallow = HUB_graphContext('staticpages', 'pillar-b', 0);
hubGraphAssert(count($shallow['nodes']) === 1, 'depth zero returns only the starting node');
hubGraphAssert(count($shallow['edges']) === 0, 'depth zero traverses no edges');

$source = file_get_contents(dirname(__DIR__) . '/lib-graph.php');
hubGraphAssert(strpos($source, 'function HUB_graphContext(') !== false, 'generic graph context helper exists');
hubGraphAssert(strpos($source, '$expanded') !== false, 'graph traversal records expanded nodes for cycle protection');
hubGraphAssert(strpos($source, 'max(0, min(16, (int) $maxDepth))') !== false, 'graph traversal depth is bounded');

echo "Hub graph contract tests passed." . PHP_EOL;
