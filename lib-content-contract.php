<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

if (!function_exists('HUB_capabilityDeclaration')) {
    require_once __DIR__ . '/lib-capabilities.php';
}

/**
 * Return source text for a loaded function when reflection can locate it.
 *
 * @param string $function
 * @return string
 */
function HUB_contentContractFunctionSource($function)
{
    if (!function_exists($function) || !class_exists('ReflectionFunction')) {
        return '';
    }

    try {
        $reflection = new ReflectionFunction($function);
        $file = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if (!$file || !$start || !$end || !is_readable($file)) {
            return '';
        }

        $lines = @file($file);
        if (!is_array($lines)) {
            return '';
        }

        return implode('', array_slice($lines, $start - 1, $end - $start + 1));
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Inspect evidence for the shared content collection contract.
 *
 * This intentionally does not invoke plugin_getiteminfo_*('*', ...). The audit
 * must not execute arbitrary provider queries merely to prove a convention.
 * Explicit capability declarations and source literals are reported as
 * declaration/source evidence, not runtime proof.
 *
 * @param string $plugin
 * @return array
 */
function HUB_contentContractEvidence($plugin)
{
    $plugin = strtolower((string) $plugin);
    $function = 'plugin_getiteminfo_' . $plugin;
    $source = HUB_contentContractFunctionSource($function);
    $declaration = HUB_capabilityDeclaration($plugin);

    $declaredCapabilities = array();
    if (!empty($declaration['valid']) && !empty($declaration['capabilities'])) {
        $declaredCapabilities = $declaration['capabilities'];
    }

    $evidence = array(
        'item_info' => function_exists($function),
        'collection_declared' => in_array('content.collection', $declaredCapabilities, true),
        'collection_source' => false,
        'since_source' => false,
        'limit_source' => false,
        'order_source' => false,
        'hits_source' => false,
        'hits_desc_source' => false,
    );

    if ($source !== '') {
        $evidence['collection_source'] = strpos($source, "'*'") !== false || strpos($source, '"*"') !== false;
        $evidence['since_source'] = preg_match('/[\'"]since[\'"]/', $source) === 1;
        $evidence['limit_source'] = preg_match('/[\'"]limit[\'"]/', $source) === 1;
        $evidence['order_source'] = preg_match('/[\'"]order[\'"]/', $source) === 1;
        $evidence['hits_source'] = preg_match('/[\'"]hits[\'"]/', $source) === 1;
        $evidence['hits_desc_source'] = strpos($source, 'hits-desc') !== false;
    }

    return $evidence;
}

/**
 * Convert shared content-contract evidence to audit lines.
 *
 * @param array $evidence
 * @return array
 */
function HUB_contentContractDetails($evidence)
{
    if (!is_array($evidence) || empty($evidence['item_info'])) {
        return array('— Content collection contract: not applicable without Item Info');
    }

    $details = array();

    if (!empty($evidence['collection_declared'])) {
        $details[] = '✓ Collection: declared via content.collection';
    } elseif (!empty($evidence['collection_source'])) {
        $details[] = '◐ Collection: "*" handling found in Item Info source';
    } else {
        $details[] = '? Collection: no declaration or "*" source evidence detected';
    }

    foreach (array(
        'since_source' => 'since',
        'limit_source' => 'limit',
        'order_source' => 'order',
    ) as $key => $label) {
        $details[] = (!empty($evidence[$key]) ? '◐ ' : '— ')
            . 'Collection option ' . $label . ': '
            . (!empty($evidence[$key]) ? 'source evidence detected' : 'not detected');
    }

    if (!empty($evidence['hits_desc_source'])) {
        $details[] = '◐ Popular-content ordering: hits-desc source evidence detected';
    } elseif (!empty($evidence['hits_source'])) {
        $details[] = '◐ Popularity field: hits source evidence detected; hits-desc not detected';
    } else {
        $details[] = '— Popularity field/order: not detected';
    }

    return $details;
}
