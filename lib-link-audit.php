<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return published articles assigned directly to a topic.
 *
 * This first implementation deliberately uses Geeklog core tables only. It does
 * not inspect data owned by third-party plugins.
 *
 * @param string $topicId
 * @return array
 */
function HUB_linkAuditArticlesByTopic($topicId)
{
    global $_TABLES;

    $rows = array();
    $topicId = addslashes((string) $topicId);
    $sql = "SELECT s.sid, s.title, s.introtext, s.bodytext, s.date, s.tid, t.topic "
         . "FROM {$_TABLES['stories']} AS s "
         . "LEFT JOIN {$_TABLES['topics']} AS t ON t.tid = s.tid "
         . "WHERE s.tid = '" . $topicId . "' "
         . "AND s.draft_flag = 0 AND s.status = 1 "
         . "ORDER BY s.date DESC";
    $result = DB_query($sql);

    while ($row = DB_fetchArray($result)) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Return topics available for the audit selector.
 *
 * @return array
 */
function HUB_linkAuditTopics()
{
    global $_TABLES;

    $topics = array();
    $result = DB_query("SELECT tid, topic FROM {$_TABLES['topics']} ORDER BY topic");
    while ($row = DB_fetchArray($result)) {
        $topics[] = $row;
    }

    return $topics;
}

/**
 * Return static pages available for the audit selector.
 *
 * @return array
 */
function HUB_linkAuditStaticPages()
{
    global $_TABLES;

    $pages = array();
    if (!isset($_TABLES['staticpage']) || !DB_checkTableExists('staticpage')) {
        return $pages;
    }

    $result = DB_query("SELECT sp_id, sp_title FROM {$_TABLES['staticpage']} ORDER BY sp_title");
    while ($row = DB_fetchArray($result)) {
        $pages[] = $row;
    }

    return $pages;
}

/**
 * Build the canonical public URL of a Geeklog static page.
 *
 * @param string $pageId
 * @return string
 */
function HUB_linkAuditStaticPageUrl($pageId)
{
    global $_CONF;

    $url = rtrim($_CONF['site_url'], '/') . '/staticpages/index.php?page=' . rawurlencode($pageId);

    return function_exists('COM_buildURL') ? COM_buildURL($url) : $url;
}

/**
 * Convert a link to a comparison key.
 *
 * Scheme and an optional www prefix are ignored so http/https and www/non-www
 * versions of the same internal link are recognized. Fragments are ignored.
 *
 * @param string $url
 * @param string $siteUrl
 * @return string
 */
function HUB_linkAuditUrlKey($url, $siteUrl)
{
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    if ($url === '') {
        return '';
    }

    if (strpos($url, '//') === 0) {
        $siteParts = parse_url($siteUrl);
        $scheme = isset($siteParts['scheme']) ? $siteParts['scheme'] : 'https';
        $url = $scheme . ':' . $url;
    } elseif ($url[0] === '/') {
        $siteParts = parse_url($siteUrl);
        if (!isset($siteParts['host'])) {
            return '';
        }
        $url = (isset($siteParts['scheme']) ? $siteParts['scheme'] : 'https')
             . '://' . $siteParts['host'] . $url;
    } elseif (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = rtrim($siteUrl, '/') . '/' . ltrim($url, '/');
    }

    $parts = parse_url($url);
    if ($parts === false || !isset($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $path = isset($parts['path']) ? preg_replace('#/+#', '/', $parts['path']) : '/';
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }

    $query = array();
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
        ksort($query);
    }

    return $host . '|' . $path . '|' . http_build_query($query, '', '&');
}

/**
 * Check whether stored article HTML contains a hyperlink to the target URL.
 *
 * @param string $content
 * @param string $targetUrl
 * @return bool
 */
function HUB_linkAuditContainsLink($content, $targetUrl)
{
    global $_CONF;

    $targetKey = HUB_linkAuditUrlKey($targetUrl, $_CONF['site_url']);
    if ($targetKey === '') {
        return false;
    }

    if (!preg_match_all('/<a\b[^>]*\bhref\s*=\s*([\'"])(.*?)\1/is', (string) $content, $matches)) {
        return false;
    }

    foreach ($matches[2] as $href) {
        if (HUB_linkAuditUrlKey($href, $_CONF['site_url']) === $targetKey) {
            return true;
        }
    }

    return false;
}

/**
 * Return published articles in a topic that do not link to the static page.
 *
 * @param string $topicId
 * @param string $pageId
 * @return array
 */
function HUB_linkAuditMissingArticles($topicId, $pageId)
{
    $missing = array();
    $targetUrl = HUB_linkAuditStaticPageUrl($pageId);

    foreach (HUB_linkAuditArticlesByTopic($topicId) as $article) {
        $content = (string) $article['introtext'] . "\n" . (string) $article['bodytext'];
        if (!HUB_linkAuditContainsLink($content, $targetUrl)) {
            $missing[] = $article;
        }
    }

    return $missing;
}
