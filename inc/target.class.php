<?php
/**
 * Público-alvo de um aviso — vínculo com Entity ou Profile.
 *
 * Corresponde a glpi_plugin_avisos_targets (seção 15). Cada linha associa um
 * aviso a uma entidade (com opção "incluir sub-entidades") ou a um perfil.
 * Perfis vazios = todos os perfis das entidades escolhidas (seção 8/9).
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginAvisosTarget extends CommonDBTM
{
    /** @var string Segue o direito do aviso. */
    public static $rightname = 'plugin_avisos_alert';

    /**
     * Nome do tipo.
     *
     * @param integer $nb Quantidade.
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Público-alvo', 'Públicos-alvo', $nb, 'avisos');
    }

    /**
     * Retorna os alvos de um aviso agrupados por itemtype.
     *
     * @param integer $alerts_id Id do aviso.
     *
     * @return array{Entity: array<int,array>, Profile: array<int,array>}
     */
    public static function getForAlert($alerts_id)
    {
        $self    = new self();
        $rows    = $self->find(['plugin_avisos_alerts_id' => (int) $alerts_id]);
        $grouped = ['Entity' => [], 'Profile' => []];
        foreach ($rows as $row) {
            if (isset($grouped[$row['itemtype']])) {
                $grouped[$row['itemtype']][(int) $row['items_id']] = $row;
            }
        }
        return $grouped;
    }
}
