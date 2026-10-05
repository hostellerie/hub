<?php

$_SERVER['PHP_SELF'] = 'tests/relations_contract.php';

$hubItemInfoFixture = array(
    'article:story-1' => array('story-1', 'Story one', '/article.php?story=story-1'),
    'videos:video-1' => array(
        'id' => 'video-1',
        'title' => 'Video one',
        'url' => '/videos/watch.php?v=video-1',
    ),
);

function PLG_getItemInfo($type, $id, $what, $uid = 0, $options = array())
{
    global $hubItemInfoFixture;

    if ($id === '*' && $type === 'videos') {
        return array(
            $hubItemInfoFixture['videos:video-1'],
            array(
                'id' => 'video-2',
                'title' => 'Video two',
                'url' => '/videos/watch.php?v=video-2',
            ),
        );
    }

    $key = (string) $type . ':' . (string) $id;

    return isset($hubItemInfoFixture[$key]) ? $hubItemInfoFixture[$key] : array();
}

function plugin_getiteminfo_article()
{
}

function plugin_getiteminfo_videos()
{
}

function HUB_contentContractEvidence($type)
{
    return array(
        'collection_declared' => ($type === 'videos'),
        'collection_source' => false,
    );
}

$_PLUGINS = array('article', 'videos');

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
hubAssert(HUB_normalizeRelationRole('sub-pillar') === 'sub-pillar', 'structural relation role is preserved');
hubAssert(HUB_normalizeRelationRole(' SATELLITE ') === 'satellite', 'relation role normalization is case-insensitive');
hubAssert(HUB_normalizeRelationRole('guide') === 'related', 'relation role normalization defaults editorial functions to related');
hubAssert(HUB_normalizeRelationRole('anything') === 'related', 'relation role normalization defaults unknown values');
hubAssert(HUB_normalizeEditorialRole(' GUIDE ') === 'guide', 'editorial role normalization preserves guide');
hubAssert(HUB_normalizeEditorialRole('case-study') === 'case-study', 'editorial role normalization preserves case-study');
hubAssert(HUB_normalizeEditorialRole('sub-pillar') === '', 'structural role is not accepted as editorial function');
hubAssert(HUB_normalizeEditorialRole('anything') === '', 'unknown editorial role is discarded');

$resolved = HUB_resolveObject('article', 'story-1');
hubAssert(!empty($resolved['exists']), 'known object resolves');
hubAssert($resolved['status'] === 'resolved', 'known object status is resolved');
hubAssert($resolved['title'] === 'Story one', 'known object title is returned');
hubAssert(!empty($resolved['provider_available']), 'loaded provider is reported');

$resolvedVideo = HUB_resolveObject('videos', 'video-1');
hubAssert(!empty($resolvedVideo['exists']), 'associative Item Info record resolves');
hubAssert($resolvedVideo['title'] === 'Video one', 'associative Item Info title is normalized');

$collection = HUB_relationObjectOptions('videos', 100);
hubAssert(!empty($collection['supported']), 'declared collection is supported');
hubAssert(count($collection['items']) === 2, 'collection returns selectable items');
hubAssert($collection['items'][0]['title'] === 'Video one', 'collection items are normalized and sorted');

$articleCollection = HUB_relationObjectOptions('article', 100);
hubAssert(!empty($articleCollection['supported']), 'Core articles use the dedicated selectable collection fallback');

$missingKnownProvider = HUB_resolveObject('article', 'missing-story');
hubAssert(empty($missingKnownProvider['exists']), 'missing known-provider object remains unresolved');
hubAssert(!empty($missingKnownProvider['provider_available']), 'known provider remains detectable');
hubAssert(strpos($missingKnownProvider['diagnostic'], 'did not resolve') !== false, 'missing object diagnostic is specific');

$missingProvider = HUB_resolveObject('maps', 'map-1');
hubAssert(empty($missingProvider['exists']), 'unknown provider object remains unresolved');
hubAssert(empty($missingProvider['provider_available']), 'unknown provider is reported unavailable');
hubAssert(strpos($missingProvider['diagnostic'], 'No loaded Item Info provider') !== false, 'missing provider diagnostic is specific');

$articleBacklink = HUB_backlinkIntegrationStatus('article');
hubAssert(!empty($articleBacklink['supported']), 'article backlink fallback is supported');
hubAssert($articleBacklink['mode'] === 'core-template-fallback', 'article backlink mode is explicit');

$providerBacklinkTypes = array('forum', 'documents', 'videos', 'maps', 'mediagallery', 'polls');
foreach ($providerBacklinkTypes as $providerBacklinkType) {
    $providerBacklink = HUB_backlinkIntegrationStatus($providerBacklinkType);
    hubAssert(!empty($providerBacklink['supported']), $providerBacklinkType . ' generic item-display backlink is supported');
    hubAssert(
        $providerBacklink['mode'] === 'generic-itemdisplay-provider',
        $providerBacklinkType . ' generic item-display backlink mode is explicit'
    );
}

$staticPageBacklink = HUB_backlinkIntegrationStatus('staticpages');
hubAssert(!empty($staticPageBacklink['supported']), 'Static Pages backlink integration remains supported');
hubAssert($staticPageBacklink['mode'] === 'staticpage-template-hook', 'Static Pages backlink mode is explicit');

