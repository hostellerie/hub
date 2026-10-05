<?php

$_SERVER['PHP_SELF'] = 'tests/lifecycle_contract.php';

$hubLifecycleFixture = array(
    'pillars' => array(
        'staticpages:guide' => array(
            'id' => 10,
            'source_type' => 'staticpages',
            'source_id' => 'guide',
            'is_enabled' => 1,
        ),
    ),
    'relations' => array(
        'article:story-1' => array(
            array(
                'id' => 10,
                'source_type' => 'staticpages',
                'source_id' => 'guide',
                'is_enabled' => 1,
            ),
        ),
        'article:old-story' => array(
            array(
                'id' => 11,
                'source_type' => 'staticpages',
                'source_id' => 'legacy-guide',
                'is_enabled' => 1,
            ),
        ),
    ),
    'invalidated' => array(),
);

function HUB_normalizeObjectType($type)
{
    $type = strtolower(trim((string) $type));
    $type = preg_replace('/[^a-z0-9_-]+/', '', $type);
    return substr($type, 0, 64);
}

function HUB_normalizeObjectId($id)
{
    return substr(trim((string) $id), 0, 128);
}

function HUB_findPillar($type, $id)
{
    global $hubLifecycleFixture;
    $key = (string) $type . ':' . (string) $id;
    return isset($hubLifecycleFixture['pillars'][$key])
        ? $hubLifecycleFixture['pillars'][$key]
        : false;
}

function HUB_findPillarsForItem($type, $id, $includeDisabled = false)
{
    global $hubLifecycleFixture;
    $key = (string) $type . ':' . (string) $id;
    return isset($hubLifecycleFixture['relations'][$key])
        ? $hubLifecycleFixture['relations'][$key]
        : array();
}

function HUB_invalidateRelationshipCaches($pillarId, $type = '', $id = '')
{
    global $hubLifecycleFixture;
    $hubLifecycleFixture['invalidated'][] = array((int) $pillarId, (string) $type, (string) $id);
}

require_once dirname(__DIR__) . '/lib-lifecycle.php';

function hubLifecycleAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$identity = HUB_normalizeLifecycleIdentity(' story-1 ', 'story');
hubLifecycleAssert($identity['type'] === 'article', 'story lifecycle type normalizes to article');
hubLifecycleAssert($identity['id'] === 'story-1', 'lifecycle id is normalized');

$legacy = HUB_normalizeLifecycleIdentity('42', 'maps.marker');
hubLifecycleAssert($legacy['type'] === 'maps', 'legacy dotted type keeps provider type');
hubLifecycleAssert($legacy['sub_type'] === 'marker', 'legacy dotted type exposes subtype');

$pillarContexts = HUB_getAffectedContexts('staticpages', 'guide');
hubLifecycleAssert(count($pillarContexts) === 1, 'pillar source change affects its own context');
hubLifecycleAssert(in_array('pillar-source', $pillarContexts[0]['reasons'], true), 'pillar-source reason is explicit');

$relationContexts = HUB_getAffectedContexts('article', 'story-1');
hubLifecycleAssert(count($relationContexts) === 1, 'related item change resolves affected pillar');
hubLifecycleAssert(in_array('related-item', $relationContexts[0]['reasons'], true), 'related-item reason is explicit');

$hubLifecycleFixture['invalidated'] = array();
$savedContexts = HUB_handleItemSaved('story-1', 'article');
hubLifecycleAssert(count($savedContexts) === 1, 'save handler returns affected contexts');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) === 1, 'save handler invalidates affected context');

$hubLifecycleFixture['invalidated'] = array();
$renamedContexts = HUB_handleItemSaved('story-new', 'article', 'old-story');
hubLifecycleAssert(count($renamedContexts) === 1, 'old identity context is retained for invalidation on rename');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) >= 1, 'rename invalidates old affected context');

$beforeRelations = $hubLifecycleFixture['relations'];
$hubLifecycleFixture['invalidated'] = array();
$deletedContexts = HUB_handleItemDeleted('story-1', 'article');
hubLifecycleAssert(count($deletedContexts) === 1, 'delete handler returns affected contexts');
hubLifecycleAssert($beforeRelations === $hubLifecycleFixture['relations'], 'delete handler does not destroy relationship state');

$functionsSource = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubLifecycleAssert(strpos($functionsSource, "require_once $hub_path . 'lib-lifecycle.php';") !== false, 'lifecycle library is loaded');
hubLifecycleAssert(strpos($functionsSource, 'function plugin_itemsaved_hub($id, $type, $old_id = \'\', $sub_type = \'\')') !== false, 'save listener supports legacy and 2.2.2 arguments');
hubLifecycleAssert(strpos($functionsSource, 'function plugin_itemdeleted_hub($id, $type, $sub_type = \'\')') !== false, 'delete listener supports optional subtype');
hubLifecycleAssert(strpos($functionsSource, "'listens' => array('item.saved', 'item.deleted')") !== false, 'Hub declares implemented lifecycle listeners');
hubLifecycleAssert(strpos($functionsSource, "'capabilities' => array()") !== false, 'future hub.* capabilities remain unadvertised');

echo "Hub lifecycle contract tests passed." . PHP_EOL;
