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

function HUB_auditAutotags($plugin)
{
    $function = 'plugin_autotags_' . $plugin;
    $tags = array();

    if (!function_exists($function)) {
        return $tags;
    }

    $result = call_user_func($function, 'tagname');
    if (!is_array($result)) {
        return $tags;
    }

    foreach ($result as $tag) {
        if (is_string($tag) && $tag !== '') {
            $tags[] = $tag;
        }
    }

    return array_values(array_unique($tags));
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
        if (strpos($lower, $prefix) !== 0) {
            continue;
        }
        if (substr($lower, -strlen($suffix)) !== $suffix) {
            continue;
        }
        $matches[] = $function;
    }

    sort($matches);

    return array_values(array_unique($matches));
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
            $part = '';
            if ($parameter->isPassedByReference()) {
                $part .= '&';
            }
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

function HUB_auditServiceFunctions($plugin)
{
    $services = array();
    $functions = HUB_auditRuntimeFunctions($plugin, 'service_');
    $suffix = '_' . strtolower($plugin);

    foreach ($functions as $function) {
        $lower = strtolower($function);
        $action = substr($lower, strlen('service_'), -strlen($suffix));
        $services[] = array(
            'name'      => $function,
            'action'    => $action,
            'signature' => HUB_auditFunctionSignature($function),
        );
    }

    return $services;
}

function HUB_auditHasServiceLayer($plugin, $services)
{
    return HUB_auditFunctionExists('plugin_wsEnabled_', $plugin) || !empty($services);
}

function HUB_auditPluginApiSurface($plugin)
{
    $functions = HUB_auditRuntimeFunctions($plugin, 'plugin_');
    $result = array();

    foreach ($functions as $function) {
        $result[] = HUB_auditFunctionSignature($function);
    }

    return $result;
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

    $skipDirs = array(
        '.', '..', '.git', 'vendor', 'node_modules', 'cache', 'data', 'logs',
        'backups', 'tests', 'test', 'docs', 'language', 'templates', 'sql'
    );

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
    $maxFiles = 200;

    foreach (HUB_auditPluginSourceRoots($plugin) as $root) {
        HUB_auditCollectSourceFilesRecursive($root, $files, $count, $maxFiles, 0);
        if ($count >= $maxFiles) {
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

    $tokens = token_get_all($source);
    $clean = '';

    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $clean .= ' ';
                continue;
            }
            $clean .= $token[1];
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
            $relative = ltrim(substr($path, strlen($root)), '/\\');
            return basename($root) . '/' . $relative;
        }
    }

    return basename($path);
}

function HUB_auditSourceFacts($plugin)
{
    $facts = array(
        'files_scanned' => 0,
        'item_saved'    => array(),
        'item_deleted'  => array(),
        'object_types'  => array(),
    );

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
                    $type = trim($type);
                    if ($type !== '') {
                        $types[$type] = true;
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
    $details = array(
        'item_info'     => array(),
        'related_items' => array(),
        'blocks'        => array(),
        'autotags'      => array(),
        'search'        => array(),
        'services'      => array(),
    );

    $functionMap = array(
        'item_info'     => 'plugin_getiteminfo_' . $plugin,
        'related_items' => 'plugin_getrelateditems_' . $plugin,
        'blocks'        => 'plugin_getBlocks_' . $plugin,
        'search'        => 'plugin_dopluginsearch_' . $plugin,
    );

    foreach ($functionMap as $key => $function) {
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
            $label .= '  [action: ' . $service['action'] . ']';
        }
        $details['services'][] = $label;
    }

    return $details;
}

function HUB_auditLifecycleDetails($sourceFacts)
{
    $details = array();

    if (!empty($sourceFacts['item_saved'])) {
        $details[] = '◐ PLG_itemSaved() source-detected in: ' . implode(', ', $sourceFacts['item_saved']);
    }
    if (!empty($sourceFacts['item_deleted'])) {
        $details[] = '◐ PLG_itemDeleted() source-detected in: ' . implode(', ', $sourceFacts['item_deleted']);
    }
    if (empty($details)) {
        $details[] = '? No lifecycle call detected in scanned PHP source.';
    }

    $details[] = 'Scanned PHP files: ' . (int) $sourceFacts['files_scanned'];

    return $details;
}

