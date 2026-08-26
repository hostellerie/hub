<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function plugin_autoinstall_hub($pi_name)
{
    global $_HUB_PLUGIN;

    require_once __DIR__ . '/config.php';

    return array(
        'info' => array(
            'pi_name'         => $_HUB_PLUGIN['pi_name'],
            'pi_display_name' => 'Hub',
            'pi_version'      => $_HUB_PLUGIN['pi_version'],
            'pi_gl_version'   => $_HUB_PLUGIN['gl_version'],
            'pi_homepage'     => $_HUB_PLUGIN['pi_url'],
        ),
        'groups'   => $_HUB_PLUGIN['GROUPS'],
        'features' => $_HUB_PLUGIN['FEATURES'],
        'mappings' => $_HUB_PLUGIN['MAPPINGS'],
        'tables'   => array(),
    );
}

function plugin_compatible_with_this_version_hub($pi_name)
{
    if (defined('VERSION') && version_compare(VERSION, '2.1.1', '<')) {
        return false;
    }
    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        return false;
    }
    return true;
}
