<?php
$root = dirname(__DIR__);
$required = array('config.php', 'autoinstall.php', 'functions.inc', 'lib-audit.php', 'lib-capabilities.php', 'lib-capability-evidence.php', 'lib-content-contract.php', 'lib-metadata.php', 'lib-services.php', 'lib-render.php', 'lib-role.php', 'lib-stats.php', 'lib-audit-cache.php', 'lib-distribution.php', 'lib-link-audit.php', 'lib-staticpages.php', 'lib-relations.php', 'lib-site-context.php', 'install_updates.php', 'sql/mysql_install.php', 'plugin.json', 'public_html/hub.css', 'admin/index.php', 'admin/audit.php', 'admin/link-audit.php', 'admin/relations.php', 'admin/integrity.php', 'ROADMAP.md');
foreach ($required as $file) {
    if (!file_exists($root . '/' . $file)) {
        fwrite(STDERR, "Missing: $file\n");
        exit(1);
    }
}
$role = file_get_contents($root . '/lib-role.php');
$admin = file_get_contents($root . '/admin/audit.php');
foreach (array('HUB_roleInfer', 'HUB_roleReadiness', 'HUB_roleEnrichRows', 'HUB_roleMarkdown') as $needle) {
    if (strpos($role, $needle) === false) {
        fwrite(STDERR, "Missing role-aware capability: $needle\n");
        exit(1);
    }
}
if (strpos($admin, 'Export audit as Markdown') === false || strpos($admin, '<th>Role</th>') === false || strpos($admin, 'Primary role') === false || strpos($admin, 'ID to URL') === false || strpos($admin, 'Extended Geeklog integration') === false || strpos($admin, 'Capability implementation evidence') === false || strpos($admin, 'Shared content contract evidence') === false || strpos($admin, 'Modernization metadata') === false) {
    fwrite(STDERR, "Missing role-aware/export audit UI\n");
    exit(1);
}
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $extension = strtolower($fileInfo->getExtension());
    if ($extension !== 'php' && $extension !== 'inc') {
        continue;
    }
    $contents = file_get_contents($fileInfo->getPathname());
    if ($contents === false || substr($contents, 0, 3) === "\xEF\xBB\xBF" || substr($contents, 0, 5) !== '<?php') {
        fwrite(STDERR, "Unsafe PHP source: " . $fileInfo->getPathname() . "\n");
        exit(1);
    }
}


$readme = file_get_contents($root . '/README.md');
$roadmap = file_get_contents($root . '/ROADMAP.md');
$libAudit = file_get_contents($root . '/lib-audit.php');
$configSource = file_get_contents($root . '/config.php');
$autoinstallSource = file_get_contents($root . '/autoinstall.php');
$functionsSource = file_get_contents($root . '/functions.inc');
$sqlSource = file_get_contents($root . '/sql/mysql_install.php');
$upgradeSource = file_get_contents($root . '/install_updates.php');
$relationsSource = file_get_contents($root . '/lib-relations.php');
$relationsApiSource = file_get_contents($root . '/lib-relations.php');
if (strpos($relationsApiSource, "function HUB_savePillar(\$pillarId, \$sourceType, \$sourceId, \$isEnabled = 1, \$ownerId = 0, \$editorialRole = '')") === false
    || strpos($relationsApiSource, '$titleOverride') !== false
) {
    fwrite(STDERR, "Obsolete pillar title override API still present\n");
    exit(1);
}

$relationsAdminSource = file_get_contents($root . '/admin/relations.php');

if (strpos($configSource, "'pi_version'    => '0.10.0'") === false
    || strpos($autoinstallSource, "'hub_pillars'") === false
    || strpos($autoinstallSource, "'hub_relations'") === false
    || strpos($functionsSource, 'HUB_updateSchema_0_3_0') === false
    || strpos($functionsSource, 'HUB_updateSchema_0_5_0') === false
    || strpos($functionsSource, "'hub_pillars', 'hub_relations'") === false
    || strpos($sqlSource, "CREATE TABLE {\$_TABLES['hub_pillars']}") === false
    || strpos($sqlSource, "CREATE TABLE {\$_TABLES['hub_relations']}") === false
    || strpos($upgradeSource, 'CREATE TABLE IF NOT EXISTS') === false
) {
    fwrite(STDERR, "Hub 0.10.0 storage contract missing\n");
    exit(1);
}

if (strpos($sqlSource, 'source_url') !== false
    || strpos($sqlSource, 'item_url') !== false
    || strpos($relationsSource, 'source_url') !== false
    || strpos($relationsSource, 'item_url') !== false
) {
    fwrite(STDERR, "Hub relationship storage must not persist canonical URLs\n");
    exit(1);
}

