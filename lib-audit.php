<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

if (!function_exists('HUB_capabilityDeclaration')) {
    require_once __DIR__ . '/lib-capabilities.php';
}
if (!function_exists('HUB_capabilityImplementationEvidence')) {
    require_once __DIR__ . '/lib-capability-evidence.php';
}
if (!function_exists('HUB_serviceCatalogue')) {
    require_once __DIR__ . '/lib-services.php';
}
if (!function_exists('HUB_renderCatalogue')) {
    require_once __DIR__ . '/lib-render.php';
}
if (!function_exists('HUB_contentContractEvidence')) {
    require_once __DIR__ . '/lib-content-contract.php';
}
if (!function_exists('HUB_metadataManifest')) {
    require_once __DIR__ . '/lib-metadata.php';
}

function HUB_auditPluginVersion($plugin)
{
    global $_TABLES;
    $pluginSql = addslashes($plugin);
    $version = DB_getItem($_TABLES['plugins'], 'pi_version', "pi_name = '" . $pluginSql . "'");
    return ($version === false || $version === '') ? '-' : $version;
}

function HUB_auditFunctionExists($prefix, $plugin)
{
    return function_exists($prefix . $plugin);
}

function HUB_auditFunctionSignature($function)
{
    if (!function_exists($function) || !class_exists('ReflectionFunction')) {
        return $function . '()';
    }
    try {
        $reflection = new ReflectionFunction($function);
        $parts = array();
        foreach ($reflection->getParameters() as $parameter) {
            $part = $parameter->isPassedByReference() ? '&' : '';
            $part .= '$' . $parameter->getName();
            if ($parameter->isOptional()) {
                $part .= ' = ...';
            }
            $parts[] = $part;
        }
        return $function . '(' . implode(', ', $parts) . ')';
    } catch (Exception $e) {
        return $function . '()';
    }
}

function HUB_auditRuntimeFunctions($plugin, $prefix)
{
    $matches = array();
    $defined = get_defined_functions();
    if (!isset($defined['user']) || !is_array($defined['user'])) {
        return $matches;
    }
    $suffix = '_' . strtolower($plugin);
    $prefix = strtolower($prefix);
    foreach ($defined['user'] as $function) {
        $lower = strtolower($function);
        if (strpos($lower, $prefix) === 0 && substr($lower, -strlen($suffix)) === $suffix) {
            $matches[] = $function;
        }
    }
    sort($matches);
    return array_values(array_unique($matches));
}

function HUB_auditAutotags($plugin)
{
    $function = 'plugin_autotags_' . $plugin;
    $tags = array();
    if (!function_exists($function)) {
        return $tags;
    }
    $result = call_user_func($function, 'tagname');
    if (is_array($result)) {
        foreach ($result as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }
    }
    return array_values(array_unique($tags));
}

function HUB_auditServiceFunctions($plugin)
{
    $catalogue = HUB_serviceCatalogue($plugin);
    $services = array();

    if (empty($catalogue['actions']) || !is_array($catalogue['actions'])) {
        return $services;
    }

    foreach ($catalogue['actions'] as $service) {
        $services[] = array(
            'name' => isset($service['function']) ? $service['function'] : '',
            'action' => isset($service['action']) ? $service['action'] : '',
            'signature' => isset($service['signature']) ? $service['signature'] : '',
        );
    }

    return $services;
}

function HUB_auditPluginApiSurface($plugin)
{
    $result = array();
    foreach (HUB_auditRuntimeFunctions($plugin, 'plugin_') as $function) {
        $result[] = HUB_auditFunctionSignature($function);
    }
    return $result;
}