$unknownBacklink = HUB_backlinkIntegrationStatus('events');
hubAssert(empty($unknownBacklink['supported']), 'unknown provider backlink is not advertised as supported');
hubAssert($unknownBacklink['mode'] === 'provider-hook-unconfirmed', 'unknown provider backlink mode is explicit');

$types = HUB_relationObjectTypes();
hubAssert(in_array('article', $types, true), 'article is always suggested');
hubAssert(in_array('staticpages', $types, true), 'staticpages is always suggested');
hubAssert(in_array('videos', $types, true), 'active Item Info provider is suggested');
hubAssert(!in_array('events', $types, true), 'search-only subtypes are not exposed as relation object types');

$installSql = file_get_contents(dirname(__DIR__) . '/sql/mysql_install.php');
$upgradeSql = file_get_contents(dirname(__DIR__) . '/install_updates.php');

foreach (array($installSql, $upgradeSql) as $sqlSource) {
    hubAssert(strpos($sqlSource, 'UNIQUE KEY source_identity (source_type, source_id)') !== false, 'pillar identity uniqueness is enforced');
    hubAssert(strpos($sqlSource, 'UNIQUE KEY pillar_item (pillar_id, item_type, item_id)') !== false, 'relation identity uniqueness is enforced');
    hubAssert(strpos($sqlSource, 'KEY pillar_order (pillar_id, is_enabled, position, id)') !== false, 'relation ordering index is present');
}

$functionsSource = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubAssert(strpos($functionsSource, "function plugin_itemdisplay_hub") !== false, 'Hub item-display callback is exposed');
hubAssert(strpos($functionsSource, "\$type === 'article' || \$type === 'staticpages'") !== false, 'dedicated article/staticpage paths avoid duplicate item-display backlinks');
hubAssert(strpos($functionsSource, "HUB_renderItemPillarBacklinks(\$type, \$id)") !== false, 'provider item-display callback renders pillar backlinks generically');

$relationsSource = file_get_contents(dirname(__DIR__) . '/lib-relations.php');
hubAssert(strpos($relationsSource, 'ORDER BY position ASC, id ASC') !== false, 'relation retrieval order is deterministic');
hubAssert(strpos($relationsSource, "itemType === (string) \$pillar['source_type']") !== false, 'direct self-relations remain rejected');

echo "Hub relation contract tests passed." . PHP_EOL;


$relationsSource = file_get_contents(dirname(__DIR__) . '/lib-relations.php');
$sqlInstallSource = file_get_contents(dirname(__DIR__) . '/sql/mysql_install.php');
$upgradeSource = file_get_contents(dirname(__DIR__) . '/install_updates.php');

if (strpos($relationsSource, 'function HUB_relationRoles()') === false
    || strpos($relationsSource, 'function HUB_normalizeRelationRole($role)') === false
    || strpos($relationsSource, "relation_role = '") === false
    || strpos($relationsSource, 'relation_role, position') === false
) {
    fwrite(STDERR, "Hub structural relation role contract missing\n");
    exit(1);
}

if (strpos($sqlInstallSource, "relation_role varchar(32) NOT NULL DEFAULT 'related'") === false
    || strpos($upgradeSource, 'function HUB_updateSchema_0_5_0()') === false
    || strpos($upgradeSource, "ADD relation_role varchar(32) NOT NULL DEFAULT 'related'") === false
) {
    fwrite(STDERR, "Hub 0.5.0 relation role schema contract missing\n");
    exit(1);
}

if (strpos($sqlInstallSource, 'title_override') !== false
    || strpos($upgradeSource, 'DROP COLUMN title_override') === false
) {
    fwrite(STDERR, "Hub 0.5.0 must remove obsolete pillar title override persistence\n");
    exit(1);
}


if (strpos($relationsSource, 'function HUB_editorialRoles()') === false
    || strpos($relationsSource, 'function HUB_normalizeEditorialRole($role)') === false
    || strpos($relationsSource, "function HUB_savePillar(\$pillarId, \$sourceType, \$sourceId, \$isEnabled = 1, \$ownerId = 0, \$editorialRole = '')") === false
    || strpos($relationsSource, "function HUB_saveRelation(\$relationId, \$pillarId, \$itemType, \$itemId, \$position = 0, \$isEnabled = 1, \$ownerId = 0, \$relationRole = 'related', \$editorialRole = '')") === false
) {
    fwrite(STDERR, "Hub editorial role persistence contract missing\n");
    exit(1);
}

if (substr_count($sqlInstallSource, "editorial_role varchar(32) NOT NULL DEFAULT ''") < 2
    || strpos($upgradeSource, "ADD editorial_role varchar(32) NOT NULL DEFAULT '' AFTER source_id") === false
    || strpos($upgradeSource, "ADD editorial_role varchar(32) NOT NULL DEFAULT '' AFTER relation_role") === false
) {
    fwrite(STDERR, "Hub 0.8.0 editorial role schema contract missing\n");
    exit(1);
}

$relationsAdminSource = file_get_contents(dirname(__DIR__) . '/admin/relations.php');
if (strpos($relationsAdminSource, 'name="editorial_role"') === false
    || strpos($relationsAdminSource, 'HUB_editorialRoles()') === false
) {
    fwrite(STDERR, "Hub editorial role administration contract missing\n");
    exit(1);
}