if (strpos($relationsAdminSource, '<details class="hub-rel-card hub-rel-pillar">') === false
    || strpos($relationsAdminSource, 'details.hub-rel-pillar') === false
    || strpos($relationsAdminSource, 'pillars[p].open=false') === false
) {
    fwrite(STDERR, "Hub collapsed pillar administration contract missing\n");
    exit(1);
}

if (strpos($relationsSource, 'function HUB_invalidateRelationshipCaches') === false
    || strpos($relationsSource, "CACHE_remove_instance('article__'") === false
    || strpos($relationsSource, "CACHE_remove_instance('staticpage__'") === false
) {
    fwrite(STDERR, "Hub relationship cache invalidation contract missing\n");
    exit(1);
}

if (strpos($relationsSource, 'function HUB_savePillar') === false
    || strpos($relationsSource, 'function HUB_saveRelation') === false
    || strpos($relationsSource, 'function HUB_relationCoreArticleOptions') === false
    || strpos($relationsSource, "if (\$type === 'article')") === false
    || strpos($relationsSource, 'function HUB_resolveObject') === false
    || strpos($relationsSource, 'PLG_getItemInfo') === false
    || strpos($relationsAdminSource, 'Pillars &amp; manual relations') === false
    || strpos($relationsAdminSource, 'save_relation') === false
) {
    fwrite(STDERR, "Hub relationship API/admin contract missing\n");
    exit(1);
}

if (strpos($relationsSource, 'function HUB_renderPillarRelations') === false
    || strpos($relationsSource, 'function HUB_renderItemPillarBacklinks') === false
    || strpos($functionsSource, 'function plugin_itemdisplay_hub') === false
    || strpos($functionsSource, "story_display_type") === false
    || strpos($functionsSource, "plugin_itemdisplay") === false
    || strpos($functionsSource, "featuredarticle") === false
    || strpos($functionsSource, "archivearticle") === false
    || strpos($functionsSource, "hub-pillar-backlinks") === false
    || strpos($functionsSource, "HUB_renderItemPillarBacklinks('article', \$storyId)") === false
    || strpos($functionsSource, "HUB_renderPillarRelations('staticpages', \$pageId)") === false
    || strpos($readme, 'Public relationship navigation (0.4.0)') === false
) {
    fwrite(STDERR, "Hub 0.4.0 public relationship navigation contract missing\n");
    exit(1);
}

require_once $root . '/lib-relations.php';
if (HUB_normalizeObjectType('Maps.Marker') !== 'mapsmarker'
    || HUB_normalizeObjectType('STATICPAGES') !== 'staticpages'
    || HUB_normalizeObjectId('  page-1  ') !== 'page-1'
) {
    fwrite(STDERR, "Hub relationship identity normalization failed\n");
    exit(1);
}


if (strpos($readme, 'selected topic URL') !== false) {
    fwrite(STDERR, "README still contains obsolete selected-topic wording\n");
    exit(1);
}
if (strpos($roadmap, 'select a Geeklog topic') !== false) {
    fwrite(STDERR, "Roadmap still requires obsolete manual topic selection\n");
    exit(1);
}
if (strpos($libAudit, 'current 0.1.0 audit milestone') !== false) {
    fwrite(STDERR, "Hub self-audit still reports the obsolete 0.1.0 milestone\n");
    exit(1);
}
if (strpos($readme, 'Shared Memorandum alignment') === false
    || strpos($readme, 'Pillars and manual relations (finalized in 0.3.0)') === false
    || strpos($roadmap, '0.2.0 — Generic capability discovery — completed') === false
    || strpos($roadmap, 'Current development milestone:** `0.10.0`') === false
    || strpos($roadmap, 'hub.context.read') === false
    || strpos($roadmap, 'Agent is the provider-neutral Geeklog machine access layer') === false
) {
    fwrite(STDERR, "README is not aligned with Memorandum contracts\n");
    exit(1);
}

require_once $root . '/lib-capabilities.php';

function plugin_getcapabilities_hubcaptest()
{
    return array(
        'schema' => 1,
        'roles' => array('service', 'content', 'content'),
        'capabilities' => array('content.read', 'dashboard.summary', 'content.read'),
        'lifecycle' => array(
            'emits' => array('item.saved', 'item.deleted', 'item.saved'),
            'listens' => array('item.saved'),
            'sub_type' => true,
        ),
    );
}

function plugin_getcapabilities_hubcapinvalid()
{
    return array(
        'schema' => 0,
        'roles' => 'content',
        'capabilities' => array(),
    );
}