function HUB_auditSearchTypes($plugin)
{
    $function = 'plugin_searchtypes_' . $plugin;
    $types = array();
    if (!function_exists($function)) {
        return $types;
    }
    try {
        if (class_exists('ReflectionFunction')) {
            $reflection = new ReflectionFunction($function);
            if ($reflection->getNumberOfRequiredParameters() > 0) {
                return $types;
            }
        }
        $result = call_user_func($function);
    } catch (Exception $e) {
        return $types;
    }
    if (!is_array($result)) {
        return $types;
    }
    foreach ($result as $key => $value) {
        if (is_string($key) && !is_numeric($key) && trim($key) !== '') {
            $types[trim($key)] = array('label' => is_string($value) ? trim($value) : '', 'source' => 'searchtypes');
        } elseif (is_string($value) && trim($value) !== '') {
            $types[trim($value)] = array('label' => '', 'source' => 'searchtypes');
        }
    }
    return $types;
}

function HUB_auditPluginSourceRoots($plugin)
{
    global $_CONF;
    $roots = array();
    $candidates = array();
    if (isset($_CONF['path'])) {
        $candidates[] = rtrim($_CONF['path'], '/\\') . '/plugins/' . $plugin;
    }
    if (isset($_CONF['path_html'])) {
        $html = rtrim($_CONF['path_html'], '/\\');
        $candidates[] = $html . '/' . $plugin;
        $candidates[] = $html . '/admin/plugins/' . $plugin;
    }
    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            $real = realpath($candidate);
            if ($real !== false && !in_array($real, $roots, true)) {
                $roots[] = $real;
            }
        }
    }
    return $roots;
}

function HUB_auditCollectSourceFilesRecursive($dir, &$files, &$count, $maxFiles, $depth)
{
    if ($depth > 8 || $count >= $maxFiles) {
        return;
    }
    $handle = @opendir($dir);
    if ($handle === false) {
        return;
    }
    $skipDirs = array('.', '..', '.git', 'vendor', 'node_modules', 'cache', 'data', 'logs', 'backups', 'tests', 'test', 'docs', 'language', 'templates', 'sql');
    while (($entry = readdir($handle)) !== false && $count < $maxFiles) {
        if (in_array($entry, $skipDirs, true)) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path)) {
            continue;
        }
        if (is_dir($path)) {
            HUB_auditCollectSourceFilesRecursive($path, $files, $count, $maxFiles, $depth + 1);
            continue;
        }
        if (!is_file($path)) {
            continue;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== 'php' && $extension !== 'inc') {
            continue;
        }
        $size = @filesize($path);
        if ($size === false || $size > 524288) {
            continue;
        }
        $files[] = $path;
        $count++;
    }
    closedir($handle);
}

function HUB_auditPluginSourceFiles($plugin)
{
    $files = array();
    $count = 0;
    $maxFiles = 800;

    /*
     * Scan likely public-rendering code first. Large plugins such as Forum can
     * contain more than the historical 200-file audit cap; relying on raw
     * filesystem traversal order could therefore miss the exact files that
     * expose PLG_itemDisplay().
     */
    foreach (HUB_auditPluginSourceRoots($plugin) as $root) {
        $priority = array(
            $root . DIRECTORY_SEPARATOR . 'include',
            $root . DIRECTORY_SEPARATOR . 'public_html',
            $root . DIRECTORY_SEPARATOR . 'admin',
        );

        foreach ($priority as $dir) {
            if (is_dir($dir)) {
                HUB_auditCollectSourceFilesRecursive($dir, $files, $count, $maxFiles, 0);
            }
        }

        foreach (array('functions.inc', 'index.php') as $entry) {
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $path;
                $count++;
            }
        }
    }

    foreach (HUB_auditPluginSourceRoots($plugin) as $root) {
        if ($count >= $maxFiles) {
            break;
        }
        HUB_auditCollectSourceFilesRecursive($root, $files, $count, $maxFiles, 0);
    }

    return array_values(array_unique($files));
}

function HUB_auditTokenText($token)
{
    return is_array($token) ? $token[1] : $token;
}

function HUB_auditTokenIsIgnorable($token)
{
    if (!is_array($token)) {
        return false;
    }
    return $token[0] === T_WHITESPACE
        || $token[0] === T_COMMENT
        || $token[0] === T_DOC_COMMENT;
}

function HUB_auditNextSignificantTokenIndex($tokens, $index)
{
    $count = count($tokens);
    for ($i = $index; $i < $count; $i++) {
        if (!HUB_auditTokenIsIgnorable($tokens[$i])) {
            return $i;
        }
    }
    return -1;
}

