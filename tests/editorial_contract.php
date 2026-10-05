<?php

$_SERVER['PHP_SELF'] = 'tests/editorial_contract.php';

$hubEditorialPillars = array(
    array('id' => 1, 'source_type' => 'staticpages', 'source_id' => 'hub-a', 'is_enabled' => 1),
    array('id' => 2, 'source_type' => 'staticpages', 'source_id' => 'pillar-b', 'is_enabled' => 1),
    array('id' => 3, 'source_type' => 'staticpages', 'source_id' => 'hub-c', 'is_enabled' => 1),
);

$hubEditorialRelations = array(
    1 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'satellite', 'is_enabled' => 1),
    ),
    2 => array(
        array('item_type' => 'article', 'item_id' => 'story-y', 'relation_role' => 'satellite', 'is_enabled' => 1),
    ),
    3 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'support', 'is_enabled' => 1),
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
        ? $role : 'related';
}

function HUB_graphIdentityKey($type, $id)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);
    return $type === '' || $id === '' ? '' : $type . ':' . $id;
}

function HUB_getPillars($includeDisabled = true)
{
    global $hubEditorialPillars;
    return $hubEditorialPillars;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $hubEditorialRelations;
    return isset($hubEditorialRelations[(int) $pillarId])
        ? $hubEditorialRelations[(int) $pillarId]
        : array();
}

require_once dirname(__DIR__) . '/lib-editorial.php';

function hubEditorialAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$summary = HUB_editorialSummary(false);

hubEditorialAssert($summary['schema'] === 1, 'editorial summary schema is explicit');
hubEditorialAssert($summary['pillars'] === 3, 'editorial summary counts approved pillars');
hubEditorialAssert($summary['relations'] === 5, 'editorial summary counts approved relations');
hubEditorialAssert($summary['roles']['sub-pillar'] === 2, 'sub-pillar edges are counted');
hubEditorialAssert($summary['roles']['satellite'] === 2, 'satellite edges are counted');
hubEditorialAssert($summary['roles']['support'] === 1, 'support edges are counted');
hubEditorialAssert($summary['roles']['related'] === 0, 'neutral related edges are counted separately');
hubEditorialAssert($summary['providers']['staticpages'] === 2, 'Static Page provider count is normalized');
hubEditorialAssert($summary['providers']['article'] === 3, 'article provider count is normalized');
hubEditorialAssert($summary['nested_pillars'] === 1, 'related identities that are also pillars are counted as nested pillars');
hubEditorialAssert($summary['multi_parent_items'] === 2, 'multi-parent identities are counted without enforcing a tree');
hubEditorialAssert(count($summary['pillar_items']) === 3, 'per-pillar structural summaries are exposed');
hubEditorialAssert($summary['pillar_items'][0]['pillar_id'] === 1, 'pillar summaries are deterministic by id');

$inventory = HUB_editorialInventory(false);
hubEditorialAssert($inventory['schema'] === 1, 'editorial inventory schema is explicit');
hubEditorialAssert(count($inventory['pillars']) === 3, 'editorial inventory exposes every approved pillar');
hubEditorialAssert(count($inventory['pillars'][0]['items']) === 2, 'pillar inventory exposes approved relation identities');
hubEditorialAssert($inventory['pillars'][0]['items'][0]['relation_role'] === 'sub-pillar', 'inventory preserves structural relation roles');
hubEditorialAssert($inventory['pillars'][0]['items'][0]['is_nested_pillar'] === true, 'inventory identifies related objects that are also pillars');
hubEditorialAssert($inventory['pillars'][0]['items'][0]['nested_pillar_id'] === 2, 'inventory exposes nested pillar identity without duplicating content');
hubEditorialAssert($inventory['pillars'][0]['items'][0]['parent_count'] === 2, 'inventory exposes multi-parent participation');
hubEditorialAssert($inventory['pillars'][0]['items'][1]['type'] === 'article', 'inventory remains provider-neutral by stable type + id');

$source = file_get_contents(dirname(__DIR__) . '/lib-editorial.php');
hubEditorialAssert(strpos($source, 'HUB_resolveObject(') === false, 'structural summary does not mix provider metadata resolution');
hubEditorialAssert(strpos($source, 'HUB_linkAudit') === false, 'structural summary does not mix SEO/link diagnostics');
hubEditorialAssert(strpos($source, 'HUB_resolveObject(') === false, 'editorial models do not mix provider metadata resolution');
hubEditorialAssert(strpos($source, 'HUB_linkAudit') === false, 'editorial models do not mix SEO/link diagnostics');
hubEditorialAssert(strpos($source, 'suggest') === false, 'editorial models do not mix inferred suggestions');

$adminSource = file_get_contents(dirname(__DIR__) . '/admin/editorial.php');
hubEditorialAssert(strpos($adminSource, 'Approved relation inventory') !== false, 'editorial admin exposes approved structural inventory');
hubEditorialAssert(strpos($adminSource, 'HUB_editorialInventory(false)') !== false, 'editorial admin reads the shared inventory model');
hubEditorialAssert(strpos($adminSource, 'HUB_saveRelation(') === false, 'editorial mapping view remains read-only');

echo "Hub editorial summary contract tests passed." . PHP_EOL;
