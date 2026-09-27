<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return published articles assigned to any of the supplied topics.
 *
 * A single query is used for all topic ids. Articles assigned to more than one
 * matching topic are returned only once, with the matching topics preserved in
 * the `hub_topics` element for audit explanations.
 *
 * This implementation deliberately uses Geeklog core tables only. It does not
 * inspect data owned by third-party plugins.
 *
 * @param array $topicIds
 * @return array
 */
function HUB_linkAuditArticlesByTopics(array $topicIds)
{
    global $_TABLES;

    $articles = array();

    if (empty($_TABLES['stories']) || empty($_TABLES['topic_assignments']) || empty($_TABLES['topics'])) {
        return array();
    }

    $escapedTopicIds = array();
    foreach ($topicIds as $topicId) {
        $topicId = (string) $topicId;
        if ($topicId === '') {
            continue;
        }

        $escapedTopicIds[] = function_exists('DB_escapeString')
            ? DB_escapeString($topicId)
            : addslashes($topicId);
    }

    $escapedTopicIds = array_values(array_unique($escapedTopicIds));
    if (empty($escapedTopicIds)) {
        return array();
    }

    $quotedTopicIds = "'" . implode("','", $escapedTopicIds) . "'";

    $sql = "SELECT s.sid, s.title, s.introtext, s.bodytext, s.date, s.hits, s.comments, ta.tid, t.topic "
         . "FROM {$_TABLES['stories']} AS s "
         . "INNER JOIN {$_TABLES['topic_assignments']} AS ta "
         . "ON ta.type = 'article' AND ta.id = s.sid "
         . "INNER JOIN {$_TABLES['topics']} AS t ON t.tid = ta.tid "
         . "WHERE ta.tid IN (" . $quotedTopicIds . ") "
         . "AND s.draft_flag = 0 AND s.date <= NOW() ";

    if (function_exists('COM_getPermSQL')) {
        $sql .= COM_getPermSQL('AND', 0, 2, 's');
    }
    if (function_exists('COM_getTopicSQL')) {
        $sql .= COM_getTopicSQL('AND', 0, 'ta');
    }
    if (function_exists('COM_getLangSQL')) {
        $sql .= COM_getLangSQL('sid', 'AND', 's');
    }

    $sql .= " ORDER BY s.date DESC, t.topic ASC";

    $result = DB_query($sql, 1);
    if ($result === false) {
        return array();
    }

    while ($article = DB_fetchArray($result)) {
        if (!isset($article['sid'])) {
            continue;
        }

        $sid = (string) $article['sid'];
        $tid = isset($article['tid']) ? (string) $article['tid'] : '';
        $topic = isset($article['topic']) && $article['topic'] !== ''
            ? (string) $article['topic']
            : $tid;

        if (!isset($articles[$sid])) {
            $article['hub_topics'] = array();
            $articles[$sid] = $article;
        }

        if ($tid !== '') {
            $articles[$sid]['hub_topics'][$tid] = $topic;
        }
    }

    foreach ($articles as &$article) {
        if (!empty($article['hub_topics'])) {
            natcasesort($article['hub_topics']);
        }
    }
    unset($article);

    return array_values($articles);
}

/**
 * Return static pages available for the audit selector.
 *
 * The Static Pages plugin registers its physical table in $_TABLES['staticpage'].
 * Do not depend on DB_checkTableExists(), whose availability/behaviour differs
 * between supported Geeklog versions.
 *
 * @return array
 */
