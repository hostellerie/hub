<?php

$_SERVER['PHP_SELF'] = 'tests/editorial_contract.php';

$hubEditorialPillars = array(
    array('id' => 1, 'source_type' => 'staticpages', 'source_id' => 'hub-a', 'editorial_role' => 'guide', 'is_enabled' => 1),
    array('id' => 2, 'source_type' => 'staticpages', 'source_id' => 'pillar-b', 'editorial_role' => 'reference', 'is_enabled' => 1),
    array('id' => 3, 'source_type' => 'staticpages', 'source_id' => 'hub-c', 'editorial_role' => '', 'is_enabled' => 1),
);

$hubHiddenSuggestions = array();
$hubEditorialCloseMode = false;
$hubEditorialGapMode = false;

$hubEditorialRelations = array(
    1 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'editorial_role' => 'reference', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'satellite', 'editorial_role' => 'tutorial', 'is_enabled' => 1),
    ),
    2 => array(
        array('item_type' => 'article', 'item_id' => 'story-y', 'relation_role' => 'satellite', 'is_enabled' => 1),
    ),
    3 => array(
        array('item_type' => 'staticpages', 'item_id' => 'pillar-b', 'relation_role' => 'sub-pillar', 'is_enabled' => 1),
        array('item_type' => 'article', 'item_id' => 'story-x', 'relation_role' => 'support', 'is_enabled' => 1),
    ),
);

function HUB_normalizeObjectType($type)
{
    return strtolower(trim((string) $type));
}

function HUB_normalizeObjectId($id)
{
    return trim((string) $id);
}

function HUB_normalizeRelationRole($role)
{
    $role = strtolower(trim((string) $role));
    return in_array($role, array('related', 'sub-pillar', 'satellite', 'support', 'equivalent'), true)
        ? $role : 'related';
}

function HUB_normalizeEditorialRole($role)
{
    $role = strtolower(trim((string) $role));
    return in_array($role, array('', 'guide', 'tutorial', 'reference', 'case-study', 'download', 'video', 'discussion', 'resource', 'news', 'archive'), true)
        ? $role : '';
}

function HUB_graphIdentityKey($type, $id)
{
    $type = HUB_normalizeObjectType($type);
    $id = HUB_normalizeObjectId($id);
    return $type === '' || $id === '' ? '' : $type . ':' . $id;
}

function HUB_getPillars($includeDisabled = true)
{
    global $hubEditorialPillars;
    return $hubEditorialPillars;
}

function HUB_getRelations($pillarId, $includeDisabled = true)
{
    global $hubEditorialRelations;
    return isset($hubEditorialRelations[(int) $pillarId])
        ? $hubEditorialRelations[(int) $pillarId]
        : array();
}

function HUB_staticPageTopicContext($pageId)
{
    if ((string) $pageId !== 'hub-a' && (string) $pageId !== 'new-pillar') {
        return array('specific_topics' => array());
    }

    return array(
        'specific_topics' => array(
            array('tid' => 'seo', 'topic' => 'SEO'),
            array('tid' => 'content', 'topic' => 'Content'),
        ),
    );
}

