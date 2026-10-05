<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub integrity administration without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

function HUB_integrityAdminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$summary = function_exists('HUB_integritySummary')
    ? HUB_integritySummary(0)
    : array();

$content = HUB_adminNavigation('integrity');
$content .= '<h1>Integrity &amp; cluster health</h1>';
$content .= '<p>This 0.9.0 view reports only diagnostics Hub can verify through its approved graph and public Geeklog contracts. '
    . 'Backlink integration availability is not presented as proof that a provider rendered a backlink.</p>';

$content .= '<h2>Overview</h2>';
$content .= '<ul>'
    . '<li>Enabled pillars: ' . (isset($summary['pillars']) ? (int) $summary['pillars'] : 0) . '</li>'
    . '<li>Approved relations: ' . (isset($summary['relations']) ? (int) $summary['relations'] : 0) . '</li>'
    . '<li>Resolved relations: ' . (isset($summary['resolved_relations']) ? (int) $summary['resolved_relations'] : 0) . '</li>'
    . '<li>Unresolved relations: ' . (isset($summary['unresolved_relations']) ? (int) $summary['unresolved_relations'] : 0) . '</li>'
    . '<li>Renderable outgoing targets: ' . (isset($summary['renderable_relations']) ? (int) $summary['renderable_relations'] : 0) . '</li>'
    . '<li>Non-renderable outgoing targets: ' . (isset($summary['non_renderable_relations']) ? (int) $summary['non_renderable_relations'] : 0) . '</li>'
    . '<li>Unresolved pillar sources: ' . (isset($summary['unresolved_pillar_sources']) ? (int) $summary['unresolved_pillar_sources'] : 0) . '</li>'
    . '</ul>';

$backlink = isset($summary['backlink']) && is_array($summary['backlink'])
    ? $summary['backlink']
    : array();

$health = isset($summary['health']) && is_array($summary['health'])
    ? $summary['health']
    : array();

$content .= '<h2>Cross-site relation context</h2>'
    . '<ul>'
    . '<li>Current-site targets: ' . (isset($summary['current_site_relations']) ? (int) $summary['current_site_relations'] : 0) . '</li>'
    . '<li>Cross-site targets: ' . (isset($summary['cross_site_relations']) ? (int) $summary['cross_site_relations'] : 0) . '</li>'
    . '<li>Unknown/unresolved site context: ' . (isset($summary['unknown_site_relations']) ? (int) $summary['unknown_site_relations'] : 0) . '</li>'
    . '</ul>'
    . '<p><small>Cross-site is contextual information, not an error by itself.</small></p>';

$content .= '<h2>Pillar health</h2>'
    . '<ul>'
    . '<li>Healthy: ' . (isset($health['healthy']) ? (int) $health['healthy'] : 0) . '</li>'
    . '<li>Needs attention: ' . (isset($health['attention']) ? (int) $health['attention'] : 0) . '</li>'
    . '<li>Broken: ' . (isset($health['broken']) ? (int) $health['broken'] : 0) . '</li>'
    . '</ul>';

$content .= '<h2>Backlink integration evidence</h2>';
$content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
    . '<th>Evidence level</th><th>Relations</th><th>Meaning</th>'
    . '</tr></thead><tbody>'
    . '<tr><td><code>hub-managed</code></td><td>' . (isset($backlink['hub_managed']) ? (int) $backlink['hub_managed'] : 0) . '</td>'
    . '<td>Hub owns the known public rendering path.</td></tr>'
    . '<tr><td><code>integration-available</code></td><td>' . (isset($backlink['integration_available']) ? (int) $backlink['integration_available'] : 0) . '</td>'
    . '<td>A generic integration hook is available; runtime backlink output is not asserted.</td></tr>'
    . '<tr><td><code>unconfirmed</code></td><td>' . (isset($backlink['unconfirmed']) ? (int) $backlink['unconfirmed'] : 0) . '</td>'
    . '<td>No confirmed generic backlink placement contract is available.</td></tr>'
    . '</tbody></table>';

$reciprocal = isset($summary['reciprocal']) && is_array($summary['reciprocal'])
    ? $summary['reciprocal']
    : array();

