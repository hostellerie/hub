<?php

$_SERVER['PHP_SELF'] = 'tests/suggestion_decisions_contract.php';

$hubDecisionFixture = false;

function HUB_normalizeObjectType($type)
{
    return strtolower(trim((string) $type));
}

function HUB_normalizeObjectId($id)
{
    return trim((string) $id);
}

function DB_escapeString($value)
{
    return addslashes((string) $value);
}

function DB_query($sql, $ignore = 0)
{
    global $hubDecisionFixture;
    return array('row' => $hubDecisionFixture, 'sql' => $sql);
}

function DB_numRows($result)
{
    return isset($result['row']) && is_array($result['row']) ? 1 : 0;
}

function DB_fetchArray($result)
{
    return isset($result['row']) ? $result['row'] : false;
}

function DB_error()
{
    return false;
}

$_TABLES = array(
    'hub_suggestion_decisions' => 'gl_hub_suggestion_decisions',
);

require_once dirname(__DIR__) . '/lib-suggestion-decisions.php';

function hubDecisionAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

hubDecisionAssert(HUB_normalizeSuggestionKind('RELATION') === 'relation', 'relation kind normalizes');
hubDecisionAssert(HUB_normalizeSuggestionKind('pillar') === 'pillar', 'pillar kind is accepted');
hubDecisionAssert(HUB_normalizeSuggestionKind('close-content') === 'close-content', 'close-content suggestion kind is accepted');
hubDecisionAssert(HUB_normalizeSuggestionKind('other') === '', 'unsupported suggestion kind is rejected');
hubDecisionAssert(HUB_normalizeSuggestionDecision('dismissed') === 'dismissed', 'dismissed decision is accepted');
hubDecisionAssert(HUB_normalizeSuggestionDecision('deferred') === 'deferred', 'deferred decision is accepted');
hubDecisionAssert(HUB_normalizeSuggestionDecision('approved') === '', 'unsupported persisted decision is rejected');
hubDecisionAssert(
    HUB_suggestionPairId('article', 'b', 'article', 'a') === HUB_suggestionPairId('article', 'a', 'article', 'b'),
    'close-content pair identity is order-independent'
);

$hubDecisionFixture = array(
    'decision' => 'dismissed',
    'defer_until' => 0,
);
hubDecisionAssert(
    HUB_isSuggestionHidden('relation', 7, 'article', 'story-1', 1000) === true,
    'dismissed suggestion remains hidden'
);

$hubDecisionFixture = array(
    'decision' => 'deferred',
    'defer_until' => 2000,
);
hubDecisionAssert(
    HUB_isSuggestionHidden('relation', 7, 'article', 'story-1', 1000) === true,
    'active deferred suggestion remains hidden'
);
hubDecisionAssert(
    HUB_isSuggestionHidden('relation', 7, 'article', 'story-1', 2001) === false,
    'expired deferred suggestion becomes visible automatically'
);

$source = file_get_contents(dirname(__DIR__) . '/lib-suggestion-decisions.php');
hubDecisionAssert(strpos($source, 'ON DUPLICATE KEY UPDATE') !== false, 'decision persistence is idempotent per suggestion identity');
hubDecisionAssert(strpos($source, "decision === 'dismissed'") !== false, 'dismissed decisions clear defer date');
hubDecisionAssert(strpos($source, 'DELETE FROM') !== false, 'suggestion decisions can be restored by deletion');

$configSource = file_get_contents(dirname(__DIR__) . '/config.php');
hubDecisionAssert(strpos($configSource, "hub_suggestion_decisions") !== false, 'suggestion decision table is registered');

$installSource = file_get_contents(dirname(__DIR__) . '/sql/mysql_install.php');
hubDecisionAssert(strpos($installSource, 'suggestion_identity') !== false, 'fresh install has unique suggestion decision identity');

$upgradeSource = file_get_contents(dirname(__DIR__) . '/install_updates.php');
hubDecisionAssert(strpos($upgradeSource, 'function HUB_updateSchema_0_8_0()') !== false, '0.8 development upgrade is available');
hubDecisionAssert(strpos($upgradeSource, 'CREATE TABLE IF NOT EXISTS') !== false, '0.8 decision migration is idempotent');

$functionsSource = file_get_contents(dirname(__DIR__) . '/functions.inc');
hubDecisionAssert(strpos($functionsSource, 'HUB_updateSchema_0_8_0()') !== false, '0.8 schema ensure runs even for existing 0.8 development installs');

$adminSource = file_get_contents(dirname(__DIR__) . '/admin/relations.php');
hubDecisionAssert(strpos($adminSource, 'Defer 30 days') !== false, 'relations admin exposes defer action');
hubDecisionAssert(strpos($adminSource, '>Dismiss<') !== false, 'relations admin exposes dismiss action');
hubDecisionAssert(strpos($adminSource, '>Restore<') !== false, 'relations admin exposes restore action');

echo "Hub suggestion decision contract tests passed." . PHP_EOL;
