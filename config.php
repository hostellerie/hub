<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

global $_HUB_PLUGIN, $_TABLES, $_DB_table_prefix;

if (!isset($_DB_table_prefix)) {
    $_DB_table_prefix = '';
}
if (!isset($_TABLES['hub_pillars'])) {
    $_TABLES['hub_pillars'] = $_DB_table_prefix . 'hub_pillars';
}
if (!isset($_TABLES['hub_relations'])) {
    $_TABLES['hub_relations'] = $_DB_table_prefix . 'hub_relations';
}

$_HUB_PLUGIN = array(
    'pi_name'       => 'hub',
    'pi_version'    => '0.7.0',
    'gl_version'    => '2.1.1',
    'pi_url'        => 'https://github.com/hostellerie/hub',
    'GROUPS'        => array(
        'Hub Admin' => 'Users in this group can administer the Hub plugin',
    ),
    'FEATURES'      => array(
        'hub.admin' => 'Full access to Hub plugin administration',
    ),
    'MAPPINGS'      => array(
        'hub.admin' => array('Hub Admin'),
    ),
);