$content .= '<h2>Reciprocal-link evidence</h2>'
    . '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
    . '<th>Status</th><th>Relations</th><th>Meaning</th>'
    . '</tr></thead><tbody>'
    . '<tr><td><code>hub-rendered</code></td><td>'
    . (isset($reciprocal['hub_rendered']) ? (int) $reciprocal['hub_rendered'] : 0)
    . '</td><td>Hub generated the expected pillar backlink and owns the public placement path.</td></tr>'
    . '<tr><td><code>fragment-available-runtime-unverified</code></td><td>'
    . (isset($reciprocal['fragment_available_runtime_unverified']) ? (int) $reciprocal['fragment_available_runtime_unverified'] : 0)
    . '</td><td>Hub can generate the expected fragment, but provider placement is not proven.</td></tr>'
    . '<tr><td><code>integration-available-unverified</code></td><td>'
    . (isset($reciprocal['integration_available_unverified']) ? (int) $reciprocal['integration_available_unverified'] : 0)
    . '</td><td>A provider integration path exists, but no expected fragment was verified.</td></tr>'
    . '<tr><td><code>unconfirmed</code></td><td>'
    . (isset($reciprocal['unconfirmed']) ? (int) $reciprocal['unconfirmed'] : 0)
    . '</td><td>No reciprocal-link placement can currently be verified.</td></tr>'
    . '</tbody></table>';

$providers = isset($summary['providers']) && is_array($summary['providers'])
    ? $summary['providers']
    : array();

$content .= '<h2>Provider integrity</h2>';
if (empty($providers)) {
    $content .= '<p>No enabled approved relation is currently available.</p>';
} else {
    $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>Provider</th><th>Relations</th><th>Resolved</th><th>Unresolved</th>'
        . '</tr></thead><tbody>';
    foreach ($providers as $provider => $stats) {
        $content .= '<tr><td><code>' . HUB_integrityAdminEscape($provider) . '</code></td>'
            . '<td>' . (isset($stats['relations']) ? (int) $stats['relations'] : 0) . '</td>'
            . '<td>' . (isset($stats['resolved']) ? (int) $stats['resolved'] : 0) . '</td>'
            . '<td>' . (isset($stats['unresolved']) ? (int) $stats['unresolved'] : 0) . '</td></tr>';
    }
    $content .= '</tbody></table>';
}

$pillars = isset($summary['pillar_items']) && is_array($summary['pillar_items'])
    ? $summary['pillar_items']
    : array();

$content .= '<h2>Pillar diagnostics</h2>';
if (empty($pillars)) {
    $content .= '<p>No enabled pillar is currently available.</p>';
} else {
    foreach ($pillars as $pillar) {
        $source = isset($pillar['source']) && is_array($pillar['source'])
            ? $pillar['source']
            : array();
        $sourceIdentity = (isset($source['type']) ? $source['type'] : '')
            . ':' . (isset($source['id']) ? $source['id'] : '');

        $healthStatus = isset($pillar['health']['status']) ? (string) $pillar['health']['status'] : 'attention';
        $content .= '<details style="margin:0 0 12px;border:1px solid #d7d7d7;border-radius:4px;padding:10px">'
            . '<summary style="cursor:pointer"><strong><code>'
            . HUB_integrityAdminEscape($sourceIdentity) . '</code></strong> — '
            . '<strong>' . HUB_integrityAdminEscape($healthStatus) . '</strong> — '
            . (isset($pillar['relation_count']) ? (int) $pillar['relation_count'] : 0)
            . ' relation(s), '
            . (isset($pillar['unresolved_relations']) ? (int) $pillar['unresolved_relations'] : 0)
            . ' unresolved</summary>';

        $issues = isset($pillar['health']['issues']) && is_array($pillar['health']['issues'])
            ? $pillar['health']['issues']
            : array();
        if (!empty($issues)) {
            $content .= '<p><strong>Integrity issues:</strong></p><ul>';
            foreach ($issues as $issue) {
                $content .= '<li><code>'
                    . HUB_integrityAdminEscape(isset($issue['code']) ? $issue['code'] : '')
                    . '</code> — '
                    . HUB_integrityAdminEscape(isset($issue['severity']) ? $issue['severity'] : '')
                    . ' (' . (isset($issue['count']) ? (int) $issue['count'] : 0) . ')</li>';
            }
            $content .= '</ul>';
        }

        if (empty($source['resolved'])) {
            $content .= '<p><strong>Pillar source unresolved:</strong> '
                . HUB_integrityAdminEscape(isset($source['diagnostic']) ? $source['diagnostic'] : '')
                . '</p>';
        }

        $relations = isset($pillar['relations']) && is_array($pillar['relations'])
            ? $pillar['relations']
            : array();

        if (empty($relations)) {
            $content .= '<p>No enabled relation.</p></details>';
            continue;
        }

        $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
            . '<th>Identity</th><th>Structural</th><th>Editorial</th><th>Target</th><th>Site</th><th>Integration</th><th>Reciprocal</th>'
            . '</tr></thead><tbody>';

        foreach ($relations as $relation) {
            $identity = (isset($relation['type']) ? $relation['type'] : '')
                . ':' . (isset($relation['id']) ? $relation['id'] : '');
            $targetStatus = !empty($relation['renderable_from_pillar'])
                ? 'renderable'
                : 'unresolved / non-renderable';
            $evidence = isset($relation['backlink_evidence']) && is_array($relation['backlink_evidence'])
                ? $relation['backlink_evidence']
                : array();
            $reciprocalEvidence = isset($relation['reciprocal_evidence']) && is_array($relation['reciprocal_evidence'])
                ? $relation['reciprocal_evidence']
                : array();
            $relationSite = isset($relation['site_context']) && is_array($relation['site_context'])
                ? $relation['site_context']
                : array();
            $siteLabel = !empty($relationSite['cross_site'])
                ? 'cross-site'
                : (!empty($relationSite['current_site']) ? 'current-site' : 'unknown');

            $content .= '<tr><td><code>' . HUB_integrityAdminEscape($identity) . '</code>'
                . (!empty($relation['title']) ? '<br>' . HUB_integrityAdminEscape($relation['title']) : '')
                . '</td><td><code>' . HUB_integrityAdminEscape(
                    isset($relation['relation_role']) ? $relation['relation_role'] : 'related'
                ) . '</code></td>'
                . '<td>' . (!empty($relation['editorial_role'])
                    ? '<code>' . HUB_integrityAdminEscape($relation['editorial_role']) . '</code>'
                    : '—') . '</td>'
                . '<td>' . HUB_integrityAdminEscape($targetStatus)
                . (!empty($relation['diagnostic'])
                    ? '<br><small>' . HUB_integrityAdminEscape($relation['diagnostic']) . '</small>'
                    : '') . '</td>'
                . '<td><code>' . HUB_integrityAdminEscape($siteLabel) . '</code>'
                . (!empty($relationSite['host'])
                    ? '<br><small>' . HUB_integrityAdminEscape($relationSite['host']) . '</small>'
                    : '') . '</td>'
                . '<td><code>' . HUB_integrityAdminEscape(
                    isset($evidence['verification']) ? $evidence['verification'] : 'unconfirmed'
                ) . '</code><br><small>'
                . HUB_integrityAdminEscape(isset($evidence['label']) ? $evidence['label'] : '')
                . '</small></td>'
                . '<td><code>' . HUB_integrityAdminEscape(
                    isset($reciprocalEvidence['status']) ? $reciprocalEvidence['status'] : 'unconfirmed'
                ) . '</code><br><small>'
                . HUB_integrityAdminEscape(isset($reciprocalEvidence['detail']) ? $reciprocalEvidence['detail'] : '')
                . '</small></td></tr>';
        }

        $content .= '</tbody></table></details>';
    }
}