function HUB_auditPreviousSignificantTokenIndex($tokens, $index)
{
    for ($i = $index; $i >= 0; $i--) {
        if (!HUB_auditTokenIsIgnorable($tokens[$i])) {
            return $i;
        }
    }
    return -1;
}

function HUB_auditDecodePhpStringLiteral($literal)
{
    $length = strlen($literal);
    if ($length < 2) {
        return '';
    }
    $quote = $literal[0];
    if (($quote !== "'" && $quote !== '"') || $literal[$length - 1] !== $quote) {
        return '';
    }
    $value = substr($literal, 1, -1);
    if ($quote === "'") {
        return str_replace(array('\\\\', "\\'"), array('\\', "'"), $value);
    }
    return stripcslashes($value);
}

function HUB_auditLifecycleCallsFromSource($source)
{
    $facts = array(
        'item_saved'   => false,
        'item_deleted' => false,
        'object_types' => array(),
    );

    if (!function_exists('token_get_all')) {
        return $facts;
    }

    $tokens = token_get_all($source);
    $count = count($tokens);
    $targets = array(
        'plg_itemsaved'   => 'item_saved',
        'plg_itemdeleted' => 'item_deleted',
    );

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $name = strtolower($token[1]);
        if (!isset($targets[$name])) {
            continue;
        }

        $previous = HUB_auditPreviousSignificantTokenIndex($tokens, $i - 1);
        if ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_FUNCTION) {
            continue;
        }

        $open = HUB_auditNextSignificantTokenIndex($tokens, $i + 1);
        if ($open < 0 || HUB_auditTokenText($tokens[$open]) !== '(') {
            continue;
        }

        $facts[$targets[$name]] = true;

        $depth = 1;
        $argument = 1;
        $secondArgumentTokens = array();

        for ($j = $open + 1; $j < $count && $depth > 0; $j++) {
            $current = $tokens[$j];
            $tokenText = HUB_auditTokenText($current);

            if ($tokenText === '(' || $tokenText === '[' || $tokenText === '{') {
                $depth++;
            } elseif ($tokenText === ')' || $tokenText === ']' || $tokenText === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }

            if ($depth === 1 && $tokenText === ',') {
                $argument++;
                continue;
            }

            if ($argument === 2 && $depth === 1 && !HUB_auditTokenIsIgnorable($current)) {
                $secondArgumentTokens[] = $current;
            }
        }

        if (count($secondArgumentTokens) === 1
            && is_array($secondArgumentTokens[0])
            && $secondArgumentTokens[0][0] === T_CONSTANT_ENCAPSED_STRING
        ) {
            $type = trim(HUB_auditDecodePhpStringLiteral($secondArgumentTokens[0][1]));
            if ($type !== '') {
                $facts['object_types'][$type] = true;
            }
        }
    }

    $facts['object_types'] = array_keys($facts['object_types']);
    sort($facts['object_types']);

    return $facts;
}

function HUB_auditItemDisplayCallsFromSource($source)
{
    $facts = array(
        'found' => false,
        'object_types' => array(),
    );

    if (!function_exists('token_get_all')) {
        return $facts;
    }

    $tokens = token_get_all($source);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'plg_itemdisplay') {
            continue;
        }

        $previous = HUB_auditPreviousSignificantTokenIndex($tokens, $i - 1);
        if ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_FUNCTION) {
            continue;
        }

        $open = HUB_auditNextSignificantTokenIndex($tokens, $i + 1);
        if ($open < 0 || HUB_auditTokenText($tokens[$open]) !== '(') {
            continue;
        }

        $facts['found'] = true;

        $depth = 1;
        $argument = 1;
        $secondArgumentTokens = array();

        for ($j = $open + 1; $j < $count && $depth > 0; $j++) {
            $current = $tokens[$j];
            $tokenText = HUB_auditTokenText($current);

            if ($tokenText === '(' || $tokenText === '[' || $tokenText === '{') {
                $depth++;
            } elseif ($tokenText === ')' || $tokenText === ']' || $tokenText === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }

            if ($depth === 1 && $tokenText === ',') {
                $argument++;
                continue;
            }

            if ($argument === 2 && $depth === 1 && !HUB_auditTokenIsIgnorable($current)) {
                $secondArgumentTokens[] = $current;
            }
        }

        if (count($secondArgumentTokens) === 1
            && is_array($secondArgumentTokens[0])
            && $secondArgumentTokens[0][0] === T_CONSTANT_ENCAPSED_STRING
        ) {
            $type = trim(HUB_auditDecodePhpStringLiteral($secondArgumentTokens[0][1]));
            if ($type !== '') {
                $facts['object_types'][$type] = true;
            }
        }
    }

    $facts['object_types'] = array_keys($facts['object_types']);
    sort($facts['object_types']);

    return $facts;
}

