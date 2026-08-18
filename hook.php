<?php
/**
 * Rotinas de instalação/desinstalação do plugin de Avisos.
 *
 * Cria e remove as tabelas da seção 15 e registra os direitos da seção 13.
 */

/**
 * Instalação: cria tabelas e direitos padrão.
 *
 * @return boolean
 */
function plugin_avisos_install()
{
    /** @var DBmysql $DB */
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // ------------------------------------------------------------------
    // glpi_plugin_avisos_alerts — o aviso em si (seção 15)
    // ------------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_avisos_alerts')) {
        $query = "CREATE TABLE `glpi_plugin_avisos_alerts` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `content` longtext,
            `content_format` varchar(20) NOT NULL DEFAULT 'richtext'
                COMMENT 'richtext | html',
            `severity` varchar(20) NOT NULL DEFAULT 'info'
                COMMENT 'info | warning | critical',
            `behavior` varchar(20) NOT NULL DEFAULT 'informative'
                COMMENT 'informative | acknowledge | blocking',
            `keep_banner` tinyint NOT NULL DEFAULT '0',
            `date_start` timestamp NULL DEFAULT NULL,
            `date_end` timestamp NULL DEFAULT NULL,
            `date_republish` timestamp NULL DEFAULT NULL,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `priority` int NOT NULL DEFAULT '0',
            `users_id_creator` int {$default_key_sign} NOT NULL DEFAULT '0',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `is_active` (`is_active`),
            KEY `is_deleted` (`is_deleted`),
            KEY `date_start` (`date_start`),
            KEY `date_end` (`date_end`),
            KEY `severity` (`severity`),
            KEY `users_id_creator` (`users_id_creator`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ------------------------------------------------------------------
    // glpi_plugin_avisos_targets — público-alvo (Entity | Profile)
    // ------------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_avisos_targets')) {
        $query = "CREATE TABLE `glpi_plugin_avisos_targets` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_avisos_alerts_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `itemtype` varchar(100) NOT NULL DEFAULT ''
                COMMENT 'Entity | Profile',
            `items_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0'
                COMMENT 'incluir sub-entidades (aplicável a Entity)',
            PRIMARY KEY (`id`),
            KEY `alert_item` (`plugin_avisos_alerts_id`, `itemtype`, `items_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ------------------------------------------------------------------
    // glpi_plugin_avisos_reads — registro de fechamento/ciência (seção 14)
    // Sem índice único: a republicação gera segundo registro (seção 15).
    // ------------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_avisos_reads')) {
        $query = "CREATE TABLE `glpi_plugin_avisos_reads` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_avisos_alerts_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `users_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `action` varchar(20) NOT NULL DEFAULT 'close'
                COMMENT 'close | acknowledge',
            `date_action` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `alert_user_date` (`plugin_avisos_alerts_id`, `users_id`, `date_action`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQueryOrDie($query, $DB->error());
    }

    // ------------------------------------------------------------------
    // Migração datetime -> timestamp (GLPI 10 com fuso horário ativado).
    // Idempotente: só converte colunas que ainda estejam como datetime.
    // Roda em toda instalação/atualização; em base nova não faz nada.
    // ------------------------------------------------------------------
    $date_columns = [
        'glpi_plugin_avisos_alerts' => [
            'date_start', 'date_end', 'date_republish', 'date_creation', 'date_mod',
        ],
        'glpi_plugin_avisos_reads' => ['date_action'],
    ];
    foreach ($date_columns as $table => $columns) {
        if (!$DB->tableExists($table)) {
            continue;
        }
        $fields = $DB->listFields($table, false);
        foreach ($columns as $column) {
            if (
                isset($fields[$column])
                && stripos((string) $fields[$column]['Type'], 'timestamp') === false
            ) {
                $DB->doQueryOrDie(
                    "ALTER TABLE `$table` MODIFY `$column` timestamp NULL DEFAULT NULL",
                    $DB->error()
                );
            }
        }
    }

    // Direitos por perfil (seção 13).
    PluginAvisosProfile::initProfile();
    PluginAvisosProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id'] ?? 0);

    return true;
}

/**
 * Desinstalação: remove tabelas e direitos.
 *
 * @return boolean
 */
function plugin_avisos_uninstall()
{
    /** @var DBmysql $DB */
    global $DB;

    $tables = [
        'glpi_plugin_avisos_alerts',
        'glpi_plugin_avisos_targets',
        'glpi_plugin_avisos_reads',
    ];
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQueryOrDie(
                "DROP TABLE `$table`",
                $DB->error()
            );
        }
    }

    // Remove os direitos do plugin da tabela glpi_profilerights.
    foreach (PluginAvisosProfile::getAllRights() as $right) {
        ProfileRight::deleteProfileRights([$right['field']]);
    }

    return true;
}
