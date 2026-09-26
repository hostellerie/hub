<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Inspect the optional static plugin.json modernization manifest.
 *
 * The manifest is recommended by the shared Memorandum contract but is not a
 * Geeklog Core requirement and therefore does not affect Hub readiness scores.
 *
 * @param string $plugin
 * @return array
 */
function HUB_metadataManifest($plugin)
{
    global $_CONF;

    $result = array(
        'available' => false,
        'valid' => false,
        'path' => '',
        'schema' => null,
        'id' => '',
        'name' => '',
        'icon' => '',
        'requires' => array(),
        'errors' => array(),
    );

    if (empty($_CONF['path'])) {
        return $result;
    }

    $path = rtrim($_CONF['path'], '/\\') . '/plugins/' . (string) $plugin . '/plugin.json';
    $result['path'] = $path;

    if (!is_file($path) || !is_readable($path)) {
        return $result;
    }

    $result['available'] = true;
    $json = @file_get_contents($path);
    if ($json === false || trim($json) === '') {
        $result['errors'][] = 'plugin.json is empty or unreadable.';
        return $result;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        $result['errors'][] = 'plugin.json is not valid JSON.';
        return $result;
    }

    if (!isset($data['schema']) || !is_numeric($data['schema']) || (int) $data['schema'] < 1) {
        $result['errors'][] = 'Missing or invalid schema.';
    } else {
        $result['schema'] = (int) $data['schema'];
    }

    foreach (array('id', 'name') as $key) {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            $result['errors'][] = 'Missing or invalid ' . $key . '.';
        } else {
            $result[$key] = trim($data[$key]);
        }
    }

    if ($result['id'] !== '' && strtolower($result['id']) !== strtolower((string) $plugin)) {
        $result['errors'][] = 'Manifest id does not match the installed plugin id.';
    }

    if (isset($data['icon'])) {
        if (!is_string($data['icon']) || trim($data['icon']) === '') {
            $result['errors'][] = 'icon must be a non-empty relative path.';
        } else {
            $icon = trim($data['icon']);
            if ($icon[0] === '/' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $icon)
                || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $icon)
            ) {
                $result['errors'][] = 'icon must remain inside the plugin package.';
            } else {
                $result['icon'] = $icon;
            }
        }
    }

    if (isset($data['requires'])) {
        if (!is_array($data['requires'])) {
            $result['errors'][] = 'requires must be an object.';
        } else {
            foreach (array('geeklog', 'php') as $key) {
                if (!isset($data['requires'][$key])) {
                    continue;
                }
                if (!is_string($data['requires'][$key]) || trim($data['requires'][$key]) === '') {
                    $result['errors'][] = 'requires.' . $key . ' must be a version string.';
                } else {
                    $result['requires'][$key] = trim($data['requires'][$key]);
                }
            }
        }
    }

    $result['valid'] = empty($result['errors']);

    return $result;
}

/**
 * Convert plugin.json inspection to audit-friendly lines.
 *
 * @param array $manifest
 * @return array
 */
function HUB_metadataManifestDetails($manifest)
{
    if (!is_array($manifest) || empty($manifest['available'])) {
        return array('— plugin.json: Not detected (recommended modernization metadata)');
    }

    if (empty($manifest['valid'])) {
        $details = array('? plugin.json detected but invalid');
        foreach ($manifest['errors'] as $error) {
            $details[] = 'Validation: ' . $error;
        }
        return $details;
    }

    $details = array(
        '✓ plugin.json — schema ' . (int) $manifest['schema']
            . ' — ' . $manifest['id'] . ' / ' . $manifest['name'],
    );

    if (!empty($manifest['icon'])) {
        $details[] = 'Icon: ' . $manifest['icon'];
    }
    if (!empty($manifest['requires']['geeklog'])) {
        $details[] = 'Requires Geeklog: ' . $manifest['requires']['geeklog'];
    }
    if (!empty($manifest['requires']['php'])) {
        $details[] = 'Requires PHP: ' . $manifest['requires']['php'];
    }

    return $details;
}
