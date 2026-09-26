<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Return user-defined functions matching a plugin service suffix.
 *
 * @param string $plugin
 * @return array
 */
function HUB_serviceFunctions($plugin)
{
    $matches = array();
    $defined = get_defined_functions();

    if (!isset($defined['user']) || !is_array($defined['user'])) {
        return $matches;
    }

    $suffix = '_' . strtolower((string) $plugin);
    foreach ($defined['user'] as $function) {
        $lower = strtolower($function);
        if (strpos($lower, 'service_') !== 0) {
            continue;
        }
        if (substr($lower, -strlen($suffix)) !== $suffix) {
            continue;
        }
        $matches[] = $function;
    }

    sort($matches);

    return array_values(array_unique($matches));
}

/**
 * Return a PHP-style function signature without invoking the function.
 *
 * @param string $function
 * @return string
 */
function HUB_serviceFunctionSignature($function)
{
    if (!function_exists($function) || !class_exists('ReflectionFunction')) {
        return $function . '()';
    }

    try {
        $reflection = new ReflectionFunction($function);
        $parts = array();

        foreach ($reflection->getParameters() as $parameter) {
            $part = $parameter->isPassedByReference() ? '&' : '';
            $part .= '$' . $parameter->getName();
            if ($parameter->isOptional()) {
                $part .= ' = ...';
            }
            $parts[] = $part;
        }

        return $function . '(' . implode(', ', $parts) . ')';
    } catch (Exception $e) {
        return $function . '()';
    }
}

/**
 * Build a normalized catalogue of service entry points exposed at runtime.
 *
 * This only describes loaded service functions. It does not call them and it
 * does not bypass the owning plugin's ACL or service dispatcher checks.
 *
 * @param string $plugin
 * @return array
 */
function HUB_serviceCatalogue($plugin)
{
    $plugin = strtolower((string) $plugin);
    $dispatcher = 'plugin_wsEnabled_' . $plugin;
    $suffix = '_' . $plugin;

    $catalogue = array(
        'plugin' => $plugin,
        'dispatcher' => function_exists($dispatcher),
        'dispatcher_function' => function_exists($dispatcher) ? $dispatcher : '',
        'actions' => array(),
    );

    foreach (HUB_serviceFunctions($plugin) as $function) {
        $lower = strtolower($function);
        $action = substr($lower, strlen('service_'), -strlen($suffix));

        $catalogue['actions'][] = array(
            'action' => $action,
            'function' => $function,
            'signature' => HUB_serviceFunctionSignature($function),
        );
    }

    return $catalogue;
}

/**
 * Convert a service catalogue to audit-friendly lines.
 *
 * @param array $catalogue
 * @return array
 */
function HUB_serviceCatalogueDetails($catalogue)
{
    if (!is_array($catalogue)) {
        return array();
    }

    $details = array();

    if (!empty($catalogue['dispatcher_function'])) {
        $details[] = 'Dispatcher: ' . $catalogue['dispatcher_function'] . '()';
    }

    if (!empty($catalogue['actions']) && is_array($catalogue['actions'])) {
        foreach ($catalogue['actions'] as $service) {
            if (!is_array($service)) {
                continue;
            }
            $label = isset($service['signature']) ? $service['signature'] : '';
            if (!empty($service['action'])) {
                $label .= ' [action: ' . $service['action'] . ']';
            }
            if ($label !== '') {
                $details[] = $label;
            }
        }
    }

    return $details;
}
