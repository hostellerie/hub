<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

/**
 * Normalize the supported suggestion kind.
 *
 * @param string $kind
 * @return string
 */
function HUB_normalizeSuggestionKind($kind)
{
    $kind = strtolower(trim((string) $kind));
    $allowed = array('relation', 'pillar', 'close-content', 'content-gap');

    return in_array($kind, $allowed, true) ? $kind : '';
}

/**
 * Build a stable compact identity for an unordered content pair.
 *
 * @param string $leftType
 * @param string $leftId
 * @param string $rightType
 * @param string $rightId
 * @return string
 */
function HUB_suggestionPairId($leftType, $leftId, $rightType, $rightId)
{
    $left = HUB_normalizeObjectType($leftType) . ':' . HUB_normalizeObjectId($leftId);
    $right = HUB_normalizeObjectType($rightType) . ':' . HUB_normalizeObjectId($rightId);

    if ($left === ':' || $right === ':') {
        return '';
    }

    $pair = array($left, $right);
    sort($pair, SORT_STRING);

    return sha1($pair[0] . "\n" . $pair[1]);
}

/**
 * Normalize the supported persisted decision.
 *
 * @param string $decision
 * @return string
 */
function HUB_normalizeSuggestionDecision($decision)
{
    $decision = strtolower(trim((string) $decision));
    $allowed = array('dismissed', 'deferred');

    return in_array($decision, $allowed, true) ? $decision : '';
}

/**
 * Return one persisted suggestion decision, if any.
 *
 * Expired deferred decisions are treated as absent so the suggestion becomes
 * visible again automatically.
 *
 * @param string $kind
 * @param int $pillarId
 * @param string $itemType
 * @param string $itemId
 * @param int|null $now
 * @return array|false
 */
function HUB_getSuggestionDecision($kind, $pillarId, $itemType, $itemId, $now = null)
{
    global $_TABLES;

    $kind = HUB_normalizeSuggestionKind($kind);
    $pillarId = max(0, (int) $pillarId);
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);
    $now = $now === null ? time() : (int) $now;

    if ($kind === '' || $itemType === '' || $itemId === '' || empty($_TABLES['hub_suggestion_decisions'])) {
        return false;
    }

    $sql = "SELECT * FROM {$_TABLES['hub_suggestion_decisions']} "
         . "WHERE suggestion_kind = '" . DB_escapeString($kind) . "' "
         . "AND pillar_id = " . $pillarId . " "
         . "AND item_type = '" . DB_escapeString($itemType) . "' "
         . "AND item_id = '" . DB_escapeString($itemId) . "' LIMIT 1";

    $result = DB_query($sql, 1);
    if ($result === false || DB_numRows($result) < 1) {
        return false;
    }

    $row = DB_fetchArray($result);
    if (!is_array($row)) {
        return false;
    }

    $decision = HUB_normalizeSuggestionDecision(isset($row['decision']) ? $row['decision'] : '');
    $deferUntil = isset($row['defer_until']) ? (int) $row['defer_until'] : 0;

    if ($decision === 'deferred' && $deferUntil > 0 && $deferUntil <= $now) {
        return false;
    }

    $row['decision'] = $decision;
    $row['defer_until'] = $deferUntil;

    return $row;
}

/**
 * Persist or replace a suggestion decision.
 *
 * @param string $kind
 * @param int $pillarId
 * @param string $itemType
 * @param string $itemId
 * @param string $decision
 * @param int $deferUntil
 * @param int $ownerId
 * @return bool
 */