function HUB_auditRelativeSourcePath($path, $plugin)
{
    foreach (HUB_auditPluginSourceRoots($plugin) as $root) {
        if (strpos($path, $root) === 0) {
            return basename($root) . '/' . ltrim(substr($path, strlen($root)), '/\\');
        }
    }
    return basename($path);
}

function HUB_auditSourceFacts($plugin)
{
    $facts = array(
        'files_scanned' => 0,
        'item_saved' => array(),
        'item_deleted' => array(),
        'object_types' => array(),
        'item_display' => array(),
        'item_display_types' => array(),
        'scan_limit' => 800,
        'scan_truncated' => false,
        'source_roots' => array(),
        'item_display_text_fallback' => array(),
    );
    $types = array();
    $displayTypes = array();

    $facts['source_roots'] = HUB_auditPluginSourceRoots($plugin);
    $sourceFiles = HUB_auditPluginSourceFiles($plugin);
    $facts['scan_truncated'] = count($sourceFiles) >= $facts['scan_limit'];

    foreach ($sourceFiles as $file) {
        $source = @file_get_contents($file);
        if ($source === false || $source === '') {
            continue;
        }

        $facts['files_scanned']++;
        $relative = HUB_auditRelativeSourcePath($file, $plugin);
        $sourceCalls = HUB_auditLifecycleCallsFromSource($source);

        if ($sourceCalls['item_saved']) {
            $facts['item_saved'][] = $relative;
        }
        if ($sourceCalls['item_deleted']) {
            $facts['item_deleted'][] = $relative;
        }
        foreach ($sourceCalls['object_types'] as $type) {
            $types[$type] = true;
        }

        $itemDisplay = HUB_auditItemDisplayCallsFromSource($source);
        if ($itemDisplay['found']) {
            $facts['item_display'][] = $relative;
        } elseif (stripos($source, 'PLG_itemDisplay(') !== false) {
            // Conservative fallback for legacy PHP tokenizer edge cases.
            // This is source evidence only and is reported separately.
            $facts['item_display'][] = $relative;
            $facts['item_display_text_fallback'][] = $relative;
        }
        foreach ($itemDisplay['object_types'] as $type) {
            $displayTypes[$type] = true;
        }
    }

    $facts['item_saved'] = array_values(array_unique($facts['item_saved']));
    $facts['item_deleted'] = array_values(array_unique($facts['item_deleted']));
    $facts['object_types'] = array_keys($types);
    sort($facts['object_types']);
    $facts['item_display'] = array_values(array_unique($facts['item_display']));
    $facts['item_display_text_fallback'] = array_values(array_unique($facts['item_display_text_fallback']));
    $facts['item_display_types'] = array_keys($displayTypes);
    sort($facts['item_display_types']);

    return $facts;
}

