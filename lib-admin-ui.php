<?php

if (stripos($_SERVER['PHP_SELF'], basename(__FILE__)) !== false) {
    die('This file can not be used on its own.');
}

function HUB_adminNavigation($active)
{
    $items = array(
        'home' => array('index.php', 'Hub home'),
        'relations' => array('relations.php', 'Pillars & manual relations'),
        'editorial' => array('editorial.php', 'Editorial mapping'),
        'integrity' => array('integrity.php', 'Integrity & cluster health'),
        'audit' => array('audit.php', 'Plugin interoperability audit'),
        'link-audit' => array('link-audit.php', 'Article link audit'),
    );

    $out = '<nav class="hub-admin-nav" style="margin:0 0 18px">';
    $parts = array();
    foreach ($items as $key => $item) {
        $label = htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8');
        if ($key === $active) {
            $parts[] = '<strong>' . $label . '</strong>';
        } else {
            $parts[] = '<a href="' . htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        }
    }

    return $out . implode(' &nbsp; ', $parts) . '</nav>';
}