function HUB_linkAuditArticlesByTopics(array $topicIds)
{
    global $hubEditorialCloseMode, $hubEditorialGapMode;

    if ($hubEditorialGapMode) {
        $topicId = isset($topicIds[0]) ? (string) $topicIds[0] : '';
        if ($topicId === 'seo') {
            return array();
        }
        if ($topicId === 'content') {
            return array(
                array(
                    'sid' => 'single-content',
                    'title' => 'Single content article',
                    'hub_topics' => array('content' => 'Content'),
                    'hits' => 10,
                    'comments' => 0,
                    'date' => '2025-04-01 00:00:00',
                ),
            );
        }
        return array();
    }

    if ($hubEditorialCloseMode) {
        return array(
            array(
                'sid' => 'close-a',
                'title' => 'LibreOffice migration guide for Windows',
                'hub_topics' => array('seo' => 'SEO', 'content' => 'Content'),
                'hits' => 40,
                'comments' => 1,
                'date' => '2025-01-01 00:00:00',
            ),
            array(
                'sid' => 'close-b',
                'title' => 'LibreOffice migration guide for Linux',
                'hub_topics' => array('seo' => 'SEO', 'content' => 'Content'),
                'hits' => 30,
                'comments' => 0,
                'date' => '2025-02-01 00:00:00',
            ),
            array(
                'sid' => 'far-c',
                'title' => 'Rocket stove cooking basics',
                'hub_topics' => array('seo' => 'SEO'),
                'hits' => 20,
                'comments' => 0,
                'date' => '2025-03-01 00:00:00',
            ),
        );
    }

    return array(
        array(
            'sid' => 'story-x',
            'title' => 'Already approved story',
            'hub_topics' => array('seo' => 'SEO'),
            'hits' => 100,
            'comments' => 4,
            'date' => '2025-01-10 12:00:00',
        ),
        array(
            'sid' => 'story-new',
            'title' => 'Candidate story 2024 for version 26.8',
            'introtext' => 'Migration notes for v1.5.0.',
            'bodytext' => '',
            'hub_topics' => array('seo' => 'SEO', 'content' => 'Content'),
            'hits' => 250,
            'comments' => 9,
            'date' => '2024-06-15 09:30:00',
        ),
    );
}

function HUB_isSuggestionHidden($kind, $pillarId, $itemType, $itemId)
{
    global $hubHiddenSuggestions;
    $key = (string) $kind . ':' . (int) $pillarId . ':' . (string) $itemType . ':' . (string) $itemId;
    return !empty($hubHiddenSuggestions[$key]);
}

function HUB_linkAuditStaticPages()
{
    return array(
        array('sp_id' => 'hub-a', 'sp_title' => 'Existing pillar'),
        array('sp_id' => 'new-pillar', 'sp_title' => 'New pillar candidate'),
    );
}

require_once dirname(__DIR__) . '/lib-editorial.php';

function hubEditorialAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$summary = HUB_editorialSummary(false);

hubEditorialAssert($summary['schema'] === 1, 'editorial summary schema is explicit');
hubEditorialAssert($summary['pillars'] === 3, 'editorial summary counts approved pillars');
hubEditorialAssert($summary['relations'] === 5, 'editorial summary counts approved relations');
hubEditorialAssert($summary['roles']['sub-pillar'] === 2, 'sub-pillar edges are counted');
hubEditorialAssert($summary['roles']['satellite'] === 2, 'satellite edges are counted');
hubEditorialAssert($summary['roles']['support'] === 1, 'support edges are counted');
hubEditorialAssert($summary['roles']['related'] === 0, 'neutral related edges are counted separately');
hubEditorialAssert($summary['roles']['equivalent'] === 0, 'equivalent structural edges are counted separately');
hubEditorialAssert($summary['providers']['staticpages'] === 2, 'Static Page provider count is normalized');
hubEditorialAssert($summary['providers']['article'] === 3, 'article provider count is normalized');
hubEditorialAssert($summary['nested_pillars'] === 1, 'related identities that are also pillars are counted as nested pillars');
hubEditorialAssert($summary['multi_parent_items'] === 2, 'multi-parent identities are counted without enforcing a tree');
hubEditorialAssert(count($summary['pillar_items']) === 3, 'per-pillar structural summaries are exposed');
hubEditorialAssert($summary['pillar_items'][0]['pillar_id'] === 1, 'pillar summaries are deterministic by id');

