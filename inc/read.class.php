<?php
/**
 * Registro de fechamento/ciência de um aviso por um usuário.
 *
 * Corresponde a glpi_plugin_avisos_reads (seção 15). Cada interação do usuário
 * com o modal gera um registro (seção 14). A republicação (seção 10) gera um
 * segundo registro para o mesmo par usuário/aviso — por isso sem índice único.
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginAvisosRead extends CommonDBTM
{
    /** @var string Ver relatório de visualização (seção 13). */
    public static $rightname = 'plugin_avisos_report';

    const ACTION_CLOSE       = 'close';
    const ACTION_ACKNOWLEDGE = 'acknowledge';

    /**
     * Nome do tipo.
     *
     * @param integer $nb Quantidade.
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Visualização', 'Visualizações', $nb, 'avisos');
    }

    /**
     * Grava a interação do usuário da sessão com um aviso.
     *
     * O usuário vem SEMPRE da sessão, nunca de parâmetro do cliente
     * (seção 12.5). Chamado pelos endpoints AJAX no marco de telas.
     *
     * @param integer $alerts_id Id do aviso.
     * @param string  $action    self::ACTION_CLOSE | self::ACTION_ACKNOWLEDGE.
     * @param integer $users_id  Id do usuário da sessão.
     *
     * @return integer|false Id do registro criado ou false em erro.
     */
    public static function record($alerts_id, $action, $users_id)
    {
        $allowed = [self::ACTION_CLOSE, self::ACTION_ACKNOWLEDGE];
        if (!in_array($action, $allowed, true)) {
            return false;
        }

        $read = new self();
        return $read->add([
            'plugin_avisos_alerts_id' => (int) $alerts_id,
            'users_id'                => (int) $users_id,
            'action'                  => $action,
            'date_action'             => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }
}