$graph = isset($summary['graph']) && is_array($summary['graph'])
    ? $summary['graph']
    : array();
$graphCounts = isset($graph['counts']) && is_array($graph['counts'])
    ? $graph['counts']
    : array();

$content .= '<h2>Graph diagnostics</h2>'
    . '<ul>'
    . '<li>Self-relations: ' . (isset($graphCounts['self_relations']) ? (int) $graphCounts['self_relations'] : 0) . '</li>'
    . '<li>Pillar cycles: ' . (isset($graphCounts['cycles']) ? (int) $graphCounts['cycles'] : 0) . '</li>'
    . '<li>Multi-parent items: ' . (isset($graphCounts['multi_parent_items']) ? (int) $graphCounts['multi_parent_items'] : 0)
    . ' <small>(informational; multi-parent participation is allowed)</small></li>'
    . '</ul>';

if (!empty($graph['cycles']) && is_array($graph['cycles'])) {
    $content .= '<h3>Cycles to review</h3><ul>';
    foreach ($graph['cycles'] as $cycle) {
        $content .= '<li><code>' . HUB_integrityAdminEscape(implode(' → ', $cycle)) . '</code></li>';
    }
    $content .= '</ul>';
}

$canonical = isset($summary['canonical_collisions']) && is_array($summary['canonical_collisions'])
    ? $summary['canonical_collisions']
    : array();
$content .= '<h2>Canonical/public URL consistency</h2>';
if (empty($canonical)) {
    $content .= '<p>No duplicate resolved public destination is currently detected in the approved Hub graph.</p>';
} else {
    $content .= '<p>Multiple stable identities resolve to the same normalized public URL. Review provider canonical identity and relation selection.</p><ul>';
    foreach ($canonical as $collision) {
        $identities = array();
        if (!empty($collision['identities']) && is_array($collision['identities'])) {
            foreach ($collision['identities'] as $identity) {
                $identities[] = (isset($identity['type']) ? $identity['type'] : '')
                    . ':' . (isset($identity['id']) ? $identity['id'] : '');
            }
        }
        $content .= '<li><code>' . HUB_integrityAdminEscape(
            isset($collision['url_key']) ? $collision['url_key'] : ''
        ) . '</code> — ' . HUB_integrityAdminEscape(implode(', ', $identities)) . '</li>';
    }
    $content .= '</ul>';
}

