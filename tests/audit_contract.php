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
echo "Hub packaging contract OK\n";