$inventory = HUB_editorialInventory(false);
hubEditorialAssert($inventory['schema'] === 1, 'editorial inventory schema is explicit');
hubEditorialAssert(count($inventory['pillars']) === 3, 'editorial inventory exposes every approved pillar');
hubEditorialAssert(count($inventory['pillars'][0]['items']) === 2, 'pillar inventory exposes approved relation identities');
hubEditorialAssert($inventory['pillars'][0]['editorial_role'] === 'guide', 'inventory preserves pillar editorial role');
$inventoryByIdentity = array();
foreach ($inventory['pillars'][0]['items'] as $inventoryItem) {
    $inventoryByIdentity[$inventoryItem['type'] . ':' . $inventoryItem['id']] = $inventoryItem;
}
hubEditorialAssert(isset($inventoryByIdentity['staticpages:pillar-b']), 'inventory contains the approved nested pillar identity');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['relation_role'] === 'sub-pillar', 'inventory preserves structural relation roles');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['is_nested_pillar'] === true, 'inventory identifies related objects that are also pillars');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['nested_pillar_id'] === 2, 'inventory exposes nested pillar identity without duplicating content');
hubEditorialAssert($inventoryByIdentity['staticpages:pillar-b']['parent_count'] === 2, 'inventory exposes multi-parent participation');
hubEditorialAssert(isset($inventoryByIdentity['article:story-x']), 'inventory remains provider-neutral by stable type + id');
hubEditorialAssert($inventoryByIdentity['article:story-x']['relation_role'] === 'satellite', 'inventory keeps structural role separately');
hubEditorialAssert($inventoryByIdentity['article:story-x']['editorial_role'] === 'tutorial', 'inventory keeps editorial role separately');

$suggestions = HUB_editorialSuggestions(1, 10);
hubEditorialAssert($suggestions['schema'] === 1, 'suggestion schema is explicit');
hubEditorialAssert($suggestions['generated_from'] === array('shared-topic'), 'suggestion source is explicit and explainable');
hubEditorialAssert(count($suggestions['pillars']) === 1, 'suggestions can be scoped to one pillar');
hubEditorialAssert(count($suggestions['pillars'][0]['candidates']) === 1, 'already approved identities are excluded from candidates');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['id'] === 'story-new', 'unapproved matching article is suggested');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['suggested_role'] === 'satellite', 'candidate role remains a suggestion only');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['score'] === 2, 'candidate score is deterministic from matched topics');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][0]['signal'] === 'shared-topic', 'candidate retains evidence signal');
hubEditorialAssert(count($suggestions['pillars'][0]['candidates'][0]['evidence'][0]['topics']) === 2, 'candidate retains matched topic evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['views'] === 250, 'candidate exposes engagement ranking evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['comments'] === 9, 'candidate exposes comment ranking evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['ranking']['published_year'] === 2024, 'candidate exposes publication year');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][1]['signal'] === 'engagement', 'candidate keeps engagement as transparent evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][2]['signal'] === 'publication-date', 'candidate keeps publication date as transparent evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][3]['signal'] === 'temporal-marker', 'candidate carries temporal-marker evidence');
hubEditorialAssert($suggestions['pillars'][0]['candidates'][0]['evidence'][3]['review_recommended'] === true, 'candidate temporal markers recommend human review');
hubEditorialAssert(in_array('engagement', $suggestions['ranking_signals'], true), 'suggestion payload declares engagement ranking signal');
hubEditorialAssert(in_array('publication-date', $suggestions['ranking_signals'], true), 'suggestion payload declares publication-date ranking signal');
hubEditorialAssert(in_array('older-year-marker', $suggestions['review_signals'], true), 'suggestion payload declares older year review signal');
hubEditorialAssert(in_array('version-marker', $suggestions['review_signals'], true), 'suggestion payload declares version review signal');
hubEditorialAssert(in_array('title-token-overlap', $suggestions['review_signals'], true), 'suggestion payload declares title overlap review signal');

$similarity = HUB_editorialTitleSimilarity(
    'LibreOffice migration guide for Windows',
    'LibreOffice migration guide for Linux'
);
hubEditorialAssert($similarity['similarity'] >= 0.50, 'close titles reach deterministic lexical threshold');
hubEditorialAssert(in_array('libreoffice', $similarity['common_tokens'], true), 'title similarity retains common lexical evidence');
hubEditorialAssert(in_array('migration', $similarity['common_tokens'], true), 'title similarity retains multiple common tokens');

