<?php
/**
 * Direitos do plugin por perfil (seção 13).
 *
 * Direitos declarados:
 *   - plugin_avisos_alert  : CRUD dos avisos (READ/CREATE/UPDATE/DELETE/PURGE).
 *   - plugin_avisos_html   : editar aviso em HTML (P1 — bit separado, seção 13).
 *   - plugin_avisos_report : ver relatório de visualização (P1, seção 14).
 *   - plugin_avisos_bypass : não receber modal travante (P0 — salvaguarda,
 *                            seção 13/18).
 *
 * "Ver avisos" é implícito para todo usuário do portal e não é um direito.
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginAvisosProfile extends Profile
{
    /** @var string Só quem administra perfis edita estes direitos. */
    public static $rightname = 'profile';

    const RIGHT_ALERT  = 'plugin_avisos_alert';
    const RIGHT_HTML   = 'plugin_avisos_html';
    const RIGHT_REPORT = 'plugin_avisos_report';
    const RIGHT_BYPASS = 'plugin_avisos_bypass';

    /**
     * Todos os direitos do plugin.
     *
     * @return array<int,array{field:string,label:string,type:string}>
     */
    public static function getAllRights()
    {
        return [
            [
                'field' => self::RIGHT_ALERT,
                'label' => __('Avisos', 'avisos'),
                'type'  => 'crud',
            ],
            [
                'field' => self::RIGHT_HTML,
                'label' => __('Editar aviso em HTML', 'avisos'),
                'type'  => 'bool',
            ],
            [
                'field' => self::RIGHT_REPORT,
                'label' => __('Ver relatório de visualização', 'avisos'),
                'type'  => 'bool',
            ],
            [
                'field' => self::RIGHT_BYPASS,
                'label' => __('Bypass de aviso travante', 'avisos'),
                'type'  => 'bool',
            ],
        ];
    }

    /**
     * Registra os direitos do plugin em glpi_profilerights (todos zerados).
     *
     * @return void
     */
    public static function initProfile()
    {
        $fields = array_column(self::getAllRights(), 'field');
        ProfileRight::addProfileRights($fields);
    }

    /**
     * Concede acesso total ao perfil que instalou o plugin (primeiro acesso).
     *
     * @param integer $profiles_id Perfil a receber os direitos.
     *
     * @return void
     */
    public static function createFirstAccess($profiles_id)
    {
        if (!$profiles_id) {
            return;
        }
        ProfileRight::updateProfileRights($profiles_id, [
            self::RIGHT_ALERT  => ALLSTANDARDRIGHT | PURGE,
            self::RIGHT_HTML   => 1,
            self::RIGHT_REPORT => 1,
            self::RIGHT_BYPASS => 1,
        ]);
    }

    /**
     * Nome da aba exibida na ficha do perfil.
     *
     * @param CommonGLPI $item         Item pai (Profile).
     * @param integer    $withtemplate Contexto de template.
     *
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return PluginAvisosAlert::getTypeName(Session::getPluralNumber());
        }
        return '';
    }

    /**
     * Mostra o conteúdo da aba de direitos na ficha do perfil.
     *
     * @param CommonGLPI $item         Item pai (Profile).
     * @param integer    $tabnum       Número da aba.
     * @param integer    $withtemplate Contexto de template.
     *
     * @return boolean
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if ($item instanceof Profile) {
            $prof = new self();
            $prof->showForm($item->getField('id'));
        }
        return true;
    }

    /**
     * Formulário de edição dos direitos do plugin para um perfil.
     *
     * @param integer $profiles_id Id do perfil.
     * @param array   $options     Opções do formulário.
     *
     * @return boolean
     */
    public function showForm($profiles_id, $options = [])
    {
        echo "<div class='spaced'>";
        $canedit = self::canUpdate();

        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        if ($canedit) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        // Matriz CRUD do direito principal dos avisos.
        $matrix = [[
            'itemtype'  => 'PluginAvisosAlert',
            'label'     => PluginAvisosAlert::getTypeName(Session::getPluralNumber()),
            'field'     => self::RIGHT_ALERT,
        ]];
        $profile->displayRightsChoiceMatrix($matrix, [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('Avisos', 'avisos'),
        ]);

        // Direitos booleanos independentes (HTML, relatório, bypass).
        echo "<table class='tab_cadre_fixehov'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>"
            . __('Direitos adicionais', 'avisos') . "</th></tr>";

        foreach (self::getAllRights() as $right) {
            if ($right['type'] !== 'bool') {
                continue;
            }
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . $right['label'] . "</td>";
            echo "<td>";
            Html::showCheckbox([
                'name'    => "_{$right['field']}[1_1]",
                'checked' => (bool) ($profile->fields[$right['field']] ?? false),
                'readonly' => !$canedit,
            ]);
            echo "</td></tr>";
        }
        echo "</table>";

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
            Html::closeForm();
        }

        echo "</div>";
        return true;
    }
}
