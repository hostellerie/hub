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
 *     'capabilities' => array('content.read', 'dashboard.summary'),
 *     'lifecycle' => array(
 *         'emits' => array('item.saved', 'item.deleted'),
 *         'listens' => array('item.saved'),
 *         'sub_type' => true
 *     )
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
        'lifecycle' => array(
            'emits' => array(),
            'listens' => array(),
            'sub_type' => null,
        ),
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

    if (isset($declaration['lifecycle'])) {
        if (!is_array($declaration['lifecycle'])) {
            $result['errors'][] = 'lifecycle must be an array.';
        } else {
            foreach (array('emits', 'listens') as $key) {
                if (!isset($declaration['lifecycle'][$key])) {
                    continue;
                }
                if (!is_array($declaration['lifecycle'][$key])) {
                    $result['errors'][] = 'lifecycle.' . $key . ' must be an array.';
                    continue;
                }

                $values = array();
                foreach ($declaration['lifecycle'][$key] as $value) {
                    if (!is_string($value)) {
                        continue;
                    }
                    $value = strtolower(trim($value));
                    if ($value !== '') {
                        $values[$value] = true;
                    }
                }
                $values = array_keys($values);
                sort($values);
                $result['lifecycle'][$key] = array_values($values);
            }

            if (array_key_exists('sub_type', $declaration['lifecycle'])) {
                if (!is_bool($declaration['lifecycle']['sub_type'])) {
                    $result['errors'][] = 'lifecycle.sub_type must be boolean.';
                } else {
                    $result['lifecycle']['sub_type'] = $declaration['lifecycle']['sub_type'];
                }
            }
        }
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
        if (!empty($declaration['lifecycle']['emits'])) {
            $details[] = 'Declared lifecycle emits: ' . implode(', ', $declaration['lifecycle']['emits']);
        }
        if (!empty($declaration['lifecycle']['listens'])) {
            $details[] = 'Declared lifecycle listens: ' . implode(', ', $declaration['lifecycle']['listens']);
        }
        if (isset($declaration['lifecycle']['sub_type']) && $declaration['lifecycle']['sub_type'] !== null) {
            $details[] = 'Declared lifecycle sub_type: '
                . ($declaration['lifecycle']['sub_type'] ? 'supported' : 'not supported');
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


/**
 * Return the normalized lifecycle declaration for a plugin.
 *
 * This is descriptive metadata only. Runtime callbacks and source evidence
 * remain authoritative for what Hub can prove automatically.
 *
 * @param string $plugin
 * @return array
 */
function HUB_capabilityDeclaredLifecycle($plugin)
{
    $declaration = HUB_capabilityDeclaration($plugin);

    if (empty($declaration['valid']) || empty($declaration['lifecycle'])) {
        return array(
            'emits' => array(),
            'listens' => array(),
            'sub_type' => null,
        );
    }

    return $declaration['lifecycle'];
}


/**
 * Return recommendations only when a plugin opted into the generic capability
 * contract but returned an invalid declaration.
 *
 * Missing declarations are intentionally not reported as a problem.
 *
 * @param string $plugin
 * @return array
 */
function HUB_capabilityDeclarationRecommendations($plugin)
{
    $declaration = HUB_capabilityDeclaration($plugin);

    if (empty($declaration['available']) || !empty($declaration['valid'])) {
        return array();
    }

    $recommendations = array();
    foreach ($declaration['errors'] as $error) {
        $recommendations[] = 'Fix ' . $declaration['function'] . '(): ' . $error;
    }

    return $recommendations;
}