$unconnected = isset($summary['unconnected_content']) && is_array($summary['unconnected_content'])
    ? $summary['unconnected_content']
    : array();
$unconnectedProviders = isset($unconnected['providers']) && is_array($unconnected['providers'])
    ? $unconnected['providers']
    : array();

$content .= '<h2>Content not connected to the Hub graph</h2>'
    . '<p><strong>Important:</strong> <code>hub-unconnected</code> means only that provider-owned content returned by the shared '
    . '<code>content.collection</code> contract is absent from the enabled Hub graph. It does <strong>not</strong> mean SEO orphan.</p>';

if (empty($unconnectedProviders)) {
    $content .= '<p>No unconnected content is currently reported by collection-capable providers.</p>';
} else {
    foreach ($unconnectedProviders as $provider => $providerData) {
        $items = isset($providerData['items']) && is_array($providerData['items'])
            ? $providerData['items']
            : array();

        $content .= '<details style="margin:0 0 12px;border:1px solid #d7d7d7;border-radius:4px;padding:10px">'
            . '<summary style="cursor:pointer"><strong><code>'
            . HUB_integrityAdminEscape($provider)
            . '</code></strong> — '
            . (isset($providerData['hub_unconnected_count']) ? (int) $providerData['hub_unconnected_count'] : count($items))
            . ' hub-unconnected item(s)'
            . (!empty($providerData['possibly_truncated']) ? ' — collection result may be truncated' : '')
            . '</summary>';

        if (empty($items)) {
            $content .= '<p>No unconnected item.</p></details>';
            continue;
        }

        $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
            . '<th>Identity</th><th>Title</th><th>Evidence</th>'
            . '</tr></thead><tbody>';

        foreach ($items as $item) {
            $identity = (isset($item['type']) ? $item['type'] : $provider)
                . ':' . (isset($item['id']) ? $item['id'] : '');
            $content .= '<tr><td><code>' . HUB_integrityAdminEscape($identity) . '</code></td>'
                . '<td>' . HUB_integrityAdminEscape(isset($item['title']) ? $item['title'] : '') . '</td>'
                . '<td><code>content.collection</code> + graph membership absent</td></tr>';
        }

        $content .= '</tbody></table></details>';
    }
}

$distribution = isset($summary['distribution']) && is_array($summary['distribution'])
    ? $summary['distribution']
    : array();
$distributionProviders = isset($distribution['providers']) && is_array($distribution['providers'])
    ? $distribution['providers']
    : array();

$content .= '<h2>Sitemap &amp; feed interoperability</h2>'
    . '<p>This reports provider-owned distribution contracts only. Hub does not generate XML Sitemap or feed output.</p>';

if (empty($distributionProviders)) {
    $content .= '<p>No provider distribution context is currently available.</p>';
} else {
    $content .= '<table class="uk-table uk-table-divider uk-table-small"><thead><tr>'
        . '<th>Provider</th><th>Sitemap</th><th>Syndication</th><th>Review opportunities</th>'
        . '</tr></thead><tbody>';

    foreach ($distributionProviders as $provider => $providerData) {
        $sitemap = isset($providerData['sitemap']) && is_array($providerData['sitemap'])
            ? $providerData['sitemap']
            : array();
        $syndication = isset($providerData['syndication']) && is_array($providerData['syndication'])
            ? $providerData['syndication']
            : array();
        $opportunities = isset($providerData['opportunities']) && is_array($providerData['opportunities'])
            ? $providerData['opportunities']
            : array();

        $content .= '<tr><td><code>' . HUB_integrityAdminEscape($provider) . '</code></td>'
            . '<td><code>' . HUB_integrityAdminEscape(
                isset($sitemap['status']) ? $sitemap['status'] : 'not-detected'
            ) . '</code></td>'
            . '<td><code>' . HUB_integrityAdminEscape(
                isset($syndication['status']) ? $syndication['status'] : 'not-declared'
            ) . '</code></td>'
            . '<td>' . (empty($opportunities)
                ? '—'
                : HUB_integrityAdminEscape(implode(', ', $opportunities)))
            . '</td></tr>';
    }

    $content .= '</tbody></table>';
}

$content .= '<h2>Scope boundary</h2>'
    . '<p>This 0.9.0 diagnostic does not claim actual reciprocal-link presence for generic providers, '
    . 'global SEO orphan status, sitemap/feed coverage or opaque cluster-health scoring. '
    . 'Those checks will build on this normalized integrity model.</p>';

$display = COM_startBlock('Hub integrity & cluster health') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