function HUB_auditCapabilityDetails($plugin, $autotags, $services)
{
    $details = array('item_info' => array(), 'related_items' => array(), 'id_to_url' => array(), 'blocks' => array(), 'autotags' => array(), 'search' => array(), 'services' => array());
    $map = array(
        'item_info' => 'plugin_getiteminfo_' . $plugin,
        'related_items' => 'plugin_getrelateditems_' . $plugin,
        'id_to_url' => 'plugin_idtourl_' . $plugin,
        'blocks' => 'plugin_getBlocks_' . $plugin,
        'search' => 'plugin_dopluginsearch_' . $plugin,
    );
    foreach ($map as $key => $function) {
        if (function_exists($function)) {
            $details[$key][] = HUB_auditFunctionSignature($function);
        }
    }
    if (function_exists('plugin_autotags_' . $plugin)) {
        $details['autotags'][] = HUB_auditFunctionSignature('plugin_autotags_' . $plugin);
        foreach ($autotags as $tag) {
            $details['autotags'][] = '[' . $tag . ':]';
        }
    }
    if (function_exists('plugin_wsEnabled_' . $plugin)) {
        $details['services'][] = HUB_auditFunctionSignature('plugin_wsEnabled_' . $plugin);
    }
    foreach ($services as $service) {
        $label = $service['signature'];
        if ($service['action'] !== '') {
            $label .= ' [action: ' . $service['action'] . ']';
        }
        $details['services'][] = $label;
    }
    return $details;
}

function HUB_auditLifecycleEmitterDetails($sourceFacts)
{
    $details = array();
    if (!empty($sourceFacts['item_saved'])) {
        $details[] = '◐ PLG_itemSaved() emitted in source: ' . implode(', ', $sourceFacts['item_saved']);
    }
    if (!empty($sourceFacts['item_deleted'])) {
        $details[] = '◐ PLG_itemDeleted() emitted in source: ' . implode(', ', $sourceFacts['item_deleted']);
    }
    if (empty($details)) {
        $details[] = '? No PLG_itemSaved() / PLG_itemDeleted() emission found in scanned source.';
    }
    $details[] = 'Scanned PHP files: ' . (int) $sourceFacts['files_scanned'];
    return $details;
}

function HUB_auditLifecycleListenerDetails($plugin)
{
    $details = array();
    $saved = 'plugin_itemsaved_' . $plugin;
    $deleted = 'plugin_itemdeleted_' . $plugin;
    if (function_exists($saved)) {
        $details[] = '✓ ' . HUB_auditFunctionSignature($saved);
    }
    if (function_exists($deleted)) {
        $details[] = '✓ ' . HUB_auditFunctionSignature($deleted);
    }
    if (empty($details)) {
        $details[] = '? No lifecycle listener callback detected at runtime.';
    }
    return $details;
}

function HUB_auditObjectTypes($plugin, $caps, $sourceFacts)
{
    $types = HUB_auditSearchTypes($plugin);
    foreach ($sourceFacts['object_types'] as $type) {
        if (!isset($types[$type])) {
            $types[$type] = array('label' => '', 'source' => 'lifecycle');
        } else {
            $types[$type]['source'] .= '+lifecycle';
        }
    }
    if ($caps['item_info'] && !isset($types[$plugin])) {
        $types[$plugin] = array('label' => '', 'source' => 'getiteminfo');
    } elseif ($caps['item_info'] && isset($types[$plugin])) {
        $types[$plugin]['source'] .= '+getiteminfo';
    }
    ksort($types);
    return $types;
}

function HUB_auditObjectTypeDetails($types)
{
    $details = array();
    foreach ($types as $type => $meta) {
        $sources = isset($meta['source']) ? explode('+', $meta['source']) : array();
        $evidence = array();
        if (in_array('searchtypes', $sources, true)) {
            $evidence[] = 'declared by plugin_searchtypes_*()';
        }
        if (in_array('lifecycle', $sources, true)) {
            $evidence[] = 'observed in lifecycle source';
        }
        if (in_array('getiteminfo', $sources, true)) {
            $evidence[] = 'primary type inferred from getItemInfo';
        }
        $label = isset($meta['label']) && $meta['label'] !== '' ? ' — ' . $meta['label'] : '';
        $details[] = '✓ ' . $type . $label . (empty($evidence) ? '' : ' (' . implode('; ', $evidence) . ')');
    }
    if (empty($details)) {
        $details[] = '? No stable object type could be discovered automatically.';
    }
    return $details;
}