function plugin_getcapabilities_hubcapbadlifecycle()
{
    return array(
        'schema' => 1,
        'roles' => array('content'),
        'capabilities' => array('content.lifecycle'),
        'lifecycle' => array(
            'emits' => 'item.saved',
            'sub_type' => 'yes',
        ),
    );
}

$capabilityDeclaration = HUB_capabilityDeclaration('hubcaptest');
if (empty($capabilityDeclaration['valid'])
    || $capabilityDeclaration['schema'] !== 1
    || $capabilityDeclaration['roles'] !== array('content', 'service')
    || $capabilityDeclaration['capabilities'] !== array('content.read', 'dashboard.summary')
    || $capabilityDeclaration['lifecycle']['emits'] !== array('item.deleted', 'item.saved')
    || $capabilityDeclaration['lifecycle']['listens'] !== array('item.saved')
    || $capabilityDeclaration['lifecycle']['sub_type'] !== true
) {
    fwrite(STDERR, "Generic capability declaration validation failed\n");
    exit(1);
}


if (!HUB_capabilitySupports('hubcaptest', 'content.read')
    || HUB_capabilitySupports('hubcaptest', 'missing.capability')
    || HUB_capabilityDeclaredRoles('hubcaptest') !== array('content', 'service')
) {
    fwrite(STDERR, "Capability lookup helper failed\n");
    exit(1);
}

$declaredLifecycle = HUB_capabilityDeclaredLifecycle('hubcaptest');
if ($declaredLifecycle['emits'] !== array('item.deleted', 'item.saved')
    || $declaredLifecycle['listens'] !== array('item.saved')
    || $declaredLifecycle['sub_type'] !== true
) {
    fwrite(STDERR, "Declared lifecycle helper failed\n");
    exit(1);
}

$invalidCapabilityDeclaration = HUB_capabilityDeclaration('hubcapinvalid');
if (!empty($invalidCapabilityDeclaration['valid']) || empty($invalidCapabilityDeclaration['errors'])) {
    fwrite(STDERR, "Invalid generic capability declaration was accepted\n");
    exit(1);
}
$badLifecycleDeclaration = HUB_capabilityDeclaration('hubcapbadlifecycle');
if (!empty($badLifecycleDeclaration['valid'])
    || strpos(implode(' ', $badLifecycleDeclaration['errors']), 'lifecycle.emits must be an array') === false
    || strpos(implode(' ', $badLifecycleDeclaration['errors']), 'lifecycle.sub_type must be boolean') === false
) {
    fwrite(STDERR, "Invalid lifecycle declaration was accepted\n");
    exit(1);
}

$hubAuditSource = file_get_contents($root . '/lib-audit.php');
$hubAuditAdminSource = file_get_contents($root . '/admin/audit.php');
if (strpos($hubAuditSource, 'function HUB_auditItemDisplayCallsFromSource') === false
    || strpos($hubAuditSource, 'item_display_provider') === false
    || strpos($hubAuditSource, 'item_display_consumer') === false
    || strpos($hubAuditAdminSource, '<th>Display point</th>') === false
    || strpos($hubAuditAdminSource, '<th>Display callback</th>') === false
    || strpos($hubAuditAdminSource, 'Provider placement') === false
    || strpos($hubAuditAdminSource, 'Consumer callback') === false
) {
    fwrite(STDERR, "ItemDisplay provider/consumer audit contract missing\n");
    exit(1);
}

$hubFunctionsSource = file_get_contents($root . '/functions.inc');
if (strpos($hubFunctionsSource, 'function plugin_getcapabilities_hub()') === false
    || strpos($hubFunctionsSource, "'relationship'") === false
    || strpos($hubFunctionsSource, "'orchestrator'") === false
    || strpos($hubFunctionsSource, "'service'") === false
) {
    fwrite(STDERR, "Hub shared role declaration is missing from functions.inc\n");
    exit(1);
}
foreach (array(
    'hub.capabilities.discover',
    'hub.interoperability.audit',
    'hub.links.audit',
    'hub.staticpages.topics.render'
) as $obsoleteHubCapability) {
    if (strpos($hubFunctionsSource, $obsoleteHubCapability) !== false) {
        fwrite(STDERR, "Obsolete Hub-only capability still declared: $obsoleteHubCapability\n");
        exit(1);
    }
}
$invalidDeclarationRecommendations = HUB_capabilityDeclarationRecommendations('hubcapinvalid');
if (empty($invalidDeclarationRecommendations)
    || !empty(HUB_capabilityDeclarationRecommendations('hubcaptest'))
) {
    fwrite(STDERR, "Invalid capability declaration recommendation failed\n");
    exit(1);
}



require_once $root . '/lib-services.php';

