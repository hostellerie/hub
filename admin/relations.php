<?php

require_once '../../../lib-common.php';
require_once '../../auth.inc.php';
require_once $_CONF['path'] . 'system/lib-admin.php';
require_once $_CONF['path'] . 'plugins/hub/lib-admin-ui.php';

if (!SEC_hasRights('hub.admin')) {
    COM_accessLog('User ' . (int) $_USER['uid'] . ' attempted to access Hub relationships without permission.');
    $display = COM_startBlock('Access denied') . 'You do not have sufficient rights to access this page.' . COM_endBlock();
    COM_output(COM_createHTMLDocument($display));
    exit;
}

function HUB_relAdminEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function HUB_relAdminTokenField()
{
    return '<input type="hidden" name="' . CSRF_TOKEN . '" value="' . HUB_relAdminEscape(SEC_createToken()) . '">';
}

function HUB_relAdminStaticPages()
{
    global $_TABLES;

    $rows = array();
    if (empty($_TABLES['staticpage'])) {
        return $rows;
    }

    $result = DB_query(
        "SELECT sp_id, sp_title FROM {$_TABLES['staticpage']} ORDER BY sp_title, sp_id",
        1
    );
    if ($result === false) {
        return $rows;
    }

    while ($row = DB_fetchArray($result)) {
        if (is_array($row) && !empty($row['sp_id'])) {
            $rows[] = $row;
        }
    }

    return $rows;
}

if (isset($_GET['hub_ajax']) && $_GET['hub_ajax'] === 'items') {
    $type = isset($_GET['type']) ? HUB_normalizeObjectType($_GET['type']) : '';
    $payload = HUB_relationObjectOptions($type, 100);

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload);
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SEC_checkToken()) {
        $message = '<div class="hub-rel-message hub-rel-error">Invalid security token.</div>';
    } else {
        $action = isset($_POST['hub_action']) ? (string) $_POST['hub_action'] : '';

        if ($action === 'save_pillar') {
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $sourceId = isset($_POST['source_id']) ? (string) $_POST['source_id'] : '';
            $titleOverride = isset($_POST['title_override']) ? (string) $_POST['title_override'] : '';
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;

            $saved = HUB_savePillar($pillarId, 'staticpages', $sourceId, $titleOverride, $enabled);
            $message = $saved
                ? '<div class="hub-rel-message">Pillar saved.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to save pillar. The Static Page may already be registered.</div>';
        } elseif ($action === 'delete_pillar') {
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $message = HUB_deletePillar($pillarId)
                ? '<div class="hub-rel-message">Pillar and its relations deleted.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to delete pillar.</div>';
        } elseif ($action === 'save_relation') {
            $relationId = isset($_POST['relation_id']) ? (int) $_POST['relation_id'] : 0;
            $pillarId = isset($_POST['pillar_id']) ? (int) $_POST['pillar_id'] : 0;
            $itemType = isset($_POST['item_type']) ? (string) $_POST['item_type'] : '';
            if ($itemType === '__custom__') {
                $itemType = isset($_POST['item_type_custom']) ? (string) $_POST['item_type_custom'] : '';
            }
            $itemIdChoice = isset($_POST['item_id_choice']) ? (string) $_POST['item_id_choice'] : '';
            $itemIdManual = isset($_POST['item_id_manual']) ? (string) $_POST['item_id_manual'] : '';
            $itemId = ($itemIdChoice !== '' && $itemIdChoice !== '__manual__')
                ? $itemIdChoice
                : $itemIdManual;
            $position = isset($_POST['position']) ? (int) $_POST['position'] : 0;
            $enabled = !empty($_POST['is_enabled']) ? 1 : 0;

            $saved = HUB_saveRelation($relationId, $pillarId, $itemType, $itemId, $position, $enabled);
            $message = $saved
                ? '<div class="hub-rel-message">Relation saved.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to save relation. Check the pillar and unique type + id.</div>';
        } elseif ($action === 'delete_relation') {
            $relationId = isset($_POST['relation_id']) ? (int) $_POST['relation_id'] : 0;
            $message = HUB_deleteRelation($relationId)
                ? '<div class="hub-rel-message">Relation deleted.</div>'
                : '<div class="hub-rel-message hub-rel-error">Unable to delete relation.</div>';
        }
    }
}