function HUB_linkAuditStaticPages()
{
    global $_TABLES;

    $pages = array();
    if (empty($_TABLES['staticpage'])) {
        return $pages;
    }

    $table = $_TABLES['staticpage'];
    $sql = "SELECT sp.sp_id, sp.sp_title FROM {$table} AS sp "
         . "WHERE sp.draft_flag = 0 AND sp.template_flag = 0 ";

    if (function_exists('COM_getPermSQL')) {
        $sql .= COM_getPermSQL('AND', 0, 2, 'sp');
    }
    if (function_exists('COM_getLangSQL')) {
        $sql .= COM_getLangSQL('sp_id', 'AND', 'sp');
    }

    $sql .= " ORDER BY sp.sp_title";

    $result = DB_query($sql, 1);
    if ($result === false) {
        return $pages;
    }

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
    } elseif (isset($url[0]) && $url[0] === '/') {
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
 * Return published articles in any supplied topic that do not link to the
 * selected Static Page.
 *
 * @param array $topicIds
 * @param string $pageId
 * @return array
 */
function HUB_linkAuditMissingArticlesForTopics(array $topicIds, $pageId)
{
    $missing = array();
    $targetUrl = HUB_linkAuditStaticPageUrl($pageId);

    foreach (HUB_linkAuditArticlesByTopics($topicIds) as $article) {
        $introtext = isset($article['introtext']) ? (string) $article['introtext'] : '';
        $bodytext = isset($article['bodytext']) ? (string) $article['bodytext'] : '';
        $content = $introtext . "\n" . $bodytext;
        if (!HUB_linkAuditContainsLink($content, $targetUrl)) {
            $missing[] = $article;
        }
    }

    return $missing;
}


/**
 * Return a human-readable age for a published article.
 *
 * @param string $date
 * @param int|null $now
 * @return string
 */
function HUB_linkAuditArticleAge($date, $now = null)
{
    $timestamp = strtotime((string) $date);
    if ($timestamp === false) {
        return '';
    }

    $now = $now === null ? time() : (int) $now;
    $seconds = max(0, $now - $timestamp);
    $days = (int) floor($seconds / 86400);

    if ($days < 1) {
        return '< 1 day';
    }
    if ($days < 30) {
        return $days . ' day' . ($days === 1 ? '' : 's');
    }

    $months = (int) floor($days / 30);
    if ($months < 12) {
        return $months . ' month' . ($months === 1 ? '' : 's');
    }

    $years = (int) floor($days / 365);
    return $years . ' year' . ($years === 1 ? '' : 's');
}

/**
 * Sort link-audit article rows by an explicit administrator-selected signal.
 *
 * Supported keys: topics, views, comments, date, title.
 *
 * @param array $articles
 * @param string $sort
 * @param string $direction
 * @return array
 */
function HUB_linkAuditSortArticles(array $articles, $sort, $direction = 'desc')
{
    $allowed = array('topics', 'views', 'comments', 'date', 'title');
    $sort = in_array($sort, $allowed, true) ? $sort : 'views';
    $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

    usort($articles, function ($left, $right) use ($sort, $direction) {
        switch ($sort) {
            case 'topics':
                $a = !empty($left['hub_topics']) && is_array($left['hub_topics']) ? count($left['hub_topics']) : 0;
                $b = !empty($right['hub_topics']) && is_array($right['hub_topics']) ? count($right['hub_topics']) : 0;
                break;
            case 'comments':
                $a = isset($left['comments']) ? (int) $left['comments'] : 0;
                $b = isset($right['comments']) ? (int) $right['comments'] : 0;
                break;
            case 'date':
                $a = isset($left['date']) ? strtotime((string) $left['date']) : 0;
                $b = isset($right['date']) ? strtotime((string) $right['date']) : 0;
                break;
            case 'title':
                $a = isset($left['title']) ? html_entity_decode((string) $left['title'], ENT_QUOTES, 'UTF-8') : '';
                $b = isset($right['title']) ? html_entity_decode((string) $right['title'], ENT_QUOTES, 'UTF-8') : '';
                $cmp = strcasecmp($a, $b);
                return $direction === 'asc' ? $cmp : -$cmp;
            case 'views':
            default:
                $a = isset($left['hits']) ? (int) $left['hits'] : 0;
                $b = isset($right['hits']) ? (int) $right['hits'] : 0;
                break;
        }

        if ($a == $b) {
            $titleA = isset($left['title']) ? (string) $left['title'] : '';
            $titleB = isset($right['title']) ? (string) $right['title'] : '';
            return strcasecmp($titleA, $titleB);
        }

        $cmp = ($a < $b) ? -1 : 1;
        return $direction === 'asc' ? $cmp : -$cmp;
    });

    return $articles;
}
