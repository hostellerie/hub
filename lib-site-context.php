<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return normalized context for the currently selected Geeklog site.
 *
 * The active site is already selected by Geeklog before plugins run. Hub must
 * consume that context rather than implement a second HTTP_HOST/site router.
 *
 * @return array
 */
function HUB_siteContext()
{
    global $_CONF;

    $siteUrl = isset($_CONF['site_url']) ? trim((string) $_CONF['site_url']) : '';
    $adminUrl = isset($_CONF['site_admin_url']) ? trim((string) $_CONF['site_admin_url']) : '';
    $siteName = isset($_CONF['site_name']) ? (string) $_CONF['site_name'] : '';
    $language = isset($_CONF['language']) ? trim((string) $_CONF['language']) : '';

    $host = '';
    $scheme = '';
    $port = null;

    if ($siteUrl !== '') {
        $parts = parse_url($siteUrl);
        if (is_array($parts)) {
            $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
            $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
            $port = isset($parts['port']) ? (int) $parts['port'] : null;
        }
    }

    return array(
        'schema' => 1,
        'site_url' => $siteUrl,
        'site_admin_url' => $adminUrl,
        'host' => $host,
        'scheme' => $scheme,
        'port' => $port,
        'site_name' => $siteName,
        'language' => $language,
        'source' => 'active-geeklog-config',
    );
}

/**
 * Determine whether a resolved public URL belongs to the current site host.
 *
 * Relative URLs are current-site by definition. Absolute URLs are compared
 * against the host selected in $_CONF['site_url']; HTTP_HOST is intentionally
 * not consulted.
 *
 * @param string $url
 * @return array
 */
function HUB_siteUrlContext($url)
{
    $url = trim((string) $url);
    $site = HUB_siteContext();

    $result = array(
        'url' => $url,
        'kind' => 'unknown',
        'host' => '',
        'current_site' => false,
        'cross_site' => false,
    );

    if ($url === '') {
        return $result;
    }

    if (strpos($url, '//') !== 0 && preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
        $result['kind'] = 'relative';
        $result['host'] = isset($site['host']) ? (string) $site['host'] : '';
        $result['current_site'] = true;
        return $result;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $result;
    }

    $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
    $result['kind'] = 'absolute';
    $result['host'] = $host;

    if ($host !== '' && !empty($site['host'])) {
        $result['current_site'] = $host === strtolower((string) $site['host']);
        $result['cross_site'] = !$result['current_site'];
    }

    return $result;
}