$staticPages = HUB_relAdminStaticPages();
$objectTypes = HUB_relationObjectTypes();
$pillars = HUB_getPillars(true);

$content = '<style>'
    . '.hub-rel-nav{margin:0 0 1rem}.hub-rel-nav a{margin-right:1rem}'
    . '.hub-rel-message{padding:.7rem 1rem;margin:0 0 1rem;background:#edf7ed;border-left:4px solid #2e7d32}'
    . '.hub-rel-error{background:#fff1f0;border-left-color:#b00020}'
    . '.hub-rel-card{border:1px solid #d5d8dc;padding:1rem;margin:0 0 1rem;border-radius:4px}'
    . '.hub-rel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}'
    . '.hub-rel-grid label{display:block;font-weight:600}.hub-rel-grid input:not([type=checkbox]),.hub-rel-grid select{box-sizing:border-box;width:100%;padding:.45rem}'
    . '.hub-rel-grid input[type=checkbox]{width:auto;margin:0}'
    . '.hub-rel-check{display:flex!important;flex-direction:column;justify-content:flex-end;min-height:4.15rem}'
    . '.hub-rel-check>span:first-child{margin-bottom:.55rem}.hub-rel-check-control{display:flex;align-items:center;min-height:2.45rem}'
    . '.hub-rel-item-note{display:block;margin-top:.35rem;font-weight:400;opacity:.72;font-size:.88em}'
    . '.hub-rel-table{width:100%;border-collapse:collapse;margin-top:1rem}.hub-rel-table th,.hub-rel-table td{padding:.45rem;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}'
    . '.hub-rel-actions form{display:inline}.hub-rel-muted{opacity:.7;font-size:.92em}'
    . '.hub-rel-integrity{margin:.55rem 0;padding:.55rem .7rem;border-left:4px solid #d7a900;background:#fffbea}'
    . '.hub-rel-ok{display:inline-block;padding:.12rem .45rem;border-radius:10px;background:#edf7ed;font-size:.86em}'
    . '.hub-rel-unresolved{display:inline-block;padding:.12rem .45rem;border-radius:10px;background:#fff1f0;font-size:.86em}'
    . '</style>';

$content .= HUB_adminNavigation('relations');

$content .= '<h1>Pillars &amp; manual relations</h1>';
$content .= '<p>Hub 0.3.0 stores only stable <code>type + id</code> identities. Titles and URLs are resolved dynamically from the owning Geeklog provider.</p>';
$content .= '<p class="hub-rel-muted">Known relation types are discovered from active Geeklog Item Info providers. When a provider exposes the shared collection contract, Hub can also list its selectable objects without querying plugin-private tables. Choose <em>Custom / other…</em> only when a provider type is not listed.</p>';
$content .= $message;

$content .= '<div class="hub-rel-card"><h2>Add Static Page pillar</h2>';
$content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
$content .= '<input type="hidden" name="hub_action" value="save_pillar">';
$content .= '<input type="hidden" name="pillar_id" value="0">';
$content .= '<div class="hub-rel-grid"><label>Static Page<select name="source_id" required>';
$content .= '<option value="">Select a Static Page</option>';
foreach ($staticPages as $page) {
    $content .= '<option value="' . HUB_relAdminEscape($page['sp_id']) . '">'
        . HUB_relAdminEscape($page['sp_title'] . ' [' . $page['sp_id'] . ']') . '</option>';
}
$content .= '</select></label>';
$content .= '<label>Optional title override<input type="text" name="title_override" maxlength="255"></label>';
$content .= '<label class="hub-rel-check"><span>Enabled</span><span class="hub-rel-check-control"><input type="checkbox" name="is_enabled" value="1" checked></span></label>';
$content .= '<div><button type="submit" class="uk-button uk-button-primary">Add pillar</button></div></div></form></div>';