function HUB_auditLifecycleContractDetails($plugin)
{
    $details = array();

    foreach (array('plugin_itemsaved_' . $plugin, 'plugin_itemdeleted_' . $plugin) as $function) {
        if (!function_exists($function)) {
            continue;
        }

        $subTypeAware = false;

        if (class_exists('ReflectionFunction')) {
            try {
                $reflection = new ReflectionFunction($function);
                foreach ($reflection->getParameters() as $parameter) {
                    if (strtolower($parameter->getName()) === 'sub_type') {
                        $subTypeAware = true;
                        break;
                    }
                }
            } catch (Exception $e) {
                $subTypeAware = false;
            }
        }

        $details[] = ($subTypeAware ? '✓ ' : '◐ ')
            . HUB_auditFunctionSignature($function)
            . ($subTypeAware ? ' — sub_type-aware' : ' — legacy/no sub_type');
    }

    if (empty($details)) {
        $details[] = '? No lifecycle listener callback detected.';
    }

    return $details;
}

function HUB_auditAdditionalCapabilities($plugin)
{
    $map = array(
        'URL to ID' => 'plugin_urltoid_' . $plugin,
        'Language overrides' => 'plugin_getlanguageoverrides_' . $plugin,
        'User contributed content' => 'plugin_usercontributed_' . $plugin,
        'reCAPTCHA support' => 'plugin_supportsrecaptcha_' . $plugin,
    );

    $details = array();

    foreach ($map as $label => $function) {
        if (function_exists($function)) {
            $details[] = '✓ ' . $label . ': ' . HUB_auditFunctionSignature($function);
        }
    }

    return $details;
}

function HUB_auditProviderItemDisplayProbe($plugin)
{
    global $_CONF;

    $details = array();
    $plugin = strtolower(trim((string) $plugin));

    if ($plugin !== 'forum') {
        return $details;
    }

    $base = isset($_CONF['path']) ? rtrim($_CONF['path'], '/\\') : '';
    if ($base === '') {
        $details[] = '? Direct Forum probe unavailable: Geeklog private path is not defined.';
        return $details;
    }

    $path = $base . '/plugins/forum/include/viewtopic_core.php';
    if (!file_exists($path)) {
        $details[] = '? Direct Forum probe: include/viewtopic_core.php not found at ' . $path;
        return $details;
    }

    if (!is_readable($path)) {
        $details[] = '? Direct Forum probe: include/viewtopic_core.php exists but is not readable.';
        return $details;
    }

    $source = @file_get_contents($path);
    if ($source === false) {
        $details[] = '? Direct Forum probe: include/viewtopic_core.php could not be read.';
        return $details;
    }

    $hasHook = stripos($source, 'PLG_itemDisplay(') !== false;
    $details[] = 'Direct Forum probe: include/viewtopic_core.php is readable; '
        . strlen($source) . ' bytes; PLG_itemDisplay() '
        . ($hasHook ? 'FOUND' : 'NOT FOUND') . '.';

    if (function_exists('sha1_file')) {
        $hash = @sha1_file($path);
        if ($hash !== false && $hash !== '') {
            $details[] = 'Direct Forum probe SHA-1: ' . $hash;
        }
    }

    return $details;
}

function HUB_auditItemDisplayPlacementDetails($sourceFacts, $plugin = '')
{
    $details = array();

    if (!empty($sourceFacts['item_display'])) {
        $details[] = '◐ PLG_itemDisplay() provider placement found in source: '
            . implode(', ', $sourceFacts['item_display']);
        if (!empty($sourceFacts['item_display_text_fallback'])) {
            $details[] = '◐ Text fallback matched in: ' . implode(', ', $sourceFacts['item_display_text_fallback']);
        }
        if (!empty($sourceFacts['item_display_types'])) {
            $details[] = 'Detected item type(s): ' . implode(', ', $sourceFacts['item_display_types']);
        } else {
            $details[] = '◐ Placement found, but the item type is dynamic or could not be inferred safely.';
        }
    } else {
        $details[] = '? No PLG_itemDisplay() provider placement found in scanned source.';
    }

    if (!empty($sourceFacts['source_roots'])) {
        $details[] = 'Source roots: ' . implode(' | ', $sourceFacts['source_roots']);
    } else {
        $details[] = '? No readable plugin source root was discovered.';
    }

    if (!empty($sourceFacts['scan_truncated'])) {
        $details[] = '◐ Source scan reached the configured file limit; absence cannot be treated as definitive.';
    }
    $details[] = 'Scanned PHP files: ' . (int) $sourceFacts['files_scanned'];

    foreach (HUB_auditProviderItemDisplayProbe($plugin) as $probeDetail) {
        $details[] = $probeDetail;
    }

    return $details;
}

