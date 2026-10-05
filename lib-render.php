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


/**
 * Invoke one provider-owned render service discovered by Hub.
 *
 * Hub validates only that the action belongs to the provider render catalogue.
 * The owning plugin remains responsible for authorization, filtering,
 * rendering, presentation dependencies and output semantics.
 *
 * @param string $plugin
 * @param string $action
 * @param array  $args
 * @return array
 */
function HUB_renderInvoke($plugin, $action, $args = array())
{
    $plugin = strtolower(trim((string) $plugin));
    $action = strtolower(trim((string) $action));
    $result = array(
        'available' => false,
        'status' => null,
        'output' => array(),
        'messages' => array(),
    );

    if ($plugin === '' || $action === '' || !function_exists('PLG_invokeService')) {
        return $result;
    }

    $catalogue = HUB_renderCatalogue($plugin);
    $allowed = false;
    if (!empty($catalogue['services']) && is_array($catalogue['services'])) {
        foreach ($catalogue['services'] as $service) {
            if (isset($service['action'])
                && strtolower((string) $service['action']) === $action
            ) {
                $allowed = true;
                break;
            }
        }
    }

    if (!$allowed) {
        return $result;
    }

    $output = array();
    $messages = array();
    $status = PLG_invokeService(
        $plugin,
        $action,
        is_array($args) ? $args : array(),
        $output,
        $messages
    );

    $result['available'] = true;
    $result['status'] = $status;
    $result['output'] = is_array($output) ? $output : array();
    $result['messages'] = is_array($messages) ? $messages : array();

    return $result;
}

/**
 * Return the single unambiguous provider-owned render action, if any.
 *
 * Hub does not guess between several specialized renderers. A provider with
 * multiple render actions must be selected explicitly by a future policy/UI.
 *
 * @param string $plugin
 * @return string
 */
function HUB_renderSingleAction($plugin)
{
    $catalogue = HUB_renderCatalogue($plugin);
    if (empty($catalogue['services']) || !is_array($catalogue['services'])) {
        return '';
    }

    $actions = array();
    foreach ($catalogue['services'] as $service) {
        if (!empty($service['action'])) {
            $actions[] = strtolower((string) $service['action']);
        }
    }

    $actions = array_values(array_unique($actions));

    return count($actions) === 1 ? $actions[0] : '';
}

/**
 * Render approved Hub relation identities through one provider-owned renderer.
 *
 * @param string $plugin
 * @param array  $relations
 * @param array  $context
 * @return array
 */
function HUB_renderApprovedProviderRelations($plugin, $relations, $context = array())
{
    $plugin = strtolower(trim((string) $plugin));
    $relations = is_array($relations) ? $relations : array();

    $action = HUB_renderSingleAction($plugin);
    if ($action === '' || empty($relations)) {
        return array(
            'available' => false,
            'status' => null,
            'output' => array(),
            'messages' => array(),
        );
    }

    $items = array();
    foreach ($relations as $relation) {
        if (!is_array($relation)
            || empty($relation['item_type'])
            || empty($relation['item_id'])
            || HUB_normalizeObjectType($relation['item_type']) !== $plugin
        ) {
            continue;
        }

        $items[] = array(
            'id' => HUB_normalizeObjectId($relation['item_id']),
            'relation_role' => isset($relation['relation_role'])
                ? HUB_normalizeRelationRole($relation['relation_role'])
                : 'related',
            'position' => isset($relation['position']) ? (int) $relation['position'] : 0,
        );
    }

    if (empty($items)) {
        return array(
            'available' => false,
            'status' => null,
            'output' => array(),
            'messages' => array(),
        );
    }

    return HUB_renderInvoke(
        $plugin,
        $action,
        array(
            'context' => is_array($context) ? $context : array(),
            'items' => $items,
        )
    );
}
