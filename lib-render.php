<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

if (!function_exists('HUB_capabilityDeclaration')) {
    require_once __DIR__ . '/lib-capabilities.php';
}
if (!function_exists('HUB_serviceCatalogue')) {
    require_once __DIR__ . '/lib-services.php';
}

/**
 * Discover plugin-owned render entry points without invoking them.
 *
 * A render capability may be declared generically (for example
 * "maps.marker.render") or exposed as a service action ending in "_render".
 * Hub only catalogues these entry points; the owning plugin remains
 * authoritative for permissions, arguments, rendering and output format.
 *
 * @param string $plugin
 * @return array
 */
function HUB_renderCatalogue($plugin)
{
    $plugin = strtolower((string) $plugin);
    $catalogue = array(
        'plugin' => $plugin,
        'declared' => array(),
        'services' => array(),
    );

    $declaration = HUB_capabilityDeclaration($plugin);
    if (!empty($declaration['valid']) && !empty($declaration['capabilities'])) {
        foreach ($declaration['capabilities'] as $capability) {
            $capability = strtolower(trim((string) $capability));
            if ($capability !== '' && substr($capability, -7) === '.render') {
                $catalogue['declared'][] = $capability;
            }
        }
    }

    $services = HUB_serviceCatalogue($plugin);
    if (!empty($services['actions']) && is_array($services['actions'])) {
        foreach ($services['actions'] as $service) {
            $action = isset($service['action']) ? strtolower((string) $service['action']) : '';
            if ($action === 'render' || substr($action, -7) === '_render') {
                $catalogue['services'][] = $service;
            }
        }
    }

    $catalogue['declared'] = array_values(array_unique($catalogue['declared']));
    sort($catalogue['declared']);

    return $catalogue;
}

/**
 * Convert render discovery to audit-friendly lines.
 *
 * @param array $catalogue
 * @return array
 */
function HUB_renderCatalogueDetails($catalogue)
{
    if (!is_array($catalogue)) {
        return array();
    }

    $details = array();

    if (!empty($catalogue['declared'])) {
        foreach ($catalogue['declared'] as $capability) {
            $details[] = 'Declared render capability: ' . $capability;
        }
    }

    if (!empty($catalogue['services'])) {
        foreach ($catalogue['services'] as $service) {
            $label = isset($service['signature']) ? (string) $service['signature'] : '';
            if (!empty($service['action'])) {
                $label .= ' [action: ' . $service['action'] . ']';
            }
            if ($label !== '') {
                $details[] = 'Render service: ' . $label;
            }
        }
    }

    return $details;
}

/**
 * Return whether Hub can discover at least one plugin-owned render entry point.
 *
 * @param string $plugin
 * @return bool
 */
function HUB_renderAvailable($plugin)
{
    $catalogue = HUB_renderCatalogue($plugin);

    return !empty($catalogue['declared']) || !empty($catalogue['services']);
}
