<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Read and normalize a plugin's generic capability declaration.
 *
 * The declaration is descriptive evidence supplied by the plugin. It does not
 * replace runtime capability detection, source evidence, permissions or ACLs.
 *
 * Expected schema:
 * array(
 *     'schema' => 1,
 *     'roles' => array('content', 'service'),
 *     'capabilities' => array('content.read', 'dashboard.summary')
 * )
 *
 * @param string $plugin
 * @return array
 */
function HUB_capabilityDeclaration($plugin)
{
    $result = array(
        'available' => false,
        'valid' => false,
        'function' => '',
        'schema' => null,
        'roles' => array(),
        'capabilities' => array(),
        'errors' => array(),
    );

    $function = 'plugin_getcapabilities_' . (string) $plugin;
    $result['function'] = $function;

    if (!function_exists($function)) {
        return $result;
    }

    $result['available'] = true;

    if (class_exists('ReflectionFunction')) {
        try {
            $reflection = new ReflectionFunction($function);
            if ($reflection->getNumberOfRequiredParameters() > 0) {
                $result['errors'][] = 'Capability declaration must not require parameters.';
                return $result;
            }
        } catch (Exception $e) {
            $result['errors'][] = 'Unable to inspect capability declaration signature.';
            return $result;
        }
    }

    try {
        $declaration = call_user_func($function);
    } catch (Exception $e) {
        $result['errors'][] = 'Capability declaration raised an exception.';
        return $result;
    }

    if (!is_array($declaration)) {
        $result['errors'][] = 'Capability declaration must return an array.';
        return $result;
    }

    if (!isset($declaration['schema']) || !is_numeric($declaration['schema']) || (int) $declaration['schema'] < 1) {
        $result['errors'][] = 'Missing or invalid schema version.';
    } else {
        $result['schema'] = (int) $declaration['schema'];
    }

    foreach (array('roles', 'capabilities') as $key) {
        if (!isset($declaration[$key])) {
            continue;
        }
        if (!is_array($declaration[$key])) {
            $result['errors'][] = $key . ' must be an array.';
            continue;
        }

        $values = array();
        foreach ($declaration[$key] as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $values[$value] = true;
            }
        }
        $values = array_keys($values);
        natcasesort($values);
        $result[$key] = array_values($values);
    }

    if (empty($result['roles']) && empty($result['capabilities'])) {
        $result['errors'][] = 'Declaration contains no roles or capabilities.';
    }

    $result['valid'] = empty($result['errors']);

    return $result;
}

/**
 * Convert a normalized declaration to audit-friendly evidence lines.
 *
 * @param array $declaration
 * @return array
 */
function HUB_capabilityDeclarationDetails($declaration)
{
    if (!is_array($declaration) || empty($declaration['available'])) {
        return array();
    }

    $details = array();

    if (!empty($declaration['valid'])) {
        $details[] = '✓ ' . $declaration['function'] . '() — schema ' . (int) $declaration['schema'];

        if (!empty($declaration['roles'])) {
            $details[] = 'Declared roles: ' . implode(', ', $declaration['roles']);
        }
        if (!empty($declaration['capabilities'])) {
            foreach ($declaration['capabilities'] as $capability) {
                $details[] = 'Capability: ' . $capability;
            }
        }
    } else {
        $details[] = '? ' . $declaration['function'] . '() was detected but its declaration is invalid.';
        foreach ($declaration['errors'] as $error) {
            $details[] = 'Validation: ' . $error;
        }
    }

    return $details;
}


/**
 * Check whether a valid plugin declaration advertises a capability.
 *
 * This is declaration lookup only; it is not proof that the capability is
 * currently usable or authorized for the caller.
 *
 * @param string $plugin
 * @param string $capability
 * @return bool
 */
function HUB_capabilitySupports($plugin, $capability)
{
    $capability = trim((string) $capability);
    if ($capability === '') {
        return false;
    }

    $declaration = HUB_capabilityDeclaration($plugin);
    if (empty($declaration['valid']) || empty($declaration['capabilities'])) {
        return false;
    }

    return in_array($capability, $declaration['capabilities'], true);
}

/**
 * Return the roles from a valid generic capability declaration.
 *
 * @param string $plugin
 * @return array
 */
function HUB_capabilityDeclaredRoles($plugin)
{
    $declaration = HUB_capabilityDeclaration($plugin);

    return !empty($declaration['valid']) && !empty($declaration['roles'])
        ? $declaration['roles']
        : array();
}