function plugin_wsEnabled_hubservicetest()
{
    return true;
}

function service_dashboard_summary_hubservicetest($args, &$output, &$svc_msg)
{
}

function service_item_read_hubservicetest($args, &$output, &$svc_msg)
{
}

$serviceCatalogue = HUB_serviceCatalogue('hubservicetest');
if (empty($serviceCatalogue['dispatcher'])
    || count($serviceCatalogue['actions']) !== 2
    || $serviceCatalogue['actions'][0]['action'] !== 'dashboard_summary'
    || $serviceCatalogue['actions'][1]['action'] !== 'item_read'
) {
    fwrite(STDERR, "Reusable service catalogue detection failed\n");
    exit(1);
}
if (!HUB_serviceHasAction('hubservicetest', 'dashboard_summary')
    || !HUB_serviceHasAction('hubservicetest', 'item_read')
    || HUB_serviceHasAction('hubservicetest', 'missing_action')
) {
    fwrite(STDERR, "Service action lookup helper failed\n");
    exit(1);
}


require_once $root . '/lib-capability-evidence.php';

$capEvidenceGood = HUB_capabilityImplementationEvidence(
    'example',
    array(
        'valid' => true,
        'capabilities' => array('content.read', 'content.url.resolve', 'dashboard.summary'),
    ),
    array(
        'item_info' => true,
        'related_items' => false,
        'id_to_url' => true,
        'blocks' => false,
        'autotags' => false,
        'search' => false,
        'services' => true,
    ),
    array(
        'item_saved' => array(),
        'item_deleted' => array(),
    ),
    array(
        'actions' => array(
            array('action' => 'dashboard_summary', 'function' => 'service_dashboard_summary_example', 'signature' => 'service_dashboard_summary_example(...)'),
        ),
    ),
    array(
        'collection_source' => false,
    )
);
$capEvidenceGoodText = implode(' ', $capEvidenceGood['details']);
if (strpos($capEvidenceGoodText, 'content.read') === false
    || strpos($capEvidenceGoodText, 'dashboard.summary') === false
    || !empty($capEvidenceGood['recommendations'])
) {
    fwrite(STDERR, "Capability implementation evidence reconciliation failed\n");
    exit(1);
}

$capEvidenceMissing = HUB_capabilityImplementationEvidence(
    'broken',
    array(
        'valid' => true,
        'capabilities' => array('content.read', 'content.lifecycle', 'dashboard.summary'),
    ),
    array(
        'item_info' => false,
        'related_items' => false,
        'id_to_url' => false,
        'blocks' => false,
        'autotags' => false,
        'search' => false,
        'services' => false,
    ),
    array(
        'item_saved' => array(),
        'item_deleted' => array(),
    ),
    array('actions' => array()),
    array('collection_source' => false)
);
if (count($capEvidenceMissing['recommendations']) < 3) {
    fwrite(STDERR, "Missing declared capability evidence did not produce recommendations\n");
    exit(1);
}

require_once $root . '/lib-content-contract.php';
require_once $root . '/lib-metadata.php';

function plugin_getiteminfo_hubcollectiontest($id, $what, $uid = 0, $options = array())
{
    if ($id === '*') {
        $since = isset($options['since']) ? $options['since'] : 0;
        $limit = isset($options['limit']) ? $options['limit'] : 20;
        $order = isset($options['order']) ? $options['order'] : 'modified-desc';
        if ($order === 'hits-desc') {
            return array(array('id' => '1', 'hits' => 10));
        }
        return array();
    }
    return array('id' => $id, 'hits' => 1);
}

$contentEvidence = HUB_contentContractEvidence('hubcollectiontest');
if (empty($contentEvidence['collection_source'])
    || empty($contentEvidence['since_source'])
    || empty($contentEvidence['limit_source'])
    || empty($contentEvidence['order_source'])
    || empty($contentEvidence['hits_source'])
    || empty($contentEvidence['hits_desc_source'])
) {
    fwrite(STDERR, "Memorandum content-contract evidence failed\n");
    exit(1);
}

$hubManifest = json_decode(file_get_contents($root . '/plugin.json'), true);
if (!is_array($hubManifest)
    || !isset($hubManifest['schema']) || (int) $hubManifest['schema'] !== 1
    || !isset($hubManifest['id']) || $hubManifest['id'] !== 'hub'
    || !isset($hubManifest['name']) || $hubManifest['name'] !== 'Hub'
    || empty($hubManifest['requires']['geeklog'])
    || empty($hubManifest['requires']['php'])
) {
    fwrite(STDERR, "Hub plugin.json metadata manifest is invalid\n");
    exit(1);
}

require_once $root . '/lib-audit.php';