function HUB_saveSuggestionDecision(
    $kind,
    $pillarId,
    $itemType,
    $itemId,
    $decision,
    $deferUntil = 0,
    $ownerId = 0
) {
    global $_TABLES, $_USER;

    $kind = HUB_normalizeSuggestionKind($kind);
    $pillarId = max(0, (int) $pillarId);
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);
    $decision = HUB_normalizeSuggestionDecision($decision);
    $deferUntil = max(0, (int) $deferUntil);

    if ($kind === '' || $itemType === '' || $itemId === '' || $decision === '') {
        return false;
    }

    if ($decision === 'dismissed') {
        $deferUntil = 0;
    }

    if ($ownerId < 1) {
        $ownerId = isset($_USER['uid']) ? (int) $_USER['uid'] : 2;
        if ($ownerId < 1) {
            $ownerId = 2;
        }
    }

    $now = time();

    $sql = "INSERT INTO {$_TABLES['hub_suggestion_decisions']} "
         . "(suggestion_kind, pillar_id, item_type, item_id, decision, defer_until, created, modified, owner_id) VALUES ("
         . "'" . DB_escapeString($kind) . "', "
         . $pillarId . ", "
         . "'" . DB_escapeString($itemType) . "', "
         . "'" . DB_escapeString($itemId) . "', "
         . "'" . DB_escapeString($decision) . "', "
         . $deferUntil . ", "
         . $now . ", "
         . $now . ", "
         . $ownerId
         . ") ON DUPLICATE KEY UPDATE "
         . "decision = VALUES(decision), "
         . "defer_until = VALUES(defer_until), "
         . "modified = VALUES(modified), "
         . "owner_id = VALUES(owner_id)";

    DB_query($sql, 1);

    return !DB_error();
}

/**
 * Remove one persisted suggestion decision so the candidate may appear again.
 *
 * @param string $kind
 * @param int $pillarId
 * @param string $itemType
 * @param string $itemId
 * @return bool
 */
function HUB_deleteSuggestionDecision($kind, $pillarId, $itemType, $itemId)
{
    global $_TABLES;

    $kind = HUB_normalizeSuggestionKind($kind);
    $pillarId = max(0, (int) $pillarId);
    $itemType = HUB_normalizeObjectType($itemType);
    $itemId = HUB_normalizeObjectId($itemId);

    if ($kind === '' || $itemType === '' || $itemId === '') {
        return false;
    }

    DB_query(
        "DELETE FROM {$_TABLES['hub_suggestion_decisions']} "
        . "WHERE suggestion_kind = '" . DB_escapeString($kind) . "' "
        . "AND pillar_id = " . $pillarId . " "
        . "AND item_type = '" . DB_escapeString($itemType) . "' "
        . "AND item_id = '" . DB_escapeString($itemId) . "'",
        1
    );

    return !DB_error();
}

/**
 * List persisted suggestion decisions for administration.
 *
 * @param int|null $now
 * @return array
 */
function HUB_getSuggestionDecisions($now = null)
{
    global $_TABLES;

    $now = $now === null ? time() : (int) $now;
    $rows = array();

    if (empty($_TABLES['hub_suggestion_decisions'])) {
        return $rows;
    }

    $result = DB_query(
        "SELECT * FROM {$_TABLES['hub_suggestion_decisions']} "
        . "ORDER BY modified DESC, id DESC",
        1
    );

    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (!is_array($row)) {
            continue;
        }

        $decision = HUB_normalizeSuggestionDecision(isset($row['decision']) ? $row['decision'] : '');
        $deferUntil = isset($row['defer_until']) ? (int) $row['defer_until'] : 0;
        $row['decision'] = $decision;
        $row['defer_until'] = $deferUntil;
        $row['is_active'] = $decision === 'dismissed'
            || ($decision === 'deferred' && ($deferUntil === 0 || $deferUntil > $now));
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Whether a candidate should currently be hidden by editorial decision.
 *
 * @param string $kind
 * @param int $pillarId
 * @param string $itemType
 * @param string $itemId
 * @param int|null $now
 * @return bool
 */
function HUB_isSuggestionHidden($kind, $pillarId, $itemType, $itemId, $now = null)
{
    return is_array(HUB_getSuggestionDecision($kind, $pillarId, $itemType, $itemId, $now));
}
