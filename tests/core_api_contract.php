<?php

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tests/core_api_contract.php <lib-plugins.php> <legacy|modern>\n");
    exit(2);
}

$file = $argv[1];
$mode = $argv[2];

if (!is_file($file)) {
    fwrite(STDERR, "FAIL: Geeklog Core lib-plugins.php not found: " . $file . PHP_EOL);
    exit(1);
}

$source = file_get_contents($file);

function hubCoreApiAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

hubCoreApiAssert(
    strpos($source, 'function PLG_getItemInfo($type, $id, $what, $uid = 0, $options = array())') !== false,
    'Geeklog Core exposes Item Info with options'
);
hubCoreApiAssert(
    strpos($source, 'function PLG_itemDisplay($id, $type)') !== false,
    'Geeklog Core exposes generic item display'
);
hubCoreApiAssert(
    strpos($source, 'function PLG_invokeService($type, $action, $args, &$output, &$svc_msg)') !== false,
    'Geeklog Core exposes plugin services'
);

if ($mode === 'legacy') {
    hubCoreApiAssert(
        strpos($source, "function PLG_itemSaved(\$id, \$type, \$old_id = '')") !== false,
        'legacy Core lifecycle save signature is available'
    );
    hubCoreApiAssert(
        strpos($source, 'function PLG_itemDeleted($id, $type)') !== false,
        'legacy Core lifecycle delete signature is available'
    );
} elseif ($mode === 'modern') {
    hubCoreApiAssert(
        strpos($source, "function PLG_itemSaved(\$id, \$type, \$old_id = '', \$sub_type = '')") !== false,
        'modern Core lifecycle save signature includes subtype'
    );
    hubCoreApiAssert(
        strpos($source, "function PLG_itemDeleted(\$id, \$type, \$sub_type = '')") !== false,
        'modern Core lifecycle delete signature includes subtype'
    );
} else {
    fwrite(STDERR, "FAIL: unknown Core API mode: " . $mode . PHP_EOL);
    exit(2);
}

$hubFunctions = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubCoreApiAssert(
    strpos($hubFunctions, "function plugin_itemsaved_hub(\$id, \$type, \$old_id = '', \$sub_type = '')") !== false,
    'Hub listener accepts both legacy and modern save calls'
);
hubCoreApiAssert(
    strpos($hubFunctions, "function plugin_itemdeleted_hub(\$id, \$type, \$sub_type = '')") !== false,
    'Hub listener accepts both legacy and modern delete calls'
);

echo "Hub Geeklog Core API contract passed for " . $mode . "." . PHP_EOL;