if (empty($pillars)) {
    $content .= '<p>No pillar has been created yet.</p>';
} else {
    foreach ($pillars as $pillar) {
        $resolvedPillar = HUB_resolveObject($pillar['source_type'], $pillar['source_id']);
        $pillarTitle = trim((string) $pillar['title_override']) !== ''
            ? $pillar['title_override']
            : $resolvedPillar['title'];
        $relations = HUB_getRelations($pillar['id'], true);

        $content .= '<div class="hub-rel-card">';
        $content .= '<h2>' . HUB_relAdminEscape($pillarTitle) . '</h2>';
        $content .= '<p class="hub-rel-muted"><code>' . HUB_relAdminEscape($pillar['source_type'])
            . ':' . HUB_relAdminEscape($pillar['source_id']) . '</code>'
            . (!empty($pillar['is_enabled']) ? ' · enabled' : ' · disabled');
        if (!empty($resolvedPillar['url'])) {
            $content .= ' · <a href="' . HUB_relAdminEscape($resolvedPillar['url']) . '">View source</a>';
        }
        $content .= '</p>';
        if (empty($resolvedPillar['exists'])) {
            $content .= '<div class="hub-rel-integrity"><strong>Pillar source unresolved.</strong> '
                . HUB_relAdminEscape($resolvedPillar['diagnostic']) . '</div>';
        }

        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_pillar">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<input type="hidden" name="source_id" value="' . HUB_relAdminEscape($pillar['source_id']) . '">';
        $content .= '<div class="hub-rel-grid">';
        $content .= '<label>Title override<input type="text" name="title_override" maxlength="255" value="' . HUB_relAdminEscape($pillar['title_override']) . '"></label>';
        $content .= '<label class="hub-rel-check"><span>Enabled</span><span class="hub-rel-check-control"><input type="checkbox" name="is_enabled" value="1"' . (!empty($pillar['is_enabled']) ? ' checked' : '') . '></span></label>';
        $content .= '<div><button type="submit" class="uk-button">Update pillar</button></div></div></form>';

        $content .= '<h3>Relations</h3>';
        if (empty($relations)) {
            $content .= '<p class="hub-rel-muted">No manual relation yet.</p>';
        } else {
            $content .= '<table class="hub-rel-table"><thead><tr><th>Order</th><th>Type + id</th><th>Resolved item</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            foreach ($relations as $relation) {
                $resolved = HUB_resolveObject($relation['item_type'], $relation['item_id']);
                $content .= '<tr><td colspan="5"><form method="post" action="relations.php" class="hub-rel-grid">'
                    . HUB_relAdminTokenField()
                    . '<input type="hidden" name="hub_action" value="save_relation">'
                    . '<input type="hidden" name="relation_id" value="' . (int) $relation['id'] . '">'
                    . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
                    . '<input type="hidden" name="item_type" value="' . HUB_relAdminEscape($relation['item_type']) . '">'
                    . '<input type="hidden" name="item_id" value="' . HUB_relAdminEscape($relation['item_id']) . '">'
                    . '<label>Order<input type="number" name="position" min="0" max="65535" value="' . (int) $relation['position'] . '"></label>'
                    . '<div><strong><code>' . HUB_relAdminEscape($relation['item_type']) . ':' . HUB_relAdminEscape($relation['item_id']) . '</code></strong><br>'
                    . HUB_relAdminEscape($resolved['title'])
                    . (!empty($resolved['url']) ? ' · <a href="' . HUB_relAdminEscape($resolved['url']) . '">View</a>' : '')
                    . '<br>'
                    . (!empty($resolved['exists'])
                        ? '<span class="hub-rel-ok">Resolved</span>'
                        : '<span class="hub-rel-unresolved">Unresolved</span> <span class="hub-rel-muted">' . HUB_relAdminEscape($resolved['diagnostic']) . '</span>')
                    . '</div>'
                    . '<label class="hub-rel-check"><span>Enabled</span><span class="hub-rel-check-control"><input type="checkbox" name="is_enabled" value="1"' . (!empty($relation['is_enabled']) ? ' checked' : '') . '></span></label>'
                    . '<div><button type="submit" class="uk-button">Update</button></div>'
                    . '</form><form method="post" action="relations.php" style="margin-top:.4rem">'
                    . HUB_relAdminTokenField()
                    . '<input type="hidden" name="hub_action" value="delete_relation">'
                    . '<input type="hidden" name="relation_id" value="' . (int) $relation['id'] . '">'
                    . '<button type="submit" class="uk-button">Delete</button></form></td></tr>';
            }
            $content .= '</tbody></table>';
        }

        $content .= '<h3>Add relation</h3>';
        $content .= '<form method="post" action="relations.php">' . HUB_relAdminTokenField();
        $content .= '<input type="hidden" name="hub_action" value="save_relation">';
        $content .= '<input type="hidden" name="relation_id" value="0">';
        $content .= '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">';
        $content .= '<div class="hub-rel-grid">';
        $hubTypeSelectId = 'hub-item-type-' . (int) $pillar['id'];
        $hubTypeCustomId = 'hub-item-type-custom-' . (int) $pillar['id'];
        $hubItemSelectId = 'hub-item-id-' . (int) $pillar['id'];
        $hubItemManualId = 'hub-item-id-manual-' . (int) $pillar['id'];
        $hubItemNoteId = 'hub-item-note-' . (int) $pillar['id'];
        $content .= '<label>Item type<select class="hub-rel-type-select" id="' . $hubTypeSelectId . '" name="item_type"'
            . ' data-custom-id="' . $hubTypeCustomId . '" data-item-select-id="' . $hubItemSelectId . '"'
            . ' data-item-manual-id="' . $hubItemManualId . '" data-note-id="' . $hubItemNoteId . '" required>';
        $content .= '<option value="">Select a type</option>';
        foreach ($objectTypes as $objectType) {
            $content .= '<option value="' . HUB_relAdminEscape($objectType) . '">' . HUB_relAdminEscape($objectType) . '</option>';
        }
        $content .= '<option value="__custom__">Custom / other…</option></select></label>';
        $content .= '<label id="' . $hubTypeCustomId . '-wrap" style="display:none">Custom type<input type="text" id="' . $hubTypeCustomId . '" name="item_type_custom" maxlength="64" autocomplete="off"></label>';
        $content .= '<label id="' . $hubItemSelectId . '-wrap">Item<select class="hub-rel-item-select" id="' . $hubItemSelectId . '" name="item_id_choice" disabled><option value="">Select a type first</option></select>'
            . '<span class="hub-rel-item-note" id="' . $hubItemNoteId . '"></span></label>';
        $content .= '<label id="' . $hubItemManualId . '-wrap" style="display:none">Item id<input type="text" id="' . $hubItemManualId . '" name="item_id_manual" maxlength="128" autocomplete="off">'
            . '<span class="hub-rel-item-note">Manual fallback for providers without a collection or for an ID not listed above.</span></label>';
        $content .= '<label>Order<input type="number" name="position" min="0" max="65535" value="' . (count($relations) * 10 + 10) . '"></label>';
        $content .= '<label class="hub-rel-check"><span>Enabled</span><span class="hub-rel-check-control"><input type="checkbox" name="is_enabled" value="1" checked></span></label>';
        $content .= '<div><button type="submit" class="uk-button uk-button-primary">Add relation</button></div>';
        $content .= '</div></form>';

        $content .= '<hr><form method="post" action="relations.php" onsubmit="return confirm(\'Delete this pillar and all its relations?\');">'
            . HUB_relAdminTokenField()
            . '<input type="hidden" name="hub_action" value="delete_pillar">'
            . '<input type="hidden" name="pillar_id" value="' . (int) $pillar['id'] . '">'
            . '<button type="submit" class="uk-button">Delete pillar</button></form>';

        $content .= '</div>';
    }
}

