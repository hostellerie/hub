<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return a stable fingerprint for the current interoperability environment.
 *
 * The fingerprint intentionally uses plugin names/versions and runtime
 * versions, not source mtimes. During development, Refresh audit explicitly
 * bypasses the cache when code changes without a version bump.
 *
 * @return string
 */
function HUB_auditCacheFingerprint()
{
    global $_PLUGINS, $_HUB_PLUGIN;

    $plugins = array();

    if (is_array($_PLUGINS)) {
        foreach ($_PLUGINS as $plugin) {
            $plugin = (string) $plugin;
            if ($plugin === '') {
                continue;
            }

            $plugins[$plugin] = function_exists('HUB_auditPluginVersion')
                ? HUB_auditPluginVersion($plugin)
                : '-';
        }
    }

    ksort($plugins);

    $data = array(
        'plugins' => $plugins,
        'hub' => isset($_HUB_PLUGIN['pi_version']) ? (string) $_HUB_PLUGIN['pi_version'] : '',
        'geeklog' => defined('VERSION') ? (string) VERSION : '',
        'php' => PHP_VERSION,
    );

    return sha1(serialize($data));
}

/**
 * Remove cached interoperability audit data.
 *
 * @return void
 */
function HUB_auditCacheClear()
{
    if (function_exists('CACHE_remove_instance')) {
        CACHE_remove_instance('hub_audit_data__');
    }
}

/**
 * Return enriched audit rows, using Geeklog's native cache when available.
 *
 * Cached data is shared by the admin HTML view and Markdown export. The cache
 * payload contains its own timestamp/fingerprint so expiration and plugin
 * changes remain under Hub's control.
 *
 * @param bool  $forceRefresh
 * @param int   $ttl
 * @param array $meta
 * @return array
 */
function HUB_auditCachedRows($forceRefresh = false, $ttl = 600, &$meta = null)
{
    $ttl = max(0, (int) $ttl);
    $fingerprint = HUB_auditCacheFingerprint();
    $instance = 'hub_audit_data__global';
    $now = time();

    $meta = array(
        'cached' => false,
        'created' => $now,
        'age' => 0,
        'ttl' => $ttl,
        'fingerprint' => $fingerprint,
    );

    if ($forceRefresh) {
        HUB_auditCacheClear();
    } elseif ($ttl > 0 && function_exists('CACHE_check_instance')) {
        $cached = CACHE_check_instance($instance, true, true);

        if (is_string($cached) && $cached !== '') {
            $payload = @unserialize($cached);

            if (is_array($payload)
                && isset($payload['fingerprint'], $payload['created'], $payload['rows'])
                && $payload['fingerprint'] === $fingerprint
                && is_array($payload['rows'])
            ) {
                $age = max(0, $now - (int) $payload['created']);

                if ($age <= $ttl) {
                    $meta['cached'] = true;
                    $meta['created'] = (int) $payload['created'];
                    $meta['age'] = $age;

                    return $payload['rows'];
                }
            }
        }
    }

    $rows = HUB_statsEnrichRows(HUB_roleEnrichRows(HUB_auditActivePlugins()));

    if ($ttl > 0 && function_exists('CACHE_create_instance')) {
        $payload = array(
            'fingerprint' => $fingerprint,
            'created' => $now,
            'rows' => $rows,
        );

        CACHE_create_instance($instance, serialize($payload), true, true);
    }

    return $rows;
}
