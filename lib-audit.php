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

function HUB_auditHasServiceLayer($plugin)
{
    return HUB_auditFunctionExists('plugin_wsEnabled_', $plugin)
        || HUB_auditFunctionExists('service_get_', $plugin)
        || HUB_auditFunctionExists('service_submit_', $plugin)
        || HUB_auditFunctionExists('service_update_', $plugin)
        || HUB_auditFunctionExists('service_delete_', $plugin);
}

function HUB_auditPlugin($plugin)
{
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
        'plugin'    => $plugin,
        'version'   => HUB_auditPluginVersion($plugin),
        'caps'      => $caps,
        'score'     => $score,
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
        if ($plugin === 'hub') {
            continue;
        }
        $rows[] = HUB_auditPlugin($plugin);
    }
    return $rows;
}