$hubEditorialCloseMode = true;
$closeCandidates = HUB_editorialCloseContentCandidates($hubEditorialPillars[0], 10);
hubEditorialAssert(count($closeCandidates) === 1, 'close-content detection requires shared topics and lexical overlap');
hubEditorialAssert($closeCandidates[0]['left']['id'] === 'close-a', 'close-content pair keeps first stable identity');
hubEditorialAssert($closeCandidates[0]['right']['id'] === 'close-b', 'close-content pair keeps second stable identity');
hubEditorialAssert($closeCandidates[0]['score'] >= 50, 'close-content score is deterministic percentage');
hubEditorialAssert($closeCandidates[0]['evidence'][0]['signal'] === 'shared-topic', 'close-content keeps shared-topic evidence');
hubEditorialAssert($closeCandidates[0]['evidence'][1]['signal'] === 'title-token-overlap', 'close-content keeps lexical evidence');
hubEditorialAssert($closeCandidates[0]['review_recommended'] === true, 'close-content remains a human-review suggestion');

$pairId = $closeCandidates[0]['pair_id'];
$hubHiddenSuggestions['close-content:1:article-pair:' . $pairId] = true;
$hiddenCloseCandidates = HUB_editorialCloseContentCandidates($hubEditorialPillars[0], 10);
hubEditorialAssert(empty($hiddenCloseCandidates), 'dismissed/deferred close-content pair is filtered');
unset($hubHiddenSuggestions['close-content:1:article-pair:' . $pairId]);
$hubEditorialCloseMode = false;

$hubEditorialGapMode = true;
$contentGaps = HUB_editorialContentGaps($hubEditorialPillars[0], 10);
hubEditorialAssert(count($contentGaps) === 2, 'content-gap detection distinguishes zero and single article coverage');

$gapsByTopic = array();
foreach ($contentGaps as $gap) {
    $gapsByTopic[$gap['topic_id']] = $gap;
}
hubEditorialAssert($gapsByTopic['seo']['kind'] === 'create-content', 'zero article topic becomes create-content opportunity');
hubEditorialAssert($gapsByTopic['seo']['article_count'] === 0, 'zero article gap exposes exact coverage count');
hubEditorialAssert($gapsByTopic['seo']['priority'] === 100, 'zero article gap gets highest deterministic priority');
hubEditorialAssert($gapsByTopic['content']['kind'] === 'review-thin-coverage', 'single article topic becomes thin coverage review');
hubEditorialAssert($gapsByTopic['content']['article_count'] === 1, 'thin coverage gap exposes exact coverage count');
hubEditorialAssert($gapsByTopic['content']['priority'] === 50, 'thin coverage gap gets lower deterministic priority');
hubEditorialAssert($gapsByTopic['seo']['evidence'][0]['signal'] === 'topic-coverage', 'content gap keeps topic-coverage evidence');

$hubHiddenSuggestions['content-gap:1:topic:seo'] = true;
$hiddenContentGaps = HUB_editorialContentGaps($hubEditorialPillars[0], 10);
hubEditorialAssert(count($hiddenContentGaps) === 1, 'dismissed/deferred content gap is filtered');
hubEditorialAssert($hiddenContentGaps[0]['topic_id'] === 'content', 'unhidden content gap remains visible');
unset($hubHiddenSuggestions['content-gap:1:topic:seo']);
$hubEditorialGapMode = false;

$temporal = HUB_editorialTemporalSignals(array(
    'title' => 'LibreOffice 2024 and 26.8',
    'introtext' => 'Upgrade notes for v1.5.0',
    'bodytext' => '',
    'date' => '2023-05-01 10:00:00',
), 2026);
hubEditorialAssert($temporal['publication_age_years'] === 3, 'temporal signal exposes publication age');
hubEditorialAssert($temporal['explicit_years'] === array(2024), 'temporal signal extracts explicit year markers');
hubEditorialAssert(in_array('26.8', $temporal['version_markers'], true), 'temporal signal extracts two-part version markers');
hubEditorialAssert(in_array('v1.5.0', $temporal['version_markers'], true), 'temporal signal extracts prefixed semantic versions');
hubEditorialAssert($temporal['review_recommended'] === true, 'older year/version markers recommend editorial review');
hubEditorialAssert(in_array('older-year-marker', $temporal['review_reasons'], true), 'older year reason is explicit');
hubEditorialAssert(in_array('version-marker', $temporal['review_reasons'], true), 'version reason is explicit');

