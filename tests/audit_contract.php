<?php
$root = dirname(__DIR__);
$required = array('config.php', 'autoinstall.php', 'functions.inc', 'lib-audit.php', 'lib-role.php', 'admin/index.php', 'admin/audit.php', 'ROADMAP.md');
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
if (strpos($admin, 'Export audit as Markdown') === false || strpos($admin, '<th>Role</th>') === false) {
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

// Lifecycle tokenizer regression: strings/comments that mention lifecycle functions
// must not be reported as calls, while real calls must be detected.
require_once $root . '/lib-audit.php';

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

echo "Hub packaging contract OK\n";