foreach (array('HUB_auditLifecycleCallsFromSource', 'HUB_auditLifecycleListenerDetails', 'HUB_auditLifecycleContractDetails', 'HUB_auditAdditionalCapabilities', 'HUB_auditPluginApiSurface') as $needle) {
    if (!function_exists($needle)) {
        fwrite(STDERR, "Missing finalized audit capability: $needle\n");
        exit(1);
    }
}

function plugin_itemsaved_hubtest($id, $type, $old_id, $sub_type) {}
function plugin_itemdeleted_hubtest($id, $type, $sub_type) {}
function plugin_idtourl_hubtest($sub_type, $item_id) {}
function plugin_getlanguageoverrides_hubtest() {}
function plugin_itemsaved_hublegacy($id, $type, $old_id) {}
function plugin_itemdeleted_hublegacy($id, $type) {}

$listenerDetails = HUB_auditLifecycleListenerDetails('hubtest');
$listenerText = implode(' ', $listenerDetails);
if (strpos($listenerText, 'plugin_itemsaved_hubtest') === false
    || strpos($listenerText, '$sub_type') === false
    || strpos($listenerText, 'plugin_itemdeleted_hubtest') === false
) {
    fwrite(STDERR, "Lifecycle listener signature detection failed\n");
    exit(1);
}

$contractDetails = HUB_auditLifecycleContractDetails('hubtest');
$legacyContractDetails = HUB_auditLifecycleContractDetails('hublegacy');
if (strpos(implode(' ', $contractDetails), 'sub_type-aware') === false
    || strpos(implode(' ', $legacyContractDetails), 'legacy/no sub_type') === false
) {
    fwrite(STDERR, "Lifecycle contract compatibility detection failed\n");
    exit(1);
}

$capabilityDetails = HUB_auditCapabilityDetails('hubtest', array(), array());
if (empty($capabilityDetails['id_to_url'])
    || strpos(implode(' ', $capabilityDetails['id_to_url']), 'plugin_idtourl_hubtest') === false
) {
    fwrite(STDERR, "ID to URL capability detection failed\n");
    exit(1);
}

$additionalCapabilities = HUB_auditAdditionalCapabilities('hubtest');
if (strpos(implode(' ', $additionalCapabilities), 'Language overrides') === false) {
    fwrite(STDERR, "Additional Geeklog capability detection failed\n");
    exit(1);
}

$apiSurface = HUB_auditPluginApiSurface('hubtest');
if (strpos(implode(' ', $apiSurface), 'plugin_getlanguageoverrides_hubtest') === false) {
    fwrite(STDERR, "Plugin API surface detection failed\n");
    exit(1);
}

$fakeSource = <<<'PHP'
<?php
$fake = 'PLG_itemSaved($id, "fake")';
// PLG_itemDeleted($id, 'commented');
preg_match('/PLG_itemSaved\s*\(/', $fake);
PHP;
$fakeFacts = HUB_auditLifecycleCallsFromSource($fakeSource);
if ($fakeFacts['item_saved'] || $fakeFacts['item_deleted'] || !empty($fakeFacts['object_types'])) {
    fwrite(STDERR, "Lifecycle tokenizer false-positive regression failed\n");
    exit(1);
}

$realSource = <<<'PHP'
<?php
PLG_itemSaved($id, 'article');
PLG_itemDeleted($id, "article");
PHP;
$realFacts = HUB_auditLifecycleCallsFromSource($realSource);
if (!$realFacts['item_saved'] || !$realFacts['item_deleted']
    || $realFacts['object_types'] !== array('article')
) {
    fwrite(STDERR, "Lifecycle tokenizer real-call regression failed\n");
    exit(1);
}

$itemDisplayFakeSource = <<<'PHP'
<?php
$fake = "PLG_itemDisplay($id, 'videos')";
 // PLG_itemDisplay($id, 'documents');
function PLG_itemDisplay($id, $type) {}
PHP;
$itemDisplayFakeFacts = HUB_auditItemDisplayCallsFromSource($itemDisplayFakeSource);
if (!empty($itemDisplayFakeFacts['found']) || !empty($itemDisplayFakeFacts['object_types'])) {
    fwrite(STDERR, "ItemDisplay tokenizer false-positive regression failed\n");
    exit(1);
}

$itemDisplayRealSource = <<<'PHP'
<?php
$extra = PLG_itemDisplay($videoId, 'videos');
$dynamic = PLG_itemDisplay($documentId, $type);
PHP;
$itemDisplayRealFacts = HUB_auditItemDisplayCallsFromSource($itemDisplayRealSource);
if (empty($itemDisplayRealFacts['found'])
    || $itemDisplayRealFacts['object_types'] !== array('videos')
) {
    fwrite(STDERR, "ItemDisplay tokenizer real-call regression failed\n");
    exit(1);
}