function HUB_auditItemDisplayConsumerDetails($plugin)
{
    $function = 'plugin_itemdisplay_' . $plugin;
    if (function_exists($function)) {
        return array('✓ ' . HUB_auditFunctionSignature($function));
    }

    return array('? No plugin_itemdisplay_' . $plugin . '() callback detected at runtime.');
}

function HUB_auditRecommendations($plugin, $caps, $sourceFacts)
{
    $recommendations = array();
    if ($plugin === 'hub') {
        $recommendations[] = 'Hub is the orchestrator. It audits interoperability, renders assigned Static Page topics, suggests article-to-Static-Page links, and discovers reusable plugin capabilities without acting as a content provider.';
    } else {
        if (!$caps['item_info']) {
            $recommendations[] = 'If this plugin exposes addressable content, implement plugin_getiteminfo_' . $plugin . '() so Hub can resolve current title and URL from type + id.';
        }
        if (!$caps['related_items']) {
            $recommendations[] = 'Optional for content plugins: implement plugin_getrelateditems_' . $plugin . '() for topic-related items and future Hub suggestions.';
        }
        if (!$caps['blocks']) {
            $recommendations[] = 'Optional for reusable dynamic blocks: expose them through plugin_getBlocks_' . $plugin . '().';
        }
        if (!$caps['autotags']) {
            $recommendations[] = 'Optional for embeddable content: expose autotags through plugin_autotags_' . $plugin . '().';
        }
        if (!$caps['search']) {
            $recommendations[] = 'If the plugin owns searchable content, implement plugin_dopluginsearch_' . $plugin . '().';
        }
        if (!$caps['services']) {
            $recommendations[] = 'If another plugin should request specialized actions or rendering, expose a Geeklog service entry point.';
        }
        if ($caps['item_info'] && empty($sourceFacts['item_display'])) {
            $recommendations[] = 'If this plugin has a full public item view, call PLG_itemDisplay($id, $type) at a stable server-rendered location so other plugins can contribute contextual fragments.';
        }
    }
    if (empty($sourceFacts['item_saved']) && empty($sourceFacts['item_deleted'])) {
        $recommendations[] = 'Lifecycle emitter: no PLG_itemSaved() / PLG_itemDeleted() call was found in scanned source. Addressable content plugins should consider emitting them.';
    } elseif (empty($sourceFacts['item_saved']) || empty($sourceFacts['item_deleted'])) {
        $recommendations[] = 'Lifecycle emitter: only part of the save/delete notification pair was source-detected; review coverage.';
    } else {
        $recommendations[] = 'Lifecycle emitter calls were found in source. This is strong evidence, not a guarantee that every mutation path emits them.';
    }
    return $recommendations;
}

