<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
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
    $services = array();
    $suffix = '_' . strtolower($plugin);
    foreach (HUB_auditRuntimeFunctions($plugin, 'service_') as $function) {
        $lower = strtolower($function);
        $services[] = array(
            'name' => $function,
            'action' => substr($lower, strlen('service_'), -strlen($suffix)),
            'signature' => HUB_auditFunctionSignature($function),
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
    foreach (HUB_auditPluginSourceRoots($plugin) as $root) {
        HUB_auditCollectSourceFilesRecursive($root, $files, $count, 200, 0);
        if ($count >= 200) {
            break;
        }
    }
    return array_values(array_unique($files));
}

function HUB_auditStripPhpComments($source)
{
    if (!function_exists('token_get_all')) {
        return $source;
    }
    $clean = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $clean .= ' ';
            } else {
                $clean .= $token[1];
            }
        } else {
            $clean .= $token;
        }
    }
    return $clean;
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
    $facts = array('files_scanned' => 0, 'item_saved' => array(), 'item_deleted' => array(), 'object_types' => array());
    $types = array();
    foreach (HUB_auditPluginSourceFiles($plugin) as $file) {
        $source = @file_get_contents($file);
        if ($source === false || $source === '') {
            continue;
        }
        $facts['files_scanned']++;
        $clean = HUB_auditStripPhpComments($source);
        $relative = HUB_auditRelativeSourcePath($file, $plugin);
        if (preg_match('/\\bPLG_itemSaved\\s*\\(/i', $clean)) {
            $facts['item_saved'][] = $relative;
        }
        if (preg_match('/\\bPLG_itemDeleted\\s*\\(/i', $clean)) {
            $facts['item_deleted'][] = $relative;
        }
        $patterns = array(
            '/\\bPLG_itemSaved\\s*\\(\\s*[^,]+,\\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/\\bPLG_itemDeleted\\s*\\(\\s*[^,]+,\\s*[\'\"]([^\'\"]+)[\'\"]/i',
        );
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $clean, $matches) && isset($matches[1])) {
                foreach ($matches[1] as $type) {
                    if (trim($type) !== '') {
                        $types[trim($type)] = true;
                    }
                }
            }
        }
    }
    $facts['item_saved'] = array_values(array_unique($facts['item_saved']));
    $facts['item_deleted'] = array_values(array_unique($facts['item_deleted']));
    $facts['object_types'] = array_keys($types);
    sort($facts['object_types']);
    return $facts;
}

function HUB_auditCapabilityDetails($plugin, $autotags, $services)
{
    $details = array('item_info' => array(), 'related_items' => array(), 'blocks' => array(), 'autotags' => array(), 'search' => array(), 'services' => array());
    $map = array(
        'item_info' => 'plugin_getiteminfo_' . $plugin,
        'related_items' => 'plugin_getrelateditems_' . $plugin,
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

function HUB_auditRecommendations($plugin, $caps, $sourceFacts)
{
    $recommendations = array();
    if ($plugin === 'hub') {
        $recommendations[] = 'Hub is the orchestrator. Its current 0.1.0 audit milestone does not need addressable content APIs yet.';
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
    $services = HUB_auditServiceFunctions($plugin);
    $sourceFacts = HUB_auditSourceFacts($plugin);
    $caps = array(
        'item_info' => HUB_auditFunctionExists('plugin_getiteminfo_', $plugin),
        'related_items' => HUB_auditFunctionExists('plugin_getrelateditems_', $plugin),
        'blocks' => HUB_auditFunctionExists('plugin_getblocks_', $plugin),
        'autotags' => HUB_auditFunctionExists('plugin_autotags_', $plugin),
        'search' => HUB_auditFunctionExists('plugin_dopluginsearch_', $plugin),
        'services' => HUB_auditFunctionExists('plugin_wsEnabled_', $plugin) || !empty($services),
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
        'lifecycle_emitter' => HUB_auditLifecycleEmitterDetails($sourceFacts),
        'lifecycle_listener' => HUB_auditLifecycleListenerDetails($plugin),
        'object_types' => HUB_auditObjectTypeDetails($objectTypes),
        'search_types_function' => function_exists('plugin_searchtypes_' . $plugin) ? HUB_auditFunctionSignature('plugin_searchtypes_' . $plugin) : '',
        'api_surface' => HUB_auditPluginApiSurface($plugin),
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
