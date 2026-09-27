<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

$hub_path = $_CONF['path'] . 'plugins/hub/';
require_once $hub_path . 'config.php';
require_once $hub_path . 'lib-capabilities.php';
require_once $hub_path . 'lib-capability-evidence.php';
require_once $hub_path . 'lib-content-contract.php';
require_once $hub_path . 'lib-metadata.php';
require_once $hub_path . 'lib-services.php';
require_once $hub_path . 'lib-render.php';
require_once $hub_path . 'lib-audit.php';
require_once $hub_path . 'lib-role.php';
require_once $hub_path . 'lib-stats.php';
require_once $hub_path . 'lib-distribution.php';
require_once $hub_path . 'lib-audit-cache.php';
require_once $hub_path . 'lib-link-audit.php';
require_once $hub_path . 'lib-relations.php';
require_once $hub_path . 'lib-staticpages.php';

function plugin_cclabel_hub()
{
    global $_CONF;
    if (!SEC_hasRights('hub.admin')) {
        return array();
    }
    return array('Hub', $_CONF['site_admin_url'] . '/plugins/hub/index.php', '');
}

function plugin_getcapabilities_hub()
{
    return array(
        'schema' => 1,
        'roles' => array('relationship', 'orchestrator', 'service'),
        'capabilities' => array()
    );
}

function plugin_getadminoption_hub()
{
    global $_CONF;
    if (SEC_hasRights('hub.admin')) {
        return array('Hub', $_CONF['site_admin_url'] . '/plugins/hub/index.php', 0);
    }
}



/**
 * Load Hub public styles independently from the active theme.
 *
 * @return string
 */
function plugin_getheadercode_hub()
{
    global $_CONF, $_HUB_PLUGIN;

    $cssFile = $_CONF['path'] . 'plugins/hub/public_html/hub.css';
    $version = isset($_HUB_PLUGIN['pi_version']) ? (string) $_HUB_PLUGIN['pi_version'] : '0';
    if (is_file($cssFile)) {
        $version .= '-' . (string) filemtime($cssFile);
    }

    $href = rtrim($_CONF['site_url'], '/') . '/hub/hub.css?v=' . rawurlencode($version);

    return '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
        . '">' . PHP_EOL;
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
    global $_CONF, $_TABLES;

    $installedVersion = DB_getItem($_TABLES['plugins'], 'pi_version', "pi_name = 'hub'");
    if ($installedVersion === false || $installedVersion === '') {
        $installedVersion = '0.1.0';
    }

    if (version_compare((string) $installedVersion, '0.3.0', '<')) {
        require_once $_CONF['path'] . 'plugins/hub/install_updates.php';
        if (!HUB_updateSchema_0_3_0()) {
            return false;
        }
    }

    $codeVersion = plugin_chkVersion_hub();
    DB_query(
        "UPDATE {$_TABLES['plugins']} SET pi_version = '" . DB_escapeString($codeVersion)
        . "', pi_gl_version = '2.1.1' WHERE pi_name = 'hub'",
        1
    );

    return !DB_error();
}

function plugin_autouninstall_hub()
{
    return array(
        'tables'     => array('hub_pillars', 'hub_relations'),
        'groups'     => array('Hub Admin'),
        'features'   => array('hub.admin'),
        'php_blocks' => array(),
        'vars'       => array(),
    );
}


/**
 * Extend supported Geeklog templates with Hub relationship context.
 *
 * Static Pages already call PLG_templateSetVars('staticpage', ...), so Hub can
 * append assigned topics at render time without modifying stored sp_content.
 *
 * @param string $templatename
 * @param object $template
 * @return void
 */
function plugin_templatesetvars_hub($templatename, &$template)
{
    if ($templatename !== 'staticpage' || !is_object($template)) {
        return;
    }

    if (!method_exists($template, 'get_var') || !method_exists($template, 'set_var')) {
        return;
    }

    $pageId = (string) $template->get_var('sp_id');
    if ($pageId === '') {
        return;
    }

    $content = (string) $template->get_var('content');
    $append = array();

    $relationsHtml = HUB_renderPillarRelations('staticpages', $pageId);
    if ($relationsHtml !== '') {
        $append[] = $relationsHtml;
    }

    $topicsHtml = HUB_renderStaticPageTopics($pageId);
    if ($topicsHtml !== '') {
        $append[] = $topicsHtml;
    }

    if (!empty($append)) {
        $template->set_var('content', $content . "\n" . implode("\n", $append));
    }
}

/**
 * Add a backlink to an enabled Hub pillar when the host content renderer calls
 * PLG_itemDisplay(). The host remains responsible for deciding where plugin
 * item-display fragments appear.
 *
 * @param string $id
 * @param string $type
 * @return array
 */
function plugin_itemdisplay_hub($id, $type)
{
    $html = HUB_renderItemPillarBacklinks($type, $id);

    if ($html === '') {
        return array(true);
    }

    return array(true, $html);
}