function HUB_auditPlugin($plugin)
{
    $autotags = HUB_auditAutotags($plugin);
    $serviceCatalogue = HUB_serviceCatalogue($plugin);
    $renderCatalogue = HUB_renderCatalogue($plugin);
    $services = HUB_auditServiceFunctions($plugin);
    $capabilityDeclaration = HUB_capabilityDeclaration($plugin);
    $contentContract = HUB_contentContractEvidence($plugin);
    $metadataManifest = HUB_metadataManifest($plugin);
    $sourceFacts = HUB_auditSourceFacts($plugin);
    $caps = array(
        'item_info' => HUB_auditFunctionExists('plugin_getiteminfo_', $plugin),
        'related_items' => HUB_auditFunctionExists('plugin_getrelateditems_', $plugin),
        'id_to_url' => HUB_auditFunctionExists('plugin_idtourl_', $plugin),
        'blocks' => HUB_auditFunctionExists('plugin_getblocks_', $plugin),
        'autotags' => HUB_auditFunctionExists('plugin_autotags_', $plugin),
        'search' => HUB_auditFunctionExists('plugin_dopluginsearch_', $plugin),
        'services' => HUB_auditFunctionExists('plugin_wsEnabled_', $plugin) || !empty($services),
    );
    $capabilityEvidence = HUB_capabilityImplementationEvidence(
        $plugin,
        $capabilityDeclaration,
        $caps,
        $sourceFacts,
        $serviceCatalogue,
        $contentContract
    );

    $score = 0;
    foreach ($caps as $value) {
        if ($value) {
            $score++;
        }
    }
    $readiness = $score >= 5 ? 'ready' : ($score >= 2 ? 'partial' : 'basic');
    $objectTypes = HUB_auditObjectTypes($plugin, $caps, $sourceFacts);
    return array(
        'plugin' => $plugin,
        'version' => HUB_auditPluginVersion($plugin),
        'caps' => $caps,
        'details' => HUB_auditCapabilityDetails($plugin, $autotags, $services),
        'autotags' => $autotags,
        'services' => $services,
        'service_catalogue' => $serviceCatalogue,
        'service_catalogue_details' => HUB_serviceCatalogueDetails($serviceCatalogue),
        'render_catalogue' => $renderCatalogue,
        'render_catalogue_details' => HUB_renderCatalogueDetails($renderCatalogue),
        'capability_declaration' => $capabilityDeclaration,
        'capability_declaration_details' => HUB_capabilityDeclarationDetails($capabilityDeclaration),
        'capability_implementation_evidence' => $capabilityEvidence,
        'capability_implementation_details' => isset($capabilityEvidence['details']) ? $capabilityEvidence['details'] : array(),
        'capability_implementation_recommendations' => isset($capabilityEvidence['recommendations']) ? $capabilityEvidence['recommendations'] : array(),
        'content_contract' => $contentContract,
        'content_contract_details' => HUB_contentContractDetails($contentContract),
        'metadata_manifest' => $metadataManifest,
        'metadata_manifest_details' => HUB_metadataManifestDetails($metadataManifest),
        'lifecycle_emitter' => HUB_auditLifecycleEmitterDetails($sourceFacts),
        'lifecycle_listener' => HUB_auditLifecycleListenerDetails($plugin),
        'lifecycle_contract' => HUB_auditLifecycleContractDetails($plugin),
        'item_display_provider' => !empty($sourceFacts['item_display']),
        'item_display_provider_details' => HUB_auditItemDisplayPlacementDetails($sourceFacts, $plugin),
        'item_display_consumer' => function_exists('plugin_itemdisplay_' . $plugin),
        'item_display_consumer_details' => HUB_auditItemDisplayConsumerDetails($plugin),
        'object_types' => HUB_auditObjectTypeDetails($objectTypes),
        'search_types_function' => function_exists('plugin_searchtypes_' . $plugin) ? HUB_auditFunctionSignature('plugin_searchtypes_' . $plugin) : '',
        'api_surface' => HUB_auditPluginApiSurface($plugin),
        'additional_capabilities' => HUB_auditAdditionalCapabilities($plugin),
        'source_facts' => $sourceFacts,
        'recommendations' => HUB_auditRecommendations($plugin, $caps, $sourceFacts),
        'score' => $score,
        'readiness' => $readiness,
    );
}

function HUB_auditActivePlugins()
{
    global $_PLUGINS;
    $rows = array();
    if (!is_array($_PLUGINS)) {
        return $rows;
    }
    foreach ($_PLUGINS as $plugin) {
        $rows[] = HUB_auditPlugin($plugin);
    }
    return $rows;
}
