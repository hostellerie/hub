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

function HUB_roleInfer($row)
{
    $plugin = $row['plugin'];
    $caps = $row['caps'];
    $facts = isset($row['source_facts']) ? $row['source_facts'] : array();
    $evidence = array();

    if ($plugin === 'hub') {
        return array('name' => 'orchestrator', 'label' => 'Orchestrator', 'evidence' => array('Hub is the interoperability orchestrator.'));
    }

    if (!empty($caps['item_info']) || HUB_roleHasObjectType($row) || !empty($facts['item_saved']) || !empty($facts['item_deleted'])) {
        if (!empty($caps['item_info'])) {
            $evidence[] = 'addressable content metadata via Item Info';
        }
        if (HUB_roleHasObjectType($row)) {
            $evidence[] = 'one or more object types discovered';
        }
        if (!empty($facts['item_saved']) || !empty($facts['item_deleted'])) {
            $evidence[] = 'content lifecycle emission found in source';
        }
        return array('name' => 'content', 'label' => 'Content', 'evidence' => $evidence);
    }

    if (!empty($caps['services'])) {
        return array('name' => 'service', 'label' => 'Service', 'evidence' => array('service/webservice entry points detected'));
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
        return array('name' => 'presentation', 'label' => 'Presentation', 'evidence' => $evidence);
    }

    return array('name' => 'infrastructure', 'label' => 'Infrastructure', 'evidence' => array('no addressable-content, service or presentation contract detected'));
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
        $result['status'] = 'native';
        $result['label'] = 'Native';
        $result['notes'][] = 'Hub is not scored as a content provider.';
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
    $result['notes'][] = 'Infrastructure plugins are not expected to expose Hub content APIs.';
    return $result;
}

function HUB_roleRecommendations($row, $role)
{
    $plugin = $row['plugin'];
    $caps = $row['caps'];
    $facts = isset($row['source_facts']) ? $row['source_facts'] : array();
    $out = array();

    if ($role['name'] === 'orchestrator') {
        return array('Hub is the orchestrator. The 0.1.0 audit milestone does not need addressable content APIs.');
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

    return array('No content-specific recommendation for the inferred infrastructure role.');
}

function HUB_roleEnrichRows($rows)
{
    foreach ($rows as $key => $row) {
        $role = HUB_roleInfer($row);
        $rows[$key]['role'] = $role;
        $rows[$key]['role_readiness'] = HUB_roleReadiness($row, $role);
        $rows[$key]['role_recommendations'] = HUB_roleRecommendations($row, $role);
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

function HUB_roleMarkdown($rows, $geeklogVersion, $phpVersion, $hubVersion)
{
    $out = "# Geeklog Plugin Interoperability Audit\n\n";
    $out .= '- Geeklog: `' . $geeklogVersion . "`\n";
    $out .= '- PHP: `' . $phpVersion . "`\n";
    $out .= '- Hub: `' . $hubVersion . "`\n";
    $out .= '- Generated: `' . date('c') . "`\n\n";
    $out .= "> Runtime detection, source evidence and inference are intentionally distinguished. Source scanning is not proof that every runtime path emits an event.\n\n";
    $out .= "## Summary\n\n";
    $out .= "| Plugin | Version | Role | Readiness | Core | Optional | Item Info | Related | Blocks | Autotags | Search | Services |\n";
    $out .= "| --- | --- | --- | --- | ---: | ---: | :---: | :---: | :---: | :---: | :---: | :---: |\n";
    foreach ($rows as $row) {
        $r = $row['role_readiness'];
        $core = $r['core_total'] ? $r['core_score'] . '/' . $r['core_total'] : 'N/A';
        $optional = $r['optional_total'] ? $r['optional_score'] . '/' . $r['optional_total'] : 'N/A';
        $c = $row['caps'];
        $out .= '| ' . HUB_roleMarkdownEscape($row['plugin']) . ' | ' . HUB_roleMarkdownEscape($row['version']) . ' | ' . $row['role']['label'] . ' | ' . $r['label'] . ' | ' . $core . ' | ' . $optional
            . ' | ' . ($c['item_info'] ? 'Yes' : 'No') . ' | ' . ($c['related_items'] ? 'Yes' : 'No') . ' | ' . ($c['blocks'] ? 'Yes' : 'No') . ' | ' . ($c['autotags'] ? 'Yes' : 'No') . ' | ' . ($c['search'] ? 'Yes' : 'No') . ' | ' . ($c['services'] ? 'Yes' : 'No') . " |\n";
    }

    foreach ($rows as $row) {
        $out .= "\n## " . $row['plugin'] . ' ' . $row['version'] . "\n\n";
        $out .= '**Role:** ' . $row['role']['label'] . " (inferred)\n\n";
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

        $out .= "### Hub-relevant capabilities\n\n";
        $out .= "#### Item Info\n" . HUB_roleMarkdownList($row['details']['item_info']) . "\n";
        $out .= "#### Related Items\n" . HUB_roleMarkdownList($row['details']['related_items']) . "\n";
        $out .= "#### Services\n" . HUB_roleMarkdownList($row['details']['services']) . "\n";
        $out .= "#### Lifecycle emitter\n" . HUB_roleMarkdownList($row['lifecycle_emitter']) . "\n";
        $out .= "#### Lifecycle listener\n" . HUB_roleMarkdownList($row['lifecycle_listener']) . "\n";
        $out .= "#### Object types\n" . HUB_roleMarkdownList($row['object_types']) . "\n";
        $out .= "### Embedding / discovery\n\n";
        $out .= "#### Blocks\n" . HUB_roleMarkdownList($row['details']['blocks']) . "\n";
        $out .= "#### Autotags\n" . HUB_roleMarkdownList($row['details']['autotags']) . "\n";
        $out .= "#### Search\n" . HUB_roleMarkdownList($row['details']['search']) . "\n";
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