$content .= '<script>(function(){'
    . 'function byId(id){return document.getElementById(id);}'
    . 'function setManual(input,wrap,on){if(wrap){wrap.style.display=on?"block":"none";}if(input){input.required=on;if(!on){input.value="";}}}'
    . 'function resetItems(select,note,text){select.innerHTML="";var option=document.createElement("option");option.value="";option.textContent=text||"Select an item";select.appendChild(option);select.disabled=true;if(note){note.textContent="";}}'
    . 'var types=document.querySelectorAll(".hub-rel-type-select");'
    . 'for(var i=0;i<types.length;i++){(function(typeSelect){'
    . 'var customId=typeSelect.getAttribute("data-custom-id"),customInput=byId(customId),customWrap=byId(customId+"-wrap");'
    . 'var itemId=typeSelect.getAttribute("data-item-select-id"),itemSelect=byId(itemId),itemWrap=byId(itemId+"-wrap");'
    . 'var manualId=typeSelect.getAttribute("data-item-manual-id"),manualInput=byId(manualId),manualWrap=byId(manualId+"-wrap");'
    . 'var note=byId(typeSelect.getAttribute("data-note-id"));'
    . 'function showManual(message){if(itemWrap){itemWrap.style.display="none";}setManual(manualInput,manualWrap,true);if(note){note.textContent=message||"";}}'
    . 'function loadItems(){var type=typeSelect.value,custom=type==="__custom__";'
    . 'if(customWrap){customWrap.style.display=custom?"block":"none";}if(customInput){customInput.required=custom;if(!custom){customInput.value="";}}'
    . 'resetItems(itemSelect,note,"Loading…");setManual(manualInput,manualWrap,false);if(itemWrap){itemWrap.style.display=custom?"none":"block";}'
    . 'if(!type){resetItems(itemSelect,note,"Select a type first");return;}'
    . 'if(custom){showManual("Enter the provider type and item ID manually.");return;}'
    . 'fetch("relations.php?hub_ajax=items&type="+encodeURIComponent(type),{credentials:"same-origin"})'
    . '.then(function(response){if(!response.ok){throw new Error("HTTP "+response.status);}return response.json();})'
    . '.then(function(data){itemSelect.innerHTML="";'
    . 'if(!data||!data.supported){showManual(data&&data.message?data.message:"Collection unavailable; enter the item ID manually.");return;}'
    . 'if(itemWrap){itemWrap.style.display="block";}var first=document.createElement("option");first.value="";first.textContent=data.items&&data.items.length?"Select an item":"No selectable item";itemSelect.appendChild(first);'
    . 'if(data.items){for(var j=0;j<data.items.length;j++){var row=data.items[j],opt=document.createElement("option");opt.value=row.id;opt.textContent=(row.title||row.id)+" ["+row.id+"]";itemSelect.appendChild(opt);}}'
    . 'var manual=document.createElement("option");manual.value="__manual__";manual.textContent="Enter ID manually…";itemSelect.appendChild(manual);itemSelect.disabled=false;'
    . 'if(note){note.textContent=data.message||((data.items&&data.items.length)?data.items.length+" selectable item(s).":"");}'
    . '})'
    . '.catch(function(){showManual("Unable to load the provider collection; enter the item ID manually.");});'
    . '}'
    . 'itemSelect.addEventListener("change",function(){var manual=itemSelect.value==="__manual__";setManual(manualInput,manualWrap,manual);if(manualInput&&manual){manualInput.focus();}});'
    . 'typeSelect.addEventListener("change",loadItems);loadItems();'
    . '})(types[i]);}'
    . '})();</script>';

$display = COM_startBlock('Hub 0.3.0') . $content . COM_endBlock();
COM_output(COM_createHTMLDocument($display));