$temporalNeutral = HUB_editorialTemporalSignals(array(
    'title' => 'Evergreen guide',
    'introtext' => 'General principles without dated markers',
    'bodytext' => '',
    'date' => '2025-01-01 00:00:00',
), 2026);
hubEditorialAssert($temporalNeutral['review_recommended'] === false, 'publication age alone does not mark evergreen content for review');
hubEditorialAssert(empty($temporalNeutral['review_reasons']), 'neutral content has no temporal review reason');

$allSuggestions = HUB_editorialSuggestions(0, 10);
hubEditorialAssert(isset($allSuggestions['pillar_candidates']), 'global suggestions expose pillar candidates');
hubEditorialAssert(count($allSuggestions['pillar_candidates']) === 1, 'existing Static Page pillars are excluded from pillar candidates');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['id'] === 'new-pillar', 'unapproved Static Page is suggested as a pillar candidate');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['score'] === 2, 'pillar candidate score is deterministic from matching published articles');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['evidence'][0]['signal'] === 'shared-topic', 'pillar candidate retains shared-topic evidence');
hubEditorialAssert($allSuggestions['pillar_candidates'][0]['evidence'][0]['matching_article_count'] === 2, 'pillar candidate exposes matching article count');

$hubHiddenSuggestions['relation:1:article:story-new'] = true;
$hiddenRelationSuggestions = HUB_editorialSuggestions(1, 10);
hubEditorialAssert(empty($hiddenRelationSuggestions['pillars']), 'active relation decision suppresses candidate');
unset($hubHiddenSuggestions['relation:1:article:story-new']);

$hubHiddenSuggestions['pillar:0:staticpages:new-pillar'] = true;
$hiddenPillarSuggestions = HUB_editorialSuggestions(0, 10);
hubEditorialAssert(empty($hiddenPillarSuggestions['pillar_candidates']), 'active pillar decision suppresses pillar candidate');
unset($hubHiddenSuggestions['pillar:0:staticpages:new-pillar']);

$roadmap = HUB_editorialRoadmap(10);
hubEditorialAssert($roadmap['schema'] === 1, 'editorial roadmap schema is explicit');
hubEditorialAssert($roadmap['scope'] === 'editorial-0.8', 'editorial roadmap declares its 0.8 scope');
hubEditorialAssert($roadmap['executive_summary']['pillars'] === 3, 'editorial roadmap reuses structural summary');
hubEditorialAssert($roadmap['executive_summary']['new_pillar_opportunities'] === 1, 'editorial roadmap exposes new pillar opportunities');
hubEditorialAssert(isset($roadmap['executive_summary']['close_content_review_pairs']), 'editorial roadmap exposes close-content review count');
hubEditorialAssert(isset($roadmap['executive_summary']['content_gaps']), 'editorial roadmap exposes content-gap count');
hubEditorialAssert(!empty($roadmap['existing_pillars']), 'editorial roadmap exposes existing pillars');
hubEditorialAssert(!empty($roadmap['prioritized_next_actions']), 'editorial roadmap exposes prioritized next actions');
hubEditorialAssert(in_array('cluster-health', $roadmap['deferred_diagnostics'], true), '0.9 cluster health stays explicitly deferred');
hubEditorialAssert(in_array('canonical-consistency', $roadmap['deferred_diagnostics'], true), '0.9 canonical diagnostics stay explicitly deferred');

