<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Reconcile declared shared capabilities with implementation evidence that Hub
 * can safely detect without invoking provider business operations.
 *
 * A declaration remains descriptive metadata. These checks only indicate
 * whether the normal Memorandum mapping can be observed through loaded Geeklog
 * callbacks, bounded service entry points or source evidence.
 *
 * @param string $plugin
 * @param array  $declaration
 * @param array  $caps
 * @param array  $sourceFacts
 * @param array  $serviceCatalogue
 * @param array  $contentContract
 * @return array
 */
function HUB_capabilityImplementationEvidence(
    $plugin,
    $declaration,
    $caps,
    $sourceFacts,
    $serviceCatalogue,
    $contentContract
) {
    $result = array(
        'details' => array(),
        'recommendations' => array(),
    );

    if (!is_array($declaration)
        || empty($declaration['valid'])
        || empty($declaration['capabilities'])
        || !is_array($declaration['capabilities'])
    ) {
        return $result;
    }

    $serviceActions = array();
    if (!empty($serviceCatalogue['actions']) && is_array($serviceCatalogue['actions'])) {
        foreach ($serviceCatalogue['actions'] as $service) {
            if (!empty($service['action'])) {
                $serviceActions[strtolower((string) $service['action'])] = true;
            }
        }
    }

    $hasSaved = !empty($sourceFacts['item_saved']);
    $hasDeleted = !empty($sourceFacts['item_deleted']);

    foreach ($declaration['capabilities'] as $capability) {
        $capability = strtolower(trim((string) $capability));
        if ($capability === '') {
            continue;
        }

        switch ($capability) {
            case 'content.read':
                if (!empty($caps['item_info'])) {
                    $result['details'][] = '✓ content.read → Item Info detected';
                } else {
                    $result['details'][] = '? content.read declared but Item Info was not detected';
                    $result['recommendations'][] = 'Declared content.read should normally be backed by plugin_getiteminfo_' . $plugin . '().';
                }
                break;

            case 'content.collection':
                if (empty($caps['item_info'])) {
                    $result['details'][] = '? content.collection declared but Item Info was not detected';
                    $result['recommendations'][] = 'Declared content.collection requires an addressable collection surface such as plugin_getiteminfo_' . $plugin . "('*', ...).";
                } elseif (!empty($contentContract['collection_source'])) {
                    $result['details'][] = '◐ content.collection → "*" handling found in Item Info source';
                } else {
                    $result['details'][] = '? content.collection declared; Item Info exists but "*" handling was not source-proven';
                }
                break;

            case 'content.search':
                if (!empty($caps['search'])) {
                    $result['details'][] = '✓ content.search → Geeklog search callback detected';
                } else {
                    $result['details'][] = '? content.search declared without a detected native search callback';
                    $result['recommendations'][] = 'Review content.search: expose plugin_dopluginsearch_' . $plugin . '() or document the shared alternative implementation surface.';
                }
                break;

            case 'content.url.resolve':
                if (!empty($caps['id_to_url'])) {
                    $result['details'][] = '✓ content.url.resolve → ID-to-URL callback detected';
                } elseif (!empty($caps['item_info'])) {
                    $result['details'][] = '◐ content.url.resolve → Item Info exists; canonical URL field remains provider-owned';
                } else {
                    $result['details'][] = '? content.url.resolve declared without ID-to-URL or Item Info evidence';
                    $result['recommendations'][] = 'Declared content.url.resolve should be backed by plugin_idtourl_' . $plugin . '() and/or a canonical URL from Item Info.';
                }
                break;

            case 'content.lifecycle':
                if ($hasSaved && $hasDeleted) {
                    $result['details'][] = '◐ content.lifecycle → save/delete emissions found in source';
                } elseif ($hasSaved || $hasDeleted) {
                    $result['details'][] = '? content.lifecycle declared but only part of the save/delete pair was source-detected';
                    $result['recommendations'][] = 'Review declared content.lifecycle: Hub could not source-detect both PLG_itemSaved() and PLG_itemDeleted().';
                } else {
                    $result['details'][] = '? content.lifecycle declared without source-detected save/delete emissions';
                    $result['recommendations'][] = 'Review declared content.lifecycle: emit PLG_itemSaved() / PLG_itemDeleted() on addressable content mutations.';
                }
                break;

            case 'content.related':
                if (!empty($caps['related_items'])) {
                    $result['details'][] = '✓ content.related → Related Items callback detected';
                } else {
                    $result['details'][] = '? content.related declared without a detected Related Items callback';
                    $result['recommendations'][] = 'Review content.related: expose plugin_getrelateditems_' . $plugin . '() or document the shared relationship service used instead.';
                }
                break;

            case 'dashboard.summary':
                if (isset($serviceActions['dashboard_summary'])) {
                    $result['details'][] = '✓ dashboard.summary → dashboard_summary service detected';
                } else {
                    $result['details'][] = '? dashboard.summary declared without a detected dashboard_summary service';
                    $result['recommendations'][] = 'Declared dashboard.summary should normally expose the bounded dashboard_summary service.';
                }
                break;

            default:
                $action = $capability;
                $prefix = strtolower((string) $plugin) . '.';
                if (strpos($action, $prefix) === 0) {
                    $action = substr($action, strlen($prefix));
                }
                $action = str_replace('.', '_', $action);

                if ($action !== '' && isset($serviceActions[$action])) {
                    $result['details'][] = '✓ ' . $capability . ' → service action ' . $action . ' detected';
                } else {
                    $result['details'][] = '— ' . $capability . ' → declaration accepted; implementation surface not inferred';
                }
                break;
        }
    }

    $result['details'] = array_values(array_unique($result['details']));
    $result['recommendations'] = array_values(array_unique($result['recommendations']));

    return $result;
}
