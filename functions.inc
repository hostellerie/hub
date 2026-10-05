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
require_once $hub_path . 'lib-graph.php';
require_once $hub_path . 'lib-lifecycle.php';
require_once $hub_path . 'lib-staticpages.php';

/**
 * Return the Hub icon used by Geeklog administration UIs.
 *
 * Hub does not ship a dedicated raster icon yet, so use Geeklog's native
 * generic plugin icon. This keeps Command & Control / Modern Workspace
 * integration theme-compatible on Geeklog 2.1.1 and 2.2.x.
 *
 * @return string
 */
function plugin_geticon_hub()
{
    global $_CONF;

    return $_CONF['layout_url'] . '/images/icons/plugins.png';
}

/**
 * Expose Hub in Geeklog Command & Control.
 *
 * @return array
 */
function plugin_cclabel_hub()
{
    global $_CONF;

    if (!SEC_hasRights('hub.admin')) {
        return array();
    }

    return array(
        'Hub',
        $_CONF['site_admin_url'] . '/plugins/hub/index.php',
        plugin_geticon_hub(),
        'plugins'
    );
}

function plugin_getcapabilities_hub()
{
    return array(
        'schema' => 1,
        'roles' => array('relationship', 'orchestrator', 'service'),
        'capabilities' => array('hub.affected.read', 'hub.context.read', 'hub.related.read', 'hub.pillar.read'),
        'lifecycle' => array(
            'emits' => array(),
            'listens' => array('item.saved', 'item.deleted'),
            'sub_type' => true
        )
    );
}

function plugin_wsEnabled_hub()
{
    return true;
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
    if (!is_object($template)
        || !method_exists($template, 'get_var')
        || !method_exists($template, 'set_var')
    ) {
        return;
    }

    if ($templatename === 'staticpage') {
        $pageId = (string) $template->get_var('sp_id');
        if ($pageId === '') {
            return;
        }

        $pillar = HUB_findPillar('staticpages', $pageId);
        if (!$pillar || empty($pillar['is_enabled'])) {
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

        return;
    }

    // Article fallback compatible with Geeklog 2.1.1 and 2.2.x.
    //
    // Geeklog invokes two template-hook families while rendering stories:
    // storytext/featuredstorytext/archivestorytext and then
    // article/featuredarticle/archivearticle. Cached stories can skip the
    // first family, so Hub accepts both.
    $hubArticleTemplates = array(
        'storytext',
        'featuredstorytext',
        'archivestorytext',
        'article',
        'featuredarticle',
        'archivearticle',
    );

    if (in_array($templatename, $hubArticleTemplates, true)) {
        $storyId = (string) $template->get_var('story_id');
        if ($storyId === '' && isset($_GET['story'])) {
            $storyId = HUB_normalizeObjectId($_GET['story']);
        }

        $displayType = (string) $template->get_var('story_display_type');
        if ($displayType === '') {
            $displayType = (string) $template->get_var('display_type');
        }

        $script = isset($_SERVER['PHP_SELF']) ? basename((string) $_SERVER['PHP_SELF']) : '';
        $isFullArticle = ($displayType === 'n')
            || ($displayType === '' && $script === 'article.php');

        if (!$isFullArticle || $storyId === '') {
            return;
        }

        $backlinksHtml = HUB_renderItemPillarBacklinks('article', $storyId);
        if ($backlinksHtml === '') {
            return;
        }

        // Render the Hub backlink once inside the article body. The Core
        // plugin_itemdisplay footer slot remains untouched to avoid duplicate
        // output on themes that render both locations.
        $storyText = (string) $template->get_var('story_text_no_br');
        if ($storyText !== '' && strpos($storyText, 'hub-pillar-backlinks') === false) {
            $template->set_var('story_text_no_br', $storyText . "\n" . $backlinksHtml);
        }

        $storyIntro = (string) $template->get_var('story_introtext');
        if ($storyIntro !== '' && strpos($storyIntro, 'hub-pillar-backlinks') === false) {
            $template->set_var('story_introtext', $storyIntro . "\n" . $backlinksHtml);
        }
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
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);

    if ($type === 'story') {
        $type = 'article';
    }

    // Articles and Static Pages already have dedicated Hub integration paths.
    // Do not emit the same backlink again if one of those providers also calls
    // PLG_itemDisplay(), otherwise themes may display duplicate Hub blocks.
    if ($type === 'article' || $type === 'staticpages' || $type === '' || $id === '') {
        return array(true);
    }

    $html = HUB_renderItemPillarBacklinks($type, $id);

    if ($html === '') {
        return array(true);
    }

    return array(true, $html);
}


/**
 * React to Geeklog item-save lifecycle notifications.
 *
 * Geeklog 2.1.1 calls listeners with the legacy arguments while 2.2.2 may
 * additionally provide a subtype. Optional parameters keep the callback
 * compatible with both generations.
 *
 * @param string $id
 * @param string $type
 * @param string $old_id
 * @param string $sub_type
 * @return void
 */
function plugin_itemsaved_hub($id, $type, $old_id = '', $sub_type = '')
{
    HUB_handleItemSaved($id, $type, $old_id, $sub_type);
}

/**
 * React to Geeklog item-delete lifecycle notifications.
 *
 * Hub deliberately preserves stored relationships when a provider removes an
 * object. The existing integrity diagnostics then expose the unresolved stable
 * identity instead of silently destroying editorial intent.
 *
 * @param string $id
 * @param string $type
 * @param string $sub_type
 * @return void
 */
function plugin_itemdeleted_hub($id, $type, $sub_type = '')
{
    HUB_handleItemDeleted($id, $type, $sub_type);
}
