<?php
$root = dirname(__DIR__);
$required = array('config.php', 'autoinstall.php', 'functions.inc', 'lib-audit.php', 'lib-capabilities.php', 'lib-services.php', 'lib-role.php', 'lib-stats.php', 'lib-distribution.php', 'lib-link-audit.php', 'lib-staticpages.php', 'public_html/hub.css', 'admin/index.php', 'admin/audit.php', 'admin/link-audit.php', 'ROADMAP.md');
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
if (strpos($admin, 'Export audit as Markdown') === false || strpos($admin, '<th>Role</th>') === false || strpos($admin, 'ID to URL') === false || strpos($admin, 'Additional Geeklog capabilities') === false) {
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

require_once $root . '/lib-capabilities.php';

function plugin_getcapabilities_hubcaptest()
{
    return array(
        'schema' => 1,
        'roles' => array('service', 'content', 'content'),
        'capabilities' => array('content.read', 'dashboard.summary', 'content.read'),
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

$capabilityDeclaration = HUB_capabilityDeclaration('hubcaptest');
if (empty($capabilityDeclaration['valid'])
    || $capabilityDeclaration['schema'] !== 1
    || $capabilityDeclaration['roles'] !== array('content', 'service')
    || $capabilityDeclaration['capabilities'] !== array('content.read', 'dashboard.summary')
) {
    fwrite(STDERR, "Generic capability declaration validation failed\n");
    exit(1);
}

$invalidCapabilityDeclaration = HUB_capabilityDeclaration('hubcapinvalid');
if (!empty($invalidCapabilityDeclaration['valid']) || empty($invalidCapabilityDeclaration['errors'])) {
    fwrite(STDERR, "Invalid generic capability declaration was accepted\n");
    exit(1);
}
$hubFunctionsSource = file_get_contents($root . '/functions.inc');
if (strpos($hubFunctionsSource, 'function plugin_getcapabilities_hub()') === false
    || strpos($hubFunctionsSource, "'orchestrator'") === false
    || strpos($hubFunctionsSource, "'hub.capabilities.discover'") === false
) {
    fwrite(STDERR, "Hub generic capability declaration is missing from functions.inc\n");
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

require_once $root . '/lib-audit.php';

foreach (array('HUB_auditLifecycleCallsFromSource', 'HUB_auditLifecycleListenerDetails', 'HUB_auditPluginApiSurface') as $needle) {
    if (!function_exists($needle)) {
        fwrite(STDERR, "Missing finalized audit capability: $needle\n");
        exit(1);
    }
}

function plugin_itemsaved_hubtest($id, $type, $old_id, $sub_type) {}
function plugin_itemdeleted_hubtest($id, $type, $sub_type) {}
function plugin_idtourl_hubtest($sub_type, $item_id) {}
function plugin_getlanguageoverrides_hubtest() {}

$listenerDetails = HUB_auditLifecycleListenerDetails('hubtest');
$listenerText = implode(' ', $listenerDetails);
if (strpos($listenerText, 'plugin_itemsaved_hubtest') === false
    || strpos($listenerText, '$sub_type') === false
    || strpos($listenerText, 'plugin_itemdeleted_hubtest') === false
) {
    fwrite(STDERR, "Lifecycle listener signature detection failed\n");
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

require_once $root . '/lib-link-audit.php';

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

echo "Hub packaging contract OK\n";
