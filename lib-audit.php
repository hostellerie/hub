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

function HUB_auditServiceFunctions($plugin)
{
    $services = array();
    $defined = get_defined_functions();

    if (!isset($defined['user']) || !is_array($defined['user'])) {
        return $services;
    }

    $suffix = '_' . strtolower($plugin);
    foreach ($defined['user'] as $function) {
        $lower = strtolower($function);
        if (strpos($lower, 'service_') !== 0) {
            continue;
        }
        if (substr($lower, -strlen($suffix)) !== $suffix) {
            continue;
        }
        $services[] = $function . '()';
    }

    sort($services);

    return array_values(array_unique($services));
}

function HUB_auditHasServiceLayer($plugin)
{
    return HUB_auditFunctionExists('plugin_wsEnabled_', $plugin)
        || !empty(HUB_auditServiceFunctions($plugin));
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
            $details[$key][] = $function . '()';
        }
    }

    if (function_exists('plugin_autotags_' . $plugin)) {
        $details['autotags'][] = 'plugin_autotags_' . $plugin . '()';
        foreach ($autotags as $tag) {
            $details['autotags'][] = '[' . $tag . ':]';
        }
    }

    if (function_exists('plugin_wsEnabled_' . $plugin)) {
        $details['services'][] = 'plugin_wsEnabled_' . $plugin . '()';
    }
    foreach ($services as $service) {
        $details['services'][] = $service;
    }

    return $details;
}

function HUB_auditRecommendations($plugin, $caps)
{
    $recommendations = array();

    if ($plugin === 'hub') {
        $recommendations[] = 'Hub is the orchestrator. Its current 0.1.0 audit-only milestone does not need to expose content APIs yet.';
        $recommendations[] = 'Next: add an explicit Hub capability contract before pillar relationships are introduced.';
        return $recommendations;
    }

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

    $recommendations[] = 'Lifecycle support is not inferred. A future explicit capability declaration should state whether saves/deletes emit PLG_itemSaved() and PLG_itemDeleted().';

    return $recommendations;
}

function HUB_auditPlugin($plugin)
{
    $autotags = HUB_auditAutotags($plugin);
    $services = HUB_auditServiceFunctions($plugin);

    $caps = array(
        'item_info'     => HUB_auditFunctionExists('plugin_getiteminfo_', $plugin),
        'related_items' => HUB_auditFunctionExists('plugin_getrelateditems_', $plugin),
        'blocks'        => HUB_auditFunctionExists('plugin_getblocks_', $plugin),
        'autotags'      => HUB_auditFunctionExists('plugin_autotags_', $plugin),
        'search'        => HUB_auditFunctionExists('plugin_dopluginsearch_', $plugin),
        'services'      => HUB_auditHasServiceLayer($plugin),
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
        'recommendations' => HUB_auditRecommendations($plugin, $caps),
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
