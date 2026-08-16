<?php
/**
 * Lista de avisos (motor de busca do GLPI).
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_avisos_alert', READ);

Html::header(
    PluginAvisosAlert::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'admin',
    'PluginAvisosAlert'
);

Search::show('PluginAvisosAlert');

Html::footer();
