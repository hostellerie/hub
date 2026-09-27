<?php

$_SERVER['PHP_SELF'] = 'tests/relations_contract.php';

$hubItemInfoFixture = array(
    'article:story-1' => array('story-1', 'Story one', '/article.php?story=story-1'),
);

function PLG_getItemInfo($type, $id, $what)
{
    global $hubItemInfoFixture;
    $key = (string) $type . ':' . (string) $id;

    return isset($hubItemInfoFixture[$key]) ? $hubItemInfoFixture[$key] : array();
}

function plugin_getiteminfo_article()
{
}

function plugin_searchtypes_calendar()
{
    return array(
        'calendar' => 'Calendar',
        'events' => 'Events',
    );
}

$_PLUGINS = array('article', 'calendar');

require_once dirname(__DIR__) . '/lib-relations.php';

function hubAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

hubAssert(HUB_normalizeObjectType(' Article ') === 'article', 'object type normalization');
hubAssert(HUB_normalizeObjectType('maps<script>') === 'mapsscript', 'unsafe type characters are stripped');
hubAssert(HUB_normalizeObjectId('  abc-123  ') === 'abc-123', 'object id normalization');

$resolved = HUB_resolveObject('article', 'story-1');
hubAssert(!empty($resolved['exists']), 'known object resolves');
hubAssert($resolved['status'] === 'resolved', 'known object status is resolved');
hubAssert($resolved['title'] === 'Story one', 'known object title is returned');
hubAssert(!empty($resolved['provider_available']), 'loaded provider is reported');

$missingKnownProvider = HUB_resolveObject('article', 'missing-story');
hubAssert(empty($missingKnownProvider['exists']), 'missing known-provider object remains unresolved');
hubAssert(!empty($missingKnownProvider['provider_available']), 'known provider remains detectable');
hubAssert(strpos($missingKnownProvider['diagnostic'], 'did not resolve') !== false, 'missing object diagnostic is specific');

$missingProvider = HUB_resolveObject('videos', 'video-1');
hubAssert(empty($missingProvider['exists']), 'unknown provider object remains unresolved');
hubAssert(empty($missingProvider['provider_available']), 'unknown provider is reported unavailable');
hubAssert(strpos($missingProvider['diagnostic'], 'No loaded Item Info provider') !== false, 'missing provider diagnostic is specific');

$types = HUB_relationObjectTypes();
hubAssert(in_array('article', $types, true), 'article is always suggested');
hubAssert(in_array('staticpages', $types, true), 'staticpages is always suggested');
hubAssert(in_array('calendar', $types, true), 'plugin primary/search type is suggested');
hubAssert(in_array('events', $types, true), 'plugin_searchtypes keys are suggested');
foreach ($types as $type) {
    hubAssert(strpos($type, 'declaredbyplugin') === false, 'display-only audit text is never used as an object type');
    hubAssert(strpos($type, 'observedinlifecycle') === false, 'lifecycle evidence text is never used as an object type');
}

$installSql = file_get_contents(dirname(__DIR__) . '/sql/mysql_install.php');
$upgradeSql = file_get_contents(dirname(__DIR__) . '/install_updates.php');

foreach (array($installSql, $upgradeSql) as $sqlSource) {
    hubAssert(strpos($sqlSource, 'UNIQUE KEY source_identity (source_type, source_id)') !== false, 'pillar identity uniqueness is enforced');
    hubAssert(strpos($sqlSource, 'UNIQUE KEY pillar_item (pillar_id, item_type, item_id)') !== false, 'relation identity uniqueness is enforced');
    hubAssert(strpos($sqlSource, 'KEY pillar_order (pillar_id, is_enabled, position, id)') !== false, 'relation ordering index is present');
}

$relationsSource = file_get_contents(dirname(__DIR__) . '/lib-relations.php');
hubAssert(strpos($relationsSource, 'ORDER BY position ASC, id ASC') !== false, 'relation retrieval order is deterministic');
hubAssert(strpos($relationsSource, "itemType === (string) \$pillar['source_type']") !== false, 'direct self-relations remain rejected');

echo "Hub relation contract tests passed." . PHP_EOL;
