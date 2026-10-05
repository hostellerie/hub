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
    'topics' => array(
        'seo' => array(
            array(
                'pillar_id' => 12,
                'source_type' => 'staticpages',
                'source_id' => 'seo-guide',
                'reasons' => array('topic-assignment'),
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

function HUB_findPillarContextsForTopic($topicId, $includeDisabled = false)
{
    global $hubLifecycleFixture;
    return isset($hubLifecycleFixture['topics'][(string) $topicId])
        ? $hubLifecycleFixture['topics'][(string) $topicId]
        : array();
}

function HUB_invalidateRelationshipCaches($pillarId, $type = '', $id = '')
{
    global $hubLifecycleFixture;
    $hubLifecycleFixture['invalidated'][] = array((int) $pillarId, (string) $type, (string) $id);
}

function HUB_migrateObjectIdentity($type, $oldId, $newId)
{
    global $hubLifecycleFixture;

    $oldKey = (string) $type . ':' . (string) $oldId;
    $newKey = (string) $type . ':' . (string) $newId;
    $changed = false;

    if (isset($hubLifecycleFixture['pillars'][$oldKey])
        && !isset($hubLifecycleFixture['pillars'][$newKey])
    ) {
        $pillar = $hubLifecycleFixture['pillars'][$oldKey];
        unset($hubLifecycleFixture['pillars'][$oldKey]);
        $pillar['source_id'] = (string) $newId;
        $hubLifecycleFixture['pillars'][$newKey] = $pillar;
        $changed = true;
    }

    if (isset($hubLifecycleFixture['relations'][$oldKey])
        && !isset($hubLifecycleFixture['relations'][$newKey])
    ) {
        $hubLifecycleFixture['relations'][$newKey] = $hubLifecycleFixture['relations'][$oldKey];
        unset($hubLifecycleFixture['relations'][$oldKey]);
        $changed = true;
    }

    return array(
        'changed' => $changed,
        'pillar_updated' => false,
        'relations_updated' => $changed ? 1 : 0,
        'collisions' => array(),
        'error' => '',
    );
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

$topicContexts = HUB_getAffectedContexts('topic', 'seo');
hubLifecycleAssert(count($topicContexts) === 1, 'topic metadata change resolves assigned Hub pillar');
hubLifecycleAssert($topicContexts[0]['pillar_id'] === 12, 'topic change points to the assigned Static Page pillar');
hubLifecycleAssert(in_array('topic-assignment', $topicContexts[0]['reasons'], true), 'topic assignment reason is explicit');

$hubLifecycleFixture['invalidated'] = array();
HUB_handleItemSaved('seo', 'topic');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) === 1, 'topic save invalidates assigned pillar context');

$hubLifecycleFixture['invalidated'] = array();
HUB_handleItemDeleted('seo', 'topic');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) === 1, 'topic delete invalidates assigned pillar context before Core removes assignments');

$hubLifecycleFixture['invalidated'] = array();
$savedContexts = HUB_handleItemSaved('story-1', 'article');
hubLifecycleAssert(count($savedContexts) === 1, 'save handler returns affected contexts');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) === 1, 'save handler invalidates affected context');

$hubLifecycleFixture['invalidated'] = array();
$renamedContexts = HUB_handleItemSaved('story-new', 'article', 'old-story');
hubLifecycleAssert(count($renamedContexts) === 1, 'renamed identity resolves the migrated affected context');
hubLifecycleAssert($renamedContexts[0]['pillar_id'] === 11, 'renamed identity now points to the original pillar context');
hubLifecycleAssert(isset($hubLifecycleFixture['relations']['article:story-new']), 'Hub relation follows the provider identity change');
hubLifecycleAssert(!isset($hubLifecycleFixture['relations']['article:old-story']), 'old Hub relation identity is no longer retained after a clean migration');
hubLifecycleAssert(count($hubLifecycleFixture['invalidated']) >= 2, 'rename invalidates old and new affected contexts');

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
hubLifecycleAssert(strpos($functionsSource, "'hub.affected.read'") !== false, 'implemented affected-context capability is advertised');

$relationsSource = file_get_contents(dirname(__DIR__) . '/lib-relations.php');
hubLifecycleAssert(strpos($relationsSource, 'function HUB_migrateObjectIdentity(') !== false, 'Hub exposes a generic owned-identity migration helper');
hubLifecycleAssert(strpos($relationsSource, "if (!empty(\$result['collisions']))") !== false, 'identity migration aborts before writes when collisions are detected');
hubLifecycleAssert(strpos($relationsSource, "kind' => 'pillar'") !== false, 'pillar identity collisions are classified');
hubLifecycleAssert(strpos($relationsSource, "kind' => 'relation'") !== false, 'relation identity collisions are classified');
hubLifecycleAssert(strpos($relationsSource, "UPDATE {\$_TABLES['hub_relations']} SET") !== false, 'Hub updates only its own relation persistence during identity migration');

$staticPagesSource = file_get_contents(dirname(__DIR__) . '/lib-staticpages.php');
hubLifecycleAssert(strpos($staticPagesSource, 'function HUB_findPillarContextsForTopic(') !== false, 'Hub resolves Static Page pillars assigned to a changed topic');
hubLifecycleAssert(strpos($staticPagesSource, "ta.type = 'staticpages'") !== false, 'topic impact lookup is limited to Static Page assignments');

echo "Hub lifecycle contract tests passed." . PHP_EOL;