$markdown = HUB_editorialRoadmapMarkdown($roadmap);
hubEditorialAssert(strpos($markdown, '# Editorial roadmap') !== false, 'roadmap Markdown has stable title');
hubEditorialAssert(strpos($markdown, '## Existing pillars') !== false, 'roadmap Markdown exposes existing pillars');
hubEditorialAssert(strpos($markdown, '## New pillar opportunities') !== false, 'roadmap Markdown exposes pillar opportunities');
hubEditorialAssert(strpos($markdown, '## Potential cannibalization / close-content review') !== false, 'roadmap Markdown exposes close-content review section');
hubEditorialAssert(strpos($markdown, '## Content gaps to create or refresh') !== false, 'roadmap Markdown exposes content-gap section');
hubEditorialAssert(strpos($markdown, '## Prioritized next actions') !== false, 'roadmap Markdown exposes prioritized actions');
hubEditorialAssert(strpos($markdown, '## Deferred diagnostics') !== false, 'roadmap Markdown states deferred diagnostics');

$source = file_get_contents(dirname(__DIR__) . '/lib-editorial.php');
$summaryStart = strpos($source, 'function HUB_editorialSummary(');
$summaryEnd = strpos($source, 'function HUB_editorialInventory(', $summaryStart);
$summarySource = ($summaryStart !== false && $summaryEnd !== false)
    ? substr($source, $summaryStart, $summaryEnd - $summaryStart)
    : '';

hubEditorialAssert(strpos($summarySource, 'HUB_resolveObject(') === false, 'structural summary does not mix provider metadata resolution');
hubEditorialAssert(strpos($summarySource, 'HUB_linkAudit') === false, 'structural summary does not mix SEO/link diagnostics');
hubEditorialAssert(strpos($source, 'HUB_saveRelation(') === false, 'suggestion model never auto-approves relationships');

$adminSource = file_get_contents(dirname(__DIR__) . '/admin/editorial.php');
hubEditorialAssert(strpos($adminSource, 'Approved relation inventory') !== false, 'editorial admin exposes approved structural inventory');
hubEditorialAssert(strpos($adminSource, 'Structural role') !== false && strpos($adminSource, 'Editorial role') !== false, 'editorial mapping distinguishes structural and editorial roles');
hubEditorialAssert(strpos($adminSource, 'HUB_editorialInventory(false)') !== false, 'editorial admin reads the shared inventory model');
hubEditorialAssert(strpos($adminSource, 'HUB_saveRelation(') === false, 'editorial mapping view remains read-only');
hubEditorialAssert(strpos($adminSource, 'editorial.php?export=md') !== false, 'editorial admin exposes Markdown roadmap export');
hubEditorialAssert(strpos($adminSource, 'editorial.php?export=json') !== false, 'editorial admin exposes JSON roadmap export');
hubEditorialAssert(strpos($adminSource, 'Editorial roadmap preview') !== false, 'editorial admin exposes roadmap preview');
hubEditorialAssert(strpos($adminSource, 'Content gaps / opportunities') !== false, 'editorial admin exposes content-gap opportunities');
hubEditorialAssert(strpos($adminSource, 'Create-content opportunity') !== false, 'editorial admin explains zero-coverage gaps');
hubEditorialAssert(strpos($adminSource, 'Thin coverage review') !== false, 'editorial admin explains thin-coverage gaps');
hubEditorialAssert(strpos($adminSource, 'content_gap_decision') !== false, 'editorial admin exposes persisted content-gap decisions');
$relationsAdminSource = file_get_contents(dirname(__DIR__) . '/admin/relations.php');
hubEditorialAssert(strpos($relationsAdminSource, 'Review signal:') !== false, 'temporal review signals are visible in relation suggestions');
hubEditorialAssert(strpos($relationsAdminSource, 'dated marker:') !== false, 'relation suggestions explain dated marker evidence');
hubEditorialAssert(strpos($relationsAdminSource, 'version marker:') !== false, 'relation suggestions explain version marker evidence');
hubEditorialAssert(strpos($relationsAdminSource, 'Close-content review') !== false, 'relations admin exposes close-content review');
hubEditorialAssert(strpos($relationsAdminSource, 'Human review only') !== false, 'close-content admin wording avoids automatic cannibalization verdict');
hubEditorialAssert(strpos($relationsAdminSource, 'title-token-overlap') !== false, 'close-content admin reads lexical evidence');

echo "Hub editorial summary contract tests passed." . PHP_EOL;
