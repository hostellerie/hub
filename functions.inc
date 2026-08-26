<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

$hub_path = $_CONF['path'] . 'plugins/hub/';
require_once $hub_path . 'lib-audit.php';

function plugin_cclabel_hub()
{
    global $_CONF;
    if (!SEC_hasRights('hub.admin')) {
        return array();
    }
    return array('Hub', $_CONF['site_admin_url'] . '/plugins/hub/index.php', '');
}

function plugin_getadminoption_hub()
{
    global $_CONF;
    if (SEC_hasRights('hub.admin')) {
        return array('Hub', $_CONF['site_admin_url'] . '/plugins/hub/index.php', 0);
    }
}

function plugin_chkVersion_hub()
{
    global $_CONF;
    require_once $_CONF['path'] . 'plugins/hub/autoinstall.php';
    $info = plugin_autoinstall_hub('hub');
    return $info['info']['pi_version'];
}

function plugin_upgrade_hub()
{
    global $_TABLES;
    $codeVersion = plugin_chkVersion_hub();
    DB_query("UPDATE {$_TABLES['plugins']} SET pi_version = '" . addslashes($codeVersion) . "', pi_gl_version = '2.1.1' WHERE pi_name = 'hub'");
    return true;
}

function plugin_autouninstall_hub()
{
    return array(
        'tables'     => array(),
        'groups'     => array('Hub Admin'),
        'features'   => array('hub.admin'),
        'php_blocks' => array(),
        'vars'       => array(),
    );
}
