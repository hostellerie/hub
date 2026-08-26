<?php
$root = dirname(__DIR__);
$required = array('config.php', 'autoinstall.php', 'functions.inc', 'lib-audit.php', 'lib-role.php', 'lib-stats.php', 'admin/index.php', 'admin/audit.php', 'ROADMAP.md');
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

require_once $root . '/lib-audit.php';

foreach (array('HUB_auditLifecycleCallsFromSource', 'HUB_auditLifecycleContractDetails', 'HUB_auditAdditionalCapabilities') as $needle) {
    if (!function_exists($needle)) {
        fwrite(STDERR, "Missing finalized audit capability: $needle\n");
        exit(1);
    }
}

function plugin_itemsaved_hubtest($id, $type, $old_id, $sub_type) {}
function plugin_itemdeleted_hubtest($id, $type, $sub_type) {}
function plugin_idtourl_hubtest($sub_type, $item_id) {}
function plugin_getlanguageoverrides_hubtest() {}

$contractDetails = HUB_auditLifecycleContractDetails('hubtest');
if (strpos(implode(' ', $contractDetails), 'sub_type-aware') === false) {
    fwrite(STDERR, "sub_type lifecycle contract detection failed\n");
    exit(1);
}
$additional = HUB_auditAdditionalCapabilities('hubtest');
if (strpos(implode(' ', $additional), 'Language overrides') === false) {
    fwrite(STDERR, "Additional Geeklog capability detection failed\n");
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
if (!$realFacts['item_saved'] || !$realFacts['item_deleted'] || $realFacts['object_types'] !== array('article')) {
    fwrite(STDERR, "Lifecycle tokenizer real-call regression failed\n");
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

echo "Hub packaging contract OK\n";
