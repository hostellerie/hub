<?php
$root = dirname(__DIR__);
$required = array('config.php', 'autoinstall.php', 'functions.inc', 'lib-audit.php', 'admin/index.php', 'ROADMAP.md');
foreach ($required as $file) {
    if (!file_exists($root . '/' . $file)) {
        fwrite(STDERR, "Missing: $file\n");
        exit(1);
    }
}

$lib = file_get_contents($root . '/lib-audit.php');
$admin = file_get_contents($root . '/admin/index.php');
$needles = array('HUB_auditSourceFacts', 'PLG_itemSaved', 'PLG_itemDeleted', 'HUB_auditFunctionSignature', 'HUB_auditPluginApiSurface');
foreach ($needles as $needle) {
    if (strpos($lib, $needle) === false) {
        fwrite(STDERR, "Missing audit capability: $needle\n");
        exit(1);
    }
}
if (strpos($admin, 'Source evidence') === false || strpos($admin, 'Object types') === false) {
    fwrite(STDERR, "Missing enriched audit UI\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $extension = strtolower($fileInfo->getExtension());
    if ($extension !== 'php' && $extension !== 'inc') {
        continue;
    }
    $path = $fileInfo->getPathname();
    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read PHP source: $path\n");
        exit(1);
    }
    if (substr($contents, 0, 3) === "\xEF\xBB\xBF") {
        fwrite(STDERR, "UTF-8 BOM detected: $path\n");
        exit(1);
    }
    if (substr($contents, 0, 5) !== '<?php') {
        fwrite(STDERR, "Leading output or missing PHP open tag: $path\n");
        exit(1);
    }
}

echo "Hub packaging contract OK\n";