function HUB_auditObjectTypeDetails($plugin, $caps, $sourceFacts)
{
    $details = array();

    foreach ($sourceFacts['object_types'] as $type) {
        $details[] = '◐ ' . $type . ' (literal type observed in lifecycle call)';
    }

    if ($caps['item_info']) {
        $details[] = '✓ ' . $plugin . ' (primary plugin type inferred from plugin_getiteminfo_' . $plugin . '())';
    }

    if (empty($details)) {
        $details[] = '? No stable object type could be inferred automatically.';
    }

    return array_values(array_unique($details));
}

function HUB_auditRecommendations($plugin, $caps, $sourceFacts)
{
    $recommendations = array();

    if ($plugin === 'hub') {
        $recommendations[] = 'Hub is the orchestrator. Its current 0.1.0 audit milestone does not need to expose addressable content APIs yet.';
    } else {
        if (!$caps['item_info']) {
            $recommendations[] = 'If this plugin exposes addressable content, implement plugin_getiteminfo_' . $plugin . '() so Hub can resolve current title and URL from type + id.';
        }
        if (!$caps['related_items']) {
            $recommendations[] = 'Optional for content plugins: implement plugin_getrelateditems_' . $plugin . '() to contribute topic-related items and future Hub suggestions.';
        }
        if (!$caps['blocks']) {
            $recommendations[] = 'Optional for plugins with reusable dynamic blocks: expose them through plugin_getBlocks_' . $plugin . '().';
        }
        if (!$caps['autotags']) {
            $recommendations[] = 'Optional for embeddable content: expose one or more autotags through plugin_autotags_' . $plugin . '().';
        }
        if (!$caps['search']) {
            $recommendations[] = 'If the plugin owns searchable content, implement plugin_dopluginsearch_' . $plugin . '().';
        }
        if (!$caps['services']) {
            $recommendations[] = 'If Hub or another plugin should request a specialized action or renderer, expose a Geeklog service entry point for this plugin.';
        }
    }

    if (empty($sourceFacts['item_saved']) && empty($sourceFacts['item_deleted'])) {
        $recommendations[] = 'Lifecycle: no PLG_itemSaved() / PLG_itemDeleted() calls were found in the scanned PHP source. If the plugin owns addressable content, consider emitting them on save/delete.';
    } elseif (empty($sourceFacts['item_saved']) || empty($sourceFacts['item_deleted'])) {
        $recommendations[] = 'Lifecycle: only part of the expected save/delete notification pair was source-detected. Review lifecycle coverage.';
    } else {
        $recommendations[] = 'Lifecycle calls were found in source. This is strong evidence, but not a runtime guarantee that every save/delete path emits them.';
    }

    return $recommendations;
}

function HUB_auditPlugin($plugin)
{
    $autotags = HUB_auditAutotags($plugin);
    $services = HUB_auditServiceFunctions($plugin);
    $sourceFacts = HUB_auditSourceFacts($plugin);

    $caps = array(
        'item_info'     => HUB_auditFunctionExists('plugin_getiteminfo_', $plugin),
        'related_items' => HUB_auditFunctionExists('plugin_getrelateditems_', $plugin),
        'blocks'        => HUB_auditFunctionExists('plugin_getblocks_', $plugin),
        'autotags'      => HUB_auditFunctionExists('plugin_autotags_', $plugin),
        'search'        => HUB_auditFunctionExists('plugin_dopluginsearch_', $plugin),
        'services'      => HUB_auditHasServiceLayer($plugin, $services),
    );

    $score = 0;
    foreach ($caps as $value) {
        if ($value) {
            $score++;
        }
    }

    if ($score >= 5) {
        $readiness = 'ready';
    } elseif ($score >= 2) {
        $readiness = 'partial';
    } else {
        $readiness = 'basic';
    }

    return array(
        'plugin'          => $plugin,
        'version'         => HUB_auditPluginVersion($plugin),
        'caps'            => $caps,
        'details'         => HUB_auditCapabilityDetails($plugin, $autotags, $services),
        'autotags'        => $autotags,
        'services'        => $services,
        'lifecycle'       => HUB_auditLifecycleDetails($sourceFacts),
        'object_types'    => HUB_auditObjectTypeDetails($plugin, $caps, $sourceFacts),
        'api_surface'     => HUB_auditPluginApiSurface($plugin),
        'source_facts'    => $sourceFacts,
        'recommendations' => HUB_auditRecommendations($plugin, $caps, $sourceFacts),
        'score'           => $score,
        'readiness'       => $readiness,
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
