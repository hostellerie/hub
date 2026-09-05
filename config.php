<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

global $_HUB_PLUGIN;

$_HUB_PLUGIN = array(
    'pi_name'       => 'hub',
    'pi_version'    => '0.1.1-dev',
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