$functionsSource = file_get_contents($root . '/functions.inc');
if (strpos($functionsSource, 'function plugin_geticon_hub()') === false
    || strpos($functionsSource, 'function plugin_cclabel_hub()') === false
    || strpos($functionsSource, "plugin_geticon_hub(),") === false
    || strpos($functionsSource, "'plugins'") === false
) {
    fwrite(STDERR, "Hub Command & Control integration contract missing\n");
    exit(1);
}

$sourceFilesFunction = file_get_contents($root . '/lib-audit.php');
if (strpos($sourceFilesFunction, "\$maxFiles = 800;") === false
    || strpos($sourceFilesFunction, "'include'") === false
    || strpos($sourceFilesFunction, "'public_html'") === false
    || strpos($sourceFilesFunction, "'scan_truncated'") === false
    || strpos($sourceFilesFunction, "'source_roots'") === false
    || strpos($sourceFilesFunction, "'item_display_text_fallback'") === false
    || strpos($sourceFilesFunction, "stripos(\$source, 'PLG_itemDisplay(')") === false
    || strpos($sourceFilesFunction, 'function HUB_auditProviderItemDisplayProbe') === false
    || strpos($sourceFilesFunction, "Direct Forum probe: include/viewtopic_core.php") === false
    || strpos($sourceFilesFunction, "Direct Forum candidate:") === false
    || strpos($sourceFilesFunction, "viewtopic_core.php") === false
    || strpos($sourceFilesFunction, "viewtopic.php") === false
) {
    fwrite(STDERR, "Prioritized provider source scan contract missing\n");
    exit(1);
}

require_once $root . '/lib-link-audit.php';

$sortFixture = array(
    array('title' => 'Older popular', 'hits' => 100, 'comments' => 1, 'date' => '2020-01-01 00:00:00', 'hub_topics' => array('a' => 'A')),
    array('title' => 'Discussed', 'hits' => 20, 'comments' => 9, 'date' => '2024-01-01 00:00:00', 'hub_topics' => array('a' => 'A', 'b' => 'B')),
);
$sortedByViews = HUB_linkAuditSortArticles($sortFixture, 'views', 'desc');
$sortedByTopics = HUB_linkAuditSortArticles($sortFixture, 'topics', 'desc');
if ($sortedByViews[0]['title'] !== 'Older popular'
    || $sortedByTopics[0]['title'] !== 'Discussed'
    || HUB_linkAuditArticleAge('2025-01-01 00:00:00', strtotime('2026-01-02 00:00:00')) !== '1 year'
) {
    fwrite(STDERR, "Link audit sorting/age helpers failed\n");
    exit(1);
}

$_CONF = array('site_url' => 'https://example.com');

$target = 'https://example.com/staticpages/index.php?page=page-1';
$equivalentLinks = array(
    'http://www.example.com/staticpages/index.php?page=page-1',
    '/staticpages/index.php?page=page-1',
    'https://example.com/staticpages/index.php?page=page-1#section',
);
foreach ($equivalentLinks as $candidate) {
    if (HUB_linkAuditUrlKey($candidate, $_CONF['site_url']) !== HUB_linkAuditUrlKey($target, $_CONF['site_url'])) {
        fwrite(STDERR, "Link audit URL normalization failed: $candidate\n");
        exit(1);
    }
}

$queryA = 'https://example.com/staticpages/index.php?b=2&a=1';
$queryB = 'https://example.com/staticpages/index.php?a=1&b=2';
if (HUB_linkAuditUrlKey($queryA, $_CONF['site_url']) !== HUB_linkAuditUrlKey($queryB, $_CONF['site_url'])) {
    fwrite(STDERR, "Link audit query-order normalization failed\n");
    exit(1);
}

$html = '<p>See <a href="/staticpages/index.php?page=page-1">the pillar page</a>.</p>';
if (!HUB_linkAuditContainsLink($html, $target)) {
    fwrite(STDERR, "Link audit anchor detection failed\n");
    exit(1);
}
if (HUB_linkAuditContainsLink('<p>https://example.com/staticpages/index.php?page=page-1</p>', $target)) {
    fwrite(STDERR, "Link audit must only detect hyperlinks, not plain URL text\n");
    exit(1);
}

require_once $root . '/lib-role.php';

