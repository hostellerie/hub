<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_roleApiContains($row, $needle)
{
    if (empty($row['api_surface']) || !is_array($row['api_surface'])) {
        return false;
    }
    foreach ($row['api_surface'] as $signature) {
        if (stripos($signature, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function HUB_roleHasObjectType($row)
{
    if (empty($row['object_types']) || !is_array($row['object_types'])) {
        return false;
    }
    foreach ($row['object_types'] as $detail) {
        if (strpos($detail, '✓ ') === 0) {
            return true;
        }
    }
    return false;
}

function HUB_roleDeclaredRoles($row)
{
    if (empty($row['capability_declaration'])
        || !is_array($row['capability_declaration'])
        || empty($row['capability_declaration']['valid'])
        || empty($row['capability_declaration']['roles'])
        || !is_array($row['capability_declaration']['roles'])
    ) {
        return array();
    }

    return array_values(array_unique($row['capability_declaration']['roles']));
}

function HUB_roleLabel($role)
{
    $labels = array(
        'content' => 'Content',
        'service' => 'Service',
        'presentation' => 'Presentation',
        'infrastructure' => 'Infrastructure',
        'orchestrator' => 'Orchestrator',
        'navigation' => 'Navigation',
        'relationship' => 'Relationship',
        'diagnostic' => 'Diagnostic',
        'communication' => 'Communication',
    );

    if (isset($labels[$role])) {
        return $labels[$role];
    }

    return ucwords(str_replace(array('-', '_'), ' ', (string) $role));
}

function HUB_rolePrimaryDeclared($roles)
{
    if (empty($roles) || !is_array($roles)) {
        return '';
    }

    $priority = array(
        'content',
        'orchestrator',
        'relationship',
        'diagnostic',
        'navigation',
        'communication',
        'presentation',
        'service',
        'infrastructure',
    );

    foreach ($priority as $role) {
        if (in_array($role, $roles, true)) {
            return $role;
        }
    }

    return (string) reset($roles);
}

function HUB_roleInfer($row)
{
    $plugin = $row['plugin'];
    $caps = $row['caps'];
    $facts = isset($row['source_facts']) ? $row['source_facts'] : array();
    $declaredRoles = HUB_roleDeclaredRoles($row);
    $evidence = array();

    $hasContentEvidence = !empty($caps['item_info'])
        || HUB_roleHasObjectType($row)
        || !empty($facts['item_saved'])
        || !empty($facts['item_deleted']);

    if ($hasContentEvidence) {
        if (!empty($caps['item_info'])) {
            $evidence[] = 'addressable content metadata via Item Info';
        }
        if (HUB_roleHasObjectType($row)) {
            $evidence[] = 'one or more object types discovered';
        }
        if (!empty($facts['item_saved']) || !empty($facts['item_deleted'])) {
            $evidence[] = 'content lifecycle emission found in source';
        }
        if (!empty($declaredRoles)) {
            $evidence[] = 'provider-declared roles: ' . implode(', ', $declaredRoles);
        }

        return array(
            'name' => 'content',
            'label' => HUB_roleLabel('content'),
            'evidence' => $evidence,
            'declared_roles' => $declaredRoles,
            'source' => in_array('content', $declaredRoles, true) ? 'declared+inferred' : 'inferred',
        );
    }

    if (!empty($declaredRoles)) {
        $primary = HUB_rolePrimaryDeclared($declaredRoles);
        $evidence[] = 'provider-declared roles: ' . implode(', ', $declaredRoles);

        if (!empty($caps['services'])) {
            $evidence[] = 'service/webservice entry points detected';
        }
        if (HUB_roleApiContains($row, 'plugin_getmenuitems_')) {
            $evidence[] = 'menu contribution callback detected';
        }
        if (!empty($caps['blocks']) || !empty($caps['autotags'])) {
            $evidence[] = 'presentation/embed callbacks detected';
        }

        return array(
            'name' => $primary,
            'label' => HUB_roleLabel($primary),
            'evidence' => $evidence,
            'declared_roles' => $declaredRoles,
            'source' => 'declared',
        );
    }

    if ($plugin === 'hub') {
        return array(
            'name' => 'orchestrator',
            'label' => HUB_roleLabel('orchestrator'),
            'evidence' => array('Hub is the interoperability orchestrator.'),
            'declared_roles' => array(),
            'source' => 'built-in fallback',
        );
    }

    if (!empty($caps['services'])) {
        return array(
            'name' => 'service',
            'label' => HUB_roleLabel('service'),
            'evidence' => array('service/webservice entry points detected'),
            'declared_roles' => array(),
            'source' => 'inferred',
        );
    }

    if (!empty($caps['blocks']) || !empty($caps['autotags']) || HUB_roleApiContains($row, 'plugin_getmenuitems_') || HUB_roleApiContains($row, 'plugin_centerblock_')) {
        if (!empty($caps['blocks'])) {
            $evidence[] = 'dynamic block output detected';
        }
        if (!empty($caps['autotags'])) {
            $evidence[] = 'autotag rendering detected';
        }
        if (HUB_roleApiContains($row, 'plugin_getmenuitems_')) {
            $evidence[] = 'menu contribution callback detected';
        }
        if (HUB_roleApiContains($row, 'plugin_centerblock_')) {
            $evidence[] = 'center-block rendering callback detected';
        }

        return array(
            'name' => 'presentation',
            'label' => HUB_roleLabel('presentation'),
            'evidence' => $evidence,
            'declared_roles' => array(),
            'source' => 'inferred',
        );
    }

    return array(
        'name' => 'infrastructure',
        'label' => HUB_roleLabel('infrastructure'),
        'evidence' => array('no addressable-content, service or presentation contract detected'),
        'declared_roles' => array(),
        'source' => 'inferred',
    );
}

function HUB_roleReadiness($row, $role)
{
    $caps = $row['caps'];
    $facts = isset($row['source_facts']) ? $row['source_facts'] : array();
    $result = array(
        'status' => 'limited', 'label' => 'Limited',
        'core_score' => 0, 'core_total' => 0,
        'optional_score' => 0, 'optional_total' => 0,
        'notes' => array(),
    );

    if ($role['name'] === 'orchestrator') {
        if (isset($row['plugin']) && $row['plugin'] === 'hub') {
            $result['status'] = 'native';
            $result['label'] = 'Native';
            $result['notes'][] = 'Hub is the relationship/context orchestrator and is not scored as a content provider.';
        } else {
            $result['status'] = 'role_ok';
            $result['label'] = 'Role OK';
            $result['notes'][] = 'Orchestrator is a descriptive provider role; no generic content checklist is imposed.';
        }
        return $result;
    }

    if ($role['name'] === 'content') {
        $core = array(
            'Item Info' => !empty($caps['item_info']),
            'Object type' => HUB_roleHasObjectType($row),
            'PLG_itemSaved emitter' => !empty($facts['item_saved']),
            'PLG_itemDeleted emitter' => !empty($facts['item_deleted']),
        );
        $optional = array(
            'Related Items' => !empty($caps['related_items']),
            'ID to URL' => !empty($caps['id_to_url']),
            'Services' => !empty($caps['services']),
            'Autotags' => !empty($caps['autotags']),
            'Search' => !empty($caps['search']),
            'Blocks' => !empty($caps['blocks']),
        );
        foreach ($core as $name => $value) {
            $result['core_total']++;
            if ($value) {
                $result['core_score']++;
            } else {
                $result['notes'][] = 'Core missing: ' . $name;
            }
        }
        foreach ($optional as $value) {
            $result['optional_total']++;
            if ($value) {
                $result['optional_score']++;
            }
        }
        if ($result['core_score'] === $result['core_total']) {
            $result['status'] = 'hub_ready';
            $result['label'] = 'Hub Ready';
        } elseif ($result['core_score'] >= 2) {
            $result['status'] = 'partial';
            $result['label'] = 'Partial';
        }
        return $result;
    }

    if ($role['name'] === 'service') {
        $result['core_total'] = 1;
        $result['core_score'] = !empty($caps['services']) ? 1 : 0;
        $result['optional_total'] = 1;
        $result['optional_score'] = HUB_roleApiContains($row, 'plugin_itemsaved_') || HUB_roleApiContains($row, 'plugin_itemdeleted_') ? 1 : 0;
        if ($result['core_score'] === 1) {
            $result['status'] = 'role_ready';
            $result['label'] = 'Role Ready';
        }
        return $result;
    }

    if ($role['name'] === 'presentation') {
        $presentationReady = !empty($caps['blocks']) || !empty($caps['autotags']) || HUB_roleApiContains($row, 'plugin_getmenuitems_') || HUB_roleApiContains($row, 'plugin_centerblock_');
        $result['core_total'] = 1;
        $result['core_score'] = $presentationReady ? 1 : 0;
        $result['optional_total'] = 1;
        $result['optional_score'] = !empty($caps['services']) ? 1 : 0;
        if ($presentationReady) {
            $result['status'] = 'role_ready';
            $result['label'] = 'Role Ready';
        }
        return $result;
    }

    $result['status'] = 'role_ok';
    $result['label'] = 'Role OK';

    if (in_array($role['name'], array('relationship', 'diagnostic', 'navigation', 'communication', 'infrastructure'), true)) {
        $result['notes'][] = HUB_roleLabel($role['name'])
            . ' providers are not expected to expose the normal content-owner baseline unless they also own addressable content.';
    } else {
        $result['notes'][] = 'This provider role has no generic content-readiness checklist.';
    }

    return $result;
}

function HUB_roleRecommendations($row, $role)
{
    $plugin = $row['plugin'];
    $caps = $row['caps'];
    $facts = isset($row['source_facts']) ? $row['source_facts'] : array();
    $out = array();

    if ($role['name'] === 'orchestrator') {
        if ($plugin === 'hub') {
            return array('Hub is the relationship/context orchestrator. It consumes shared Geeklog contracts without acting as a content provider.');
        }
        return array();
    }

    if ($role['name'] === 'content') {
        if (!$caps['item_info']) {
            $out[] = 'Core: implement plugin_getiteminfo_' . $plugin . '() so Hub can resolve current metadata from type + id.';
        }
        if (empty($facts['item_saved'])) {
            $out[] = 'Core lifecycle: emit PLG_itemSaved() when addressable content is created or updated.';
        }
        if (empty($facts['item_deleted'])) {
            $out[] = 'Core lifecycle: emit PLG_itemDeleted() when addressable content is deleted.';
        }
        if (!$caps['related_items']) {
            $out[] = 'Optional: implement plugin_getrelateditems_' . $plugin . '() for future Hub suggestions.';
        }
        if (empty($caps['id_to_url'])) {
            $out[] = 'Optional: implement plugin_idtourl_' . $plugin . '() when stable type + id resolution is useful beyond Item Info URL lookup.';
        }
        if (!$caps['services']) {
            $out[] = 'Optional: expose a Geeklog service if another plugin needs specialized actions or rendering.';
        }
        if (!$caps['blocks']) {
            $out[] = 'Optional: expose plugin_getBlocks_' . $plugin . '() only if reusable dynamic blocks make sense for this content type.';
        }
        if (!$caps['autotags']) {
            $out[] = 'Optional: expose autotags if this content should be embedded inside other Geeklog content.';
        }
        if (!$caps['search']) {
            $out[] = 'Optional: implement plugin_dopluginsearch_' . $plugin . '() if this content should participate in Geeklog search.';
        }
        if (!empty($facts['item_saved']) && !empty($facts['item_deleted'])) {
            $out[] = 'Lifecycle emitter calls were found in source. This is strong evidence, not a guarantee that every mutation path emits them.';
        }
        return $out;
    }

    if ($role['name'] === 'presentation') {
        if (!$caps['services']) {
            $out[] = 'Optional: expose a service only if targeted rendering/actions are needed beyond the existing presentation hooks.';
        }
        return $out;
    }

    if ($role['name'] === 'service') {
        return $out;
    }

    return array();
}

function HUB_roleEnrichRows($rows)
{
    foreach ($rows as $key => $row) {
        $role = HUB_roleInfer($row);
        $rows[$key]['role'] = $role;
        $rows[$key]['role_readiness'] = HUB_roleReadiness($row, $role);
        $recommendations = HUB_roleRecommendations($row, $role);
        if (function_exists('HUB_capabilityDeclarationRecommendations')) {
            $recommendations = array_merge(
                $recommendations,
                HUB_capabilityDeclarationRecommendations(isset($row['plugin']) ? $row['plugin'] : '')
            );
        }
        $rows[$key]['role_recommendations'] = array_values(array_unique($recommendations));
    }
    return $rows;
}

function HUB_roleMarkdownEscape($value)
{
    $value = str_replace(array("\r", "\n"), ' ', (string) $value);
    return str_replace('|', '\\|', $value);
}

function HUB_roleMarkdownList($items)
{
    if (empty($items)) {
        return "- Not detected\n";
    }
    $out = '';
    foreach ($items as $item) {
        $out .= '- `' . str_replace('`', '\\`', (string) $item) . "`\n";
    }
    return $out;
}

function HUB_roleMarkdown($rows, $geeklogVersion, $phpVersion, $hubVersion, $siteName = '')
{
    $siteName = trim((string) $siteName);
    $reportTitle = $siteName !== ''
        ? $siteName . ' — Plugin Interoperability Audit'
        : 'Plugin Interoperability Audit';

    $out = '# ' . $reportTitle . "\n\n";
    if ($siteName !== '') {
        $out .= '- Site: `' . str_replace('`', '\\`', $siteName) . "`\n";
    }
    $out .= '- Geeklog: `' . $geeklogVersion . "`\n";
    $out .= '- PHP: `' . $phpVersion . "`\n";
    $out .= '- Hub: `' . $hubVersion . "`\n";
    $out .= '- Generated: `' . date('c') . "`\n\n";
    $out .= "> Runtime detection, source evidence and inference are intentionally distinguished. Source scanning is not proof that every runtime path emits an event.\n\n";
    $out .= "## Summary\n\n";
    $out .= "| Plugin | Version | Role | Readiness | Core | Optional | Item Info | Related | ID→URL | Blocks | Autotags | Search | Services |\n";
    $out .= "| --- | --- | --- | --- | ---: | ---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |\n";
    foreach ($rows as $row) {
        $r = $row['role_readiness'];
        $core = $r['core_total'] ? $r['core_score'] . '/' . $r['core_total'] : 'N/A';
        $optional = $r['optional_total'] ? $r['optional_score'] . '/' . $r['optional_total'] : 'N/A';
        $c = $row['caps'];
        $out .= '| ' . HUB_roleMarkdownEscape($row['plugin']) . ' | ' . HUB_roleMarkdownEscape($row['version']) . ' | ' . $row['role']['label'] . ' | ' . $r['label'] . ' | ' . $core . ' | ' . $optional
            . ' | ' . ($c['item_info'] ? 'Yes' : 'No') . ' | ' . ($c['related_items'] ? 'Yes' : 'No') . ' | ' . (!empty($c['id_to_url']) ? 'Yes' : 'No') . ' | ' . ($c['blocks'] ? 'Yes' : 'No') . ' | ' . ($c['autotags'] ? 'Yes' : 'No') . ' | ' . ($c['search'] ? 'Yes' : 'No') . ' | ' . ($c['services'] ? 'Yes' : 'No') . " |\n";
    }

    foreach ($rows as $row) {
        $out .= "\n## " . $row['plugin'] . ' ' . $row['version'] . "\n\n";
        $roleSource = isset($row['role']['source']) ? $row['role']['source'] : 'inferred';
        $out .= '**Primary role:** ' . $row['role']['label'] . ' (' . $roleSource . ")\n\n";
        if (!empty($row['role']['declared_roles'])) {
            $out .= '**Declared roles:** ' . implode(', ', $row['role']['declared_roles']) . "\n\n";
        }
        $out .= "**Role evidence:**\n";
        foreach ($row['role']['evidence'] as $evidence) {
            $out .= '- ' . $evidence . "\n";
        }
        $r = $row['role_readiness'];
        $out .= "\n**Readiness:** " . $r['label'];
        if ($r['core_total']) {
            $out .= ' — core ' . $r['core_score'] . '/' . $r['core_total'];
        }
        if ($r['optional_total']) {
            $out .= ', optional ' . $r['optional_score'] . '/' . $r['optional_total'];
        }
        $out .= "\n\n";
        if (!empty($r['notes'])) {
            $out .= "**Readiness notes:**\n";
            foreach ($r['notes'] as $note) {
                $out .= '- ' . $note . "\n";
            }
            $out .= "\n";
        }

        $out .= "### Content interoperability baseline\n\n";
        $out .= "#### Item Info\n" . HUB_roleMarkdownList($row['details']['item_info']) . "\n";
        $out .= "#### Related Items\n" . HUB_roleMarkdownList($row['details']['related_items']) . "\n";
        $out .= "#### ID to URL\n" . HUB_roleMarkdownList(isset($row['details']['id_to_url']) ? $row['details']['id_to_url'] : array()) . "\n";
        $out .= "#### Services\n" . HUB_roleMarkdownList($row['details']['services']) . "\n";
        $out .= "#### Lifecycle emitter\n" . HUB_roleMarkdownList($row['lifecycle_emitter']) . "\n";
        $out .= "#### Lifecycle listener\n" . HUB_roleMarkdownList($row['lifecycle_listener']) . "\n";
        $out .= "#### Lifecycle contract\n" . HUB_roleMarkdownList(isset($row['lifecycle_contract']) ? $row['lifecycle_contract'] : array()) . "\n";
        $out .= "#### Object types\n" . HUB_roleMarkdownList($row['object_types']) . "\n";
        $out .= "### Shared content contract evidence\n\n";
        $out .= HUB_roleMarkdownList(isset($row['content_contract_details']) ? $row['content_contract_details'] : array()) . "\n";
        $out .= "> Declaration/source evidence only; Hub does not execute arbitrary provider collection queries during audit.\n\n";
        $out .= "### Shared capability declaration\n\n";
        $out .= HUB_roleMarkdownList(isset($row['capability_declaration_details']) ? $row['capability_declaration_details'] : array()) . "\n";
        $out .= "> Plugin-supplied declaration; kept distinct from runtime detection and source evidence.\n\n";
        $out .= "### Plugin-owned render entry points\n\n";
        $out .= HUB_roleMarkdownList(isset($row['render_catalogue_details']) ? $row['render_catalogue_details'] : array()) . "\n";
        $out .= "> Discovery only; the owning plugin remains responsible for arguments, permissions and output.\n\n";
        $out .= "### Embedding / discovery\n\n";
        $out .= "#### Blocks\n" . HUB_roleMarkdownList($row['details']['blocks']) . "\n";
        $out .= "#### Autotags\n" . HUB_roleMarkdownList($row['details']['autotags']) . "\n";
        $out .= "#### Search\n" . HUB_roleMarkdownList($row['details']['search']) . "\n";
        $out .= "### Extended Geeklog integration\n\n" . HUB_roleMarkdownList(isset($row['additional_capabilities']) ? $row['additional_capabilities'] : array()) . "\n";
        $out .= "### Modernization metadata\n\n";
        $out .= HUB_roleMarkdownList(isset($row['metadata_manifest_details']) ? $row['metadata_manifest_details'] : array()) . "\n";
        $out .= "> plugin.json is recommended modernization metadata and does not affect readiness scoring.\n\n";
        $out .= "### Advanced API surface\n\n" . HUB_roleMarkdownList($row['api_surface']) . "\n";
        $out .= "### Recommendations\n\n";
        if (empty($row['role_recommendations'])) {
            $out .= "- None\n";
        } else {
            foreach ($row['role_recommendations'] as $recommendation) {
                $out .= '- ' . $recommendation . "\n";
            }
        }
    }
    $out .= "\n---\nGenerated by Hub " . $hubVersion . ".\n";
    return $out;
}