$diagnosticRole = HUB_roleInfer(array(
    'plugin' => 'monitor',
    'caps' => array(
        'item_info' => false,
        'related_items' => false,
        'id_to_url' => false,
        'blocks' => false,
        'autotags' => false,
        'search' => false,
        'services' => true,
    ),
    'source_facts' => array('item_saved' => array(), 'item_deleted' => array()),
    'object_types' => array(),
    'api_surface' => array(),
    'capability_declaration' => array(
        'valid' => true,
        'roles' => array('diagnostic', 'service'),
    ),
));
if ($diagnosticRole['name'] !== 'diagnostic'
    || $diagnosticRole['source'] !== 'declared'
    || $diagnosticRole['declared_roles'] !== array('diagnostic', 'service')
) {
    fwrite(STDERR, "Declared diagnostic role precedence failed\n");
    exit(1);
}
$diagnosticReadiness = HUB_roleReadiness(array(
    'plugin' => 'monitor',
    'caps' => array('services' => true),
    'source_facts' => array(),
), $diagnosticRole);
if ($diagnosticReadiness['label'] !== 'Role OK') {
    fwrite(STDERR, "Diagnostic role readiness must not use content scoring\n");
    exit(1);
}

$contentRole = HUB_roleInfer(array(
    'plugin' => 'videos',
    'caps' => array(
        'item_info' => true,
        'related_items' => false,
        'id_to_url' => false,
        'blocks' => false,
        'autotags' => false,
        'search' => false,
        'services' => true,
    ),
    'source_facts' => array('item_saved' => array('functions.inc'), 'item_deleted' => array()),
    'object_types' => array(),
    'api_surface' => array(),
    'capability_declaration' => array(
        'valid' => true,
        'roles' => array('content', 'service'),
    ),
));
if ($contentRole['name'] !== 'content'
    || $contentRole['source'] !== 'declared+inferred'
) {
    fwrite(STDERR, "Content evidence must remain primary over generic service role\n");
    exit(1);
}

$hubRole = HUB_roleInfer(array(
    'plugin' => 'hub',
    'caps' => array(
        'item_info' => false,
        'related_items' => false,
        'id_to_url' => false,
        'blocks' => false,
        'autotags' => false,
        'search' => false,
        'services' => false,
    ),
    'source_facts' => array('item_saved' => array(), 'item_deleted' => array()),
    'object_types' => array(),
    'api_surface' => array(),
    'capability_declaration' => array(
        'valid' => true,
        'roles' => array('orchestrator', 'relationship', 'service'),
    ),
));
if ($hubRole['name'] !== 'orchestrator'
    || $hubRole['declared_roles'] !== array('orchestrator', 'relationship', 'service')
) {
    fwrite(STDERR, "Hub shared role declaration precedence failed\n");
    exit(1);
}

$siteAwareMarkdown = HUB_roleMarkdown(array(), '2.2.2', '8.1.0', '0.3.0', 'Ecologie Pratique');
if (strpos($siteAwareMarkdown, '# Ecologie Pratique — Plugin Interoperability Audit') === false
    || strpos($siteAwareMarkdown, '- Site: `Ecologie Pratique`') === false
) {
    fwrite(STDERR, "Site-aware Markdown export failed\n");
    exit(1);
}

require_once $root . '/lib-stats.php';
function plugin_showstats_hubstatstest($showsitestats) {}
function plugin_statssummary_hubstatstest() {}
$stats = HUB_statsPluginCapabilities('hubstatstest');
if (strpos(implode(' ', $stats), 'Statistics: Full') === false
    || strpos(implode(' ', $stats), 'Statistics page contribution') === false
    || strpos(implode(' ', $stats), 'Statistics summary') === false
) {
    fwrite(STDERR, "Statistics capability detection failed\n");
    exit(1);
}

require_once $root . '/lib-distribution.php';

function plugin_getfeednames_hubdistributiontest() {}
function plugin_getfeedcontent_hubdistributiontest($feed, &$link, &$update_data, $feedType, $feedVersion) {}
function plugin_feedupdatecheck_hubdistributiontest($feed, $topic, $update_data, $limit, $updated_type, $updated_topic, $updated_id) {}
function plugin_collectSitemapItems_hubdistributiontest($uid, $limit) {}

$syndication = HUB_distributionSyndicationCapabilities('hubdistributiontest');
$dummyRow = array('caps' => array('item_info' => true));
$sitemap = HUB_distributionSitemapCapabilities('hubdistributiontest', $dummyRow);
$fallback = HUB_distributionSitemapCapabilities('hubfallbacktest', $dummyRow);

if (strpos(implode(' ', $syndication), 'Content Syndication: Full') === false
    || strpos(implode(' ', $syndication), 'Feed update check') === false
    || strpos(implode(' ', $sitemap), 'Native collector') === false
    || strpos(implode(' ', $fallback), 'Item Info fallback') === false
) {
    fwrite(STDERR, "Distribution capability detection failed\n");
    exit(1);
}

$relationsAdminSource = file_get_contents($root . '/admin/relations.php');
$relationsLibSource = file_get_contents($root . '/lib-relations.php');
$newInstallSqlSource = file_get_contents($root . '/sql/mysql_install.php');

if (stripos($relationsAdminSource, 'title override') !== false
    || strpos($newInstallSqlSource, 'title_override') !== false
    || strpos($relationsLibSource, '"title_override = "') !== false
) {
    fwrite(STDERR, "Obsolete pillar title override is still active\n");
    exit(1);
}

$staticPageSource = file_get_contents($root . '/lib-staticpages.php');
$linkAuditLibSource = file_get_contents($root . '/lib-link-audit.php');
$linkAuditSource = file_get_contents($root . '/admin/link-audit.php');
$auditCacheSource = file_get_contents($root . '/lib-audit-cache.php');

if (strpos($linkAuditSource, 'hub-topic-link') === false
    || strpos($linkAuditSource, 'text-overflow:ellipsis') === false
    || strpos($linkAuditSource, 'title="' ) === false
) {
    fwrite(STDERR, "Hub compact topic label contract missing\n");
    exit(1);
}

if (strpos($linkAuditSource, 'hub-audit-table') === false
    || strpos($linkAuditSource, '<th>Engagement</th>') === false
    || strpos($linkAuditSource, '<th>Topics</th>') === false
) {
    fwrite(STDERR, "Hub compact article audit layout missing\n");
    exit(1);
}

if (strpos($linkAuditLibSource, 's.hits') === false
    || strpos($linkAuditLibSource, 's.comments') === false
    || strpos($linkAuditLibSource, 'function HUB_linkAuditArticleAge') === false
    || strpos($linkAuditLibSource, 'function HUB_linkAuditSortArticles') === false
    || strpos($linkAuditSource, '<th>Engagement</th>') === false
    || strpos($linkAuditSource, 'views</span>') === false
    || strpos($linkAuditSource, 'comments</span>') === false
    || strpos($linkAuditSource, 'hub-article-meta') === false
    || strpos($linkAuditSource, 'add_article_relation') === false
    || strpos($linkAuditSource, 'Already related') === false
) {
    fwrite(STDERR, "Hub article link audit prioritization/actions contract missing\n");
    exit(1);
}

if (strpos($staticPageSource, 'function HUB_staticPageTopicContext') === false
    || strpos($staticPageSource, "'placement_only'") === false
    || strpos($linkAuditSource, 'placement options') === false
) {
    fwrite(STDERR, "Static Page placement semantics documentation missing\n");
    exit(1);
}

if (strpos($auditCacheSource, 'CACHE_check_instance') === false
    || strpos($auditCacheSource, 'CACHE_create_instance') === false
    || strpos($auditCacheSource, 'HUB_auditCacheFingerprint') === false
) {
    fwrite(STDERR, "Audit cache contract missing\n");
    exit(1);
}

echo "Hub packaging contract OK\n";


$integrityAdminSource = file_get_contents($root . '/admin/integrity.php');
$adminUiSource = file_get_contents($root . '/lib-admin-ui.php');
if (strpos($integrityAdminSource, 'HUB_integritySummary(0)') === false
    || strpos($integrityAdminSource, 'Backlink integration evidence') === false
    || strpos($integrityAdminSource, 'integration-available') === false
    || strpos($integrityAdminSource, 'runtime backlink output is not asserted') === false
    || strpos($integrityAdminSource, 'Pillar health') === false
    || strpos($integrityAdminSource, 'Integrity issues') === false
    || strpos($integrityAdminSource, 'Content not connected to the Hub graph') === false
    || strpos($integrityAdminSource, 'does <strong>not</strong> mean SEO orphan') === false
    || strpos($integrityAdminSource, 'Sitemap &amp; feed interoperability') === false
    || strpos($integrityAdminSource, 'Cross-site relation context') === false
    || strpos($integrityAdminSource, '<th>Language</th>') === false
    || strpos($integrityAdminSource, 'Equivalent-content context') === false
    || strpos($integrityAdminSource, 'does not infer equivalence or generate hreflang') === false
    || strpos($integrityAdminSource, 'Cross-site is contextual information, not an error by itself') === false
    || strpos($integrityAdminSource, 'Hub does not generate XML Sitemap or feed output') === false
    || strpos($adminUiSource, "'integrity' => array('integrity.php', 'Integrity & cluster health')") === false
) {
    fwrite(STDERR, "Hub integrity administration contract missing\n");
    exit(1);
}
