<?php
/**
 * Aviso — entidade principal do plugin.
 *
 * Corresponde a glpi_plugin_avisos_alerts (seção 15). Esta classe é o modelo
 * de dados; a camada de exibição (modal/faixa) fica separada de propósito
 * (seção 5) para isolar uma futura migração ao GLPI 11.
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginAvisosAlert extends CommonDBTM
{
    /** @var string Direito que controla o CRUD do aviso (seção 13). */
    public static $rightname = 'plugin_avisos_alert';

    /** @var boolean Registra histórico de alterações. */
    public $dohistory = true;

    /** @var boolean Usa a lixeira do GLPI (exclusão lógica — seção 19-Q5). */
    public $maybe_deleted = true;

    // --- Formato do conteúdo (seção 8 / 11) ---------------------------
    const FORMAT_RICHTEXT = 'richtext';
    const FORMAT_HTML     = 'html';

    // --- Severidade (seção 7.3) ---------------------------------------
    const SEVERITY_INFO     = 'info';
    const SEVERITY_WARNING  = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    // --- Comportamento (seção 7.1) ------------------------------------
    const BEHAVIOR_INFORMATIVE = 'informative';
    const BEHAVIOR_ACKNOWLEDGE = 'acknowledge';
    const BEHAVIOR_BLOCKING    = 'blocking';

    /**
     * Nome do tipo, no singular/plural.
     *
     * @param integer $nb Quantidade (para pluralização).
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Aviso', 'Avisos', $nb, 'avisos');
    }

    /**
     * Ícone do menu.
     *
     * @return string
     */
    public static function getIcon()
    {
        return 'ti ti-bell';
    }

    /**
     * Peso de ordenação por severidade (Crítico > Atenção > Informação),
     * usado na fila de exibição (seção 9).
     *
     * @return array<string,int>
     */
    public static function getSeverityOrder()
    {
        return [
            self::SEVERITY_CRITICAL => 3,
            self::SEVERITY_WARNING  => 2,
            self::SEVERITY_INFO     => 1,
        ];
    }

    /**
     * Rótulos traduzíveis das severidades.
     *
     * @return array<string,string>
     */
    public static function getSeverities()
    {
        return [
            self::SEVERITY_INFO     => __('Informação', 'avisos'),
            self::SEVERITY_WARNING  => __('Atenção', 'avisos'),
            self::SEVERITY_CRITICAL => __('Crítico', 'avisos'),
        ];
    }

    /**
     * Rótulos traduzíveis dos comportamentos.
     *
     * @return array<string,string>
     */
    public static function getBehaviors()
    {
        return [
            self::BEHAVIOR_INFORMATIVE => __('Informativo', 'avisos'),
            self::BEHAVIOR_ACKNOWLEDGE => __('Com ciência', 'avisos'),
            self::BEHAVIOR_BLOCKING    => __('Travante', 'avisos'),
        ];
    }

    /**
     * Rótulos traduzíveis dos formatos de conteúdo.
     *
     * @return array<string,string>
     */
    public static function getFormats()
    {
        return [
            self::FORMAT_RICHTEXT => __('Texto formatado', 'avisos'),
            self::FORMAT_HTML     => __('HTML', 'avisos'),
        ];
    }

    /**
     * Este perfil pode usar o formato HTML? (seção 13 / critério de aceite 12).
     *
     * @return boolean
     */
    public static function canUseHtmlFormat()
    {
        return (bool) Session::haveRight(PluginAvisosProfile::RIGHT_HTML, 1);
    }

    // ==================================================================
    //  Gravação: validação + sanitização (seção 12) + público-alvo
    // ==================================================================

    /**
     * @param array $input Dados do formulário.
     *
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        $input = $this->validateAndSanitize($input, true);
        if ($input === false) {
            return false;
        }
        $input['users_id_creator'] = Session::getLoginUserID();
        return $input;
    }

    /**
     * @param array $input Dados do formulário.
     *
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->validateAndSanitize($input, false);
    }

    /**
     * Regras comuns de validação e limpeza (seções 8 e 12).
     *
     * @param array   $input   Dados recebidos.
     * @param boolean $is_add  Verdadeiro na criação.
     *
     * @return array|false Input tratado, ou false com mensagem de erro.
     */
    private function validateAndSanitize($input, $is_add)
    {
        // --- Título obrigatório (seção 8) ---
        if ($is_add || isset($input['name'])) {
            $input['name'] = trim((string) ($input['name'] ?? ''));
            if ($input['name'] === '') {
                Session::addMessageAfterRedirect(
                    __('O título é obrigatório.', 'avisos'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        // --- Formato: HTML só com o direito específico (seção 13) ---
        if (isset($input['content_format'])) {
            if (
                $input['content_format'] === self::FORMAT_HTML
                && !self::canUseHtmlFormat()
            ) {
                $input['content_format'] = self::FORMAT_RICHTEXT;
            }
        }

        // --- Sanitização do conteúdo na gravação (seção 12.2) ---
        // O GLPI 10 entrega o POST já codificado (Sanitizer). É preciso
        // reverter para HTML real antes da allowlist e recodificar depois,
        // para manter o padrão de armazenamento do GLPI.
        if (isset($input['content'])) {
            $content = $input['content'];
            if (class_exists(\Glpi\Toolbox\Sanitizer::class)) {
                if (\Glpi\Toolbox\Sanitizer::isHtmlEncoded($content)) {
                    $content = \Glpi\Toolbox\Sanitizer::unsanitize($content);
                }
                $content = PluginAvisosSanitizer::getSafeHtml($content);
                $content = \Glpi\Toolbox\Sanitizer::sanitize($content);
            } else {
                $content = PluginAvisosSanitizer::getSafeHtml($content);
            }
            $input['content'] = $content;
        }

        // --- Período: fim > início (seção 8) ---
        $start = $input['date_start'] ?? ($this->fields['date_start'] ?? null);
        $end   = $input['date_end'] ?? ($this->fields['date_end'] ?? null);
        if (!empty($start) && !empty($end) && strtotime($end) <= strtotime($start)) {
            Session::addMessageAfterRedirect(
                __('O fim da exibição deve ser maior que o início.', 'avisos'),
                false,
                ERROR
            );
            return false;
        }

        // --- Faixa indisponível para comportamento travante (seção 7.2) ---
        $behavior = $input['behavior'] ?? ($this->fields['behavior'] ?? self::BEHAVIOR_INFORMATIVE);
        if ($behavior === self::BEHAVIOR_BLOCKING) {
            $input['keep_banner'] = 0;
        }

        // --- Pelo menos uma entidade no público-alvo (seção 8) ---
        // Só exige na criação ou quando o bloco de alvos vem no update.
        if ($is_add || isset($input['_target_entity'])) {
            $entities = array_filter((array) ($input['_target_entity'] ?? []), 'strlen');
            if (count($entities) === 0) {
                Session::addMessageAfterRedirect(
                    __('Selecione ao menos uma entidade no público-alvo.', 'avisos'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        return $input;
    }

    /**
     * Persiste o público-alvo após criar o aviso.
     *
     * @return void
     */
    public function post_addItem()
    {
        $this->saveTargets($this->fields['id'], $this->input);
    }

    /**
     * Repersiste o público-alvo após atualizar o aviso.
     *
     * @return void
     */
    public function post_updateItem($history = true)
    {
        // Só reescreve os alvos se o formulário trouxe o bloco de público.
        if (isset($this->input['_target_entity']) || isset($this->input['_target_profile'])) {
            $this->saveTargets($this->fields['id'], $this->input);
        }
    }

    /**
     * Remove alvos e leituras quando o aviso é excluído definitivamente.
     * Observação: os registros de leitura são preservados na exclusão lógica
     * (lixeira). Só a purga real remove tudo.
     *
     * @return void
     */
    public function cleanDBonPurge()
    {
        (new PluginAvisosTarget())->deleteByCriteria([
            'plugin_avisos_alerts_id' => $this->fields['id'],
        ]);
        // Leituras: mantidas por padrão (seção 19-Q5). A purga do aviso é ação
        // deliberada do administrador, então removemos o histórico associado.
        (new PluginAvisosRead())->deleteByCriteria([
            'plugin_avisos_alerts_id' => $this->fields['id'],
        ]);
    }

    /**
     * Reescreve as linhas de glpi_plugin_avisos_targets para um aviso.
     *
     * @param integer $alerts_id Id do aviso.
     * @param array   $input     Input do formulário.
     *
     * @return void
     */
    private function saveTargets($alerts_id, array $input)
    {
        $target = new PluginAvisosTarget();
        // Limpa os alvos atuais e regrava a partir do formulário.
        $target->deleteByCriteria(['plugin_avisos_alerts_id' => (int) $alerts_id]);

        // Entidades (com "incluir sub-entidades" por linha).
        $entities  = (array) ($input['_target_entity'] ?? []);
        $recursive = (array) ($input['_target_entity_recursive'] ?? []);
        $seen      = [];
        foreach ($entities as $idx => $entity_id) {
            if ($entity_id === '' || $entity_id === null) {
                continue;
            }
            $entity_id = (int) $entity_id;
            if (isset($seen[$entity_id])) {
                continue; // evita duplicata da mesma entidade
            }
            $seen[$entity_id] = true;
            $target->add([
                'plugin_avisos_alerts_id' => (int) $alerts_id,
                'itemtype'                => 'Entity',
                'items_id'                => $entity_id,
                'is_recursive'            => !empty($recursive[$idx]) ? 1 : 0,
            ]);
        }

        // Perfis (opcional; vazio = todos os perfis das entidades).
        $profiles = array_unique(array_filter(
            (array) ($input['_target_profile'] ?? []),
            'strlen'
        ));
        foreach ($profiles as $profile_id) {
            $target->add([
                'plugin_avisos_alerts_id' => (int) $alerts_id,
                'itemtype'                => 'Profile',
                'items_id'                => (int) $profile_id,
                'is_recursive'            => 0,
            ]);
        }
    }

    // ==================================================================
    //  Lista (motor de busca do GLPI)
    // ==================================================================

    /**
     * Opções de busca para a lista de avisos.
     *
     * @return array
     */
    public function rawSearchOptions()
    {
        $opts = [];

        $opts[] = ['id' => 'common', 'name' => self::getTypeName(2)];

        $opts[] = [
            'id'            => '1',
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Título', 'avisos'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $opts[] = [
            'id'            => '2',
            'table'         => self::getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'massiveaction' => false,
            'datatype'      => 'number',
        ];

        $opts[] = [
            'id'            => '3',
            'table'         => self::getTable(),
            'field'         => 'severity',
            'name'          => __('Severidade', 'avisos'),
            'datatype'      => 'specific',
            'searchtype'    => ['equals', 'notequals'],
        ];

        $opts[] = [
            'id'            => '4',
            'table'         => self::getTable(),
            'field'         => 'behavior',
            'name'          => __('Comportamento', 'avisos'),
            'datatype'      => 'specific',
            'searchtype'    => ['equals', 'notequals'],
        ];

        $opts[] = [
            'id'       => '5',
            'table'    => self::getTable(),
            'field'    => 'is_active',
            'name'     => __('Ativo', 'avisos'),
            'datatype' => 'bool',
        ];

        $opts[] = [
            'id'       => '6',
            'table'    => self::getTable(),
            'field'    => 'date_start',
            'name'     => __('Início da exibição', 'avisos'),
            'datatype' => 'datetime',
        ];

        $opts[] = [
            'id'       => '7',
            'table'    => self::getTable(),
            'field'    => 'date_end',
            'name'     => __('Fim da exibição', 'avisos'),
            'datatype' => 'datetime',
        ];

        $opts[] = [
            'id'       => '8',
            'table'    => self::getTable(),
            'field'    => 'priority',
            'name'     => __('Prioridade', 'avisos'),
            'datatype' => 'number',
        ];

        $opts[] = [
            'id'       => '16',
            'table'    => self::getTable(),
            'field'    => 'date_creation',
            'name'     => __('Data de criação'),
            'datatype' => 'datetime',
        ];

        return $opts;
    }

    /**
     * Renderiza valores específicos de colunas na lista (severity, behavior).
     *
     * @param string $field   Nome do campo.
     * @param mixed  $values  Valor(es).
     * @param array  $options Opções.
     *
     * @return string
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'severity':
                $labels = self::getSeverities();
                return $labels[$values[$field]] ?? $values[$field];
            case 'behavior':
                $labels = self::getBehaviors();
                return $labels[$values[$field]] ?? $values[$field];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Opções de busca (dropdowns) para severity e behavior.
     *
     * @param string $field   Nome do campo.
     * @param string $name    Nome do input.
     * @param mixed  $values  Valor.
     * @param array  $options Opções.
     *
     * @return string
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;
        switch ($field) {
            case 'severity':
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, self::getSeverities(), $options);
            case 'behavior':
                $options['value'] = $values[$field];
                return Dropdown::showFromArray($name, self::getBehaviors(), $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    // ==================================================================
    //  Formulário de cadastro (seção 8)
    // ==================================================================

    /**
     * Exibe o formulário de cadastro/edição do aviso.
     *
     * @param integer $ID      Id do aviso (0 = novo).
     * @param array   $options Opções.
     *
     * @return boolean
     */
    public function showForm($ID, array $options = [])
    {
        if (!self::canView()) {
            return false;
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $rand      = mt_rand();
        $behaviors = self::getBehaviors();
        $canhtml   = self::canUseHtmlFormat();

        // --- Título ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Título', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        echo Html::input('name', [
            'value'    => $this->fields['name'],
            'size'     => 60,
            'required' => 'required',
        ]);
        echo "</td>";

        // --- Ativo ---
        echo "<td>" . __('Ativo', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields['is_active']);
        echo "</td>";
        echo "</tr>";

        // --- Severidade / Comportamento ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Severidade', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        Dropdown::showFromArray('severity', self::getSeverities(), [
            'value' => $this->fields['severity'] ?: self::SEVERITY_INFO,
        ]);
        echo "</td>";

        echo "<td>" . __('Comportamento', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        Dropdown::showFromArray('behavior', $behaviors, [
            'value'     => $this->fields['behavior'] ?: self::BEHAVIOR_INFORMATIVE,
            'rand'      => $rand,
            'on_change' => "pluginAvisosToggleBanner(this.value, $rand);",
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Formato / Manter faixa ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Formato do conteúdo', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        $formats = self::getFormats();
        if (!$canhtml) {
            // Sem o direito de HTML, o seletor não oferece HTML (critério 12).
            unset($formats[self::FORMAT_HTML]);
        }
        Dropdown::showFromArray('content_format', $formats, [
            'value' => $this->fields['content_format'] ?: self::FORMAT_RICHTEXT,
        ]);
        echo "</td>";

        echo "<td>" . __('Manter faixa após fechar', 'avisos') . "</td>";
        echo "<td>";
        $is_blocking = ($this->fields['behavior'] === self::BEHAVIOR_BLOCKING);
        echo "<span id='avisos_keepbanner_$rand'>";
        Dropdown::showYesNo('keep_banner', $is_blocking ? 0 : $this->fields['keep_banner']);
        echo "</span>";
        echo "<span id='avisos_keepbanner_na_$rand' style='" . ($is_blocking ? '' : 'display:none') . "'>"
            . "<em class='text-muted'>" . __('Indisponível para Travante', 'avisos') . "</em></span>";
        echo "</td>";
        echo "</tr>";

        // --- Período ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Início da exibição', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        Html::showDateTimeField('date_start', [
            'value'    => $this->fields['date_start'],
            'required' => true,
        ]);
        echo "</td>";

        echo "<td>" . __('Fim da exibição', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td>";
        Html::showDateTimeField('date_end', [
            'value'    => $this->fields['date_end'],
            'required' => true,
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Prioridade / Republicação (campo do modelo; lógica é P1) ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Prioridade', 'avisos') . "</td>";
        echo "<td>";
        echo Html::input('priority', [
            'type'  => 'number',
            'value' => (int) $this->fields['priority'],
        ]);
        echo "</td>";

        echo "<td>" . __('Data de republicação', 'avisos') . "</td>";
        echo "<td>";
        Html::showDateTimeField('date_republish', [
            'value' => $this->fields['date_republish'],
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Conteúdo ---
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Conteúdo', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td colspan='3'>";
        Html::textarea([
            'name'              => 'content',
            'value'             => $this->fields['content'],
            'enable_richtext'   => true,
            'enable_images'     => true,
            'enable_fileupload' => false,
            'editor_id'         => "avisos_content_$rand",
            'cols'              => 100,
            'rows'              => 12,
        ]);
        echo "</td>";
        echo "</tr>";

        // --- Público-alvo: entidades ---
        $this->showTargetsSection($rand);

        // Botão de desativação imediata para avisos ativos já salvos
        // (salvaguarda principal do travante — seções 16 e 18).
        if ($this->fields['id'] && $this->fields['is_active'] && $this->canUpdateItem()) {
            $options['addbuttons'] = [
                'deactivate' => __('Desativar imediatamente', 'avisos'),
            ];
        }

        $this->showFormButtons($options);

        // JS de apoio: alterna disponibilidade da faixa e linhas de entidade.
        $this->renderFormScript($rand);

        return true;
    }

    /**
     * Seção de público-alvo do formulário (entidades + perfis).
     *
     * @param integer $rand Sufixo aleatório de ids.
     *
     * @return void
     */
    private function showTargetsSection($rand)
    {
        $targets  = $this->fields['id']
            ? PluginAvisosTarget::getForAlert($this->fields['id'])
            : ['Entity' => [], 'Profile' => []];

        echo "<tr class='tab_bg_2'><th colspan='4'>"
            . __('Público-alvo', 'avisos') . "</th></tr>";

        // Entidades (linhas dinâmicas).
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Entidades', 'avisos') . " <span class='red'>*</span></td>";
        echo "<td colspan='3'>";
        echo "<div id='avisos_entities_$rand'>";

        $rows = array_values($targets['Entity']);
        if (empty($rows)) {
            $this->renderEntityRow($rand, 0, null, false);
        } else {
            foreach ($rows as $i => $row) {
                $this->renderEntityRow($rand, $i, (int) $row['items_id'], !empty($row['is_recursive']));
            }
        }
        echo "</div>";
        echo "<a class='btn btn-sm btn-ghost-secondary' href='#' "
            . "onclick='pluginAvisosAddEntityRow($rand); return false;'>"
            . "<i class='ti ti-plus'></i> " . __('Adicionar entidade', 'avisos') . "</a>";
        echo "</td>";
        echo "</tr>";

        // Perfis (múltipla seleção; vazio = todos).
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Perfis', 'avisos') . "</td>";
        echo "<td colspan='3'>";
        Profile::dropdown([
            'name'     => '_target_profile[]',
            'multiple' => true,
            'values'   => array_keys($targets['Profile']),
            'width'    => '60%',
        ]);
        echo "<br><em class='text-muted'>"
            . __('Vazio = todos os perfis das entidades escolhidas.', 'avisos')
            . "</em>";
        echo "</td>";
        echo "</tr>";
    }

    /**
     * Renderiza uma linha de entidade (dropdown + "incluir sub-entidades").
     *
     * @param integer      $rand      Sufixo aleatório.
     * @param integer      $index     Índice da linha.
     * @param integer|null $entity_id Entidade selecionada.
     * @param boolean      $recursive Incluir sub-entidades.
     *
     * @return void
     */
    private function renderEntityRow($rand, $index, $entity_id, $recursive)
    {
        echo "<div class='avisos-entity-row' style='margin-bottom:4px'>";
        Entity::dropdown([
            'name'  => "_target_entity[$index]",
            'value' => $entity_id,
            'width' => '50%',
        ]);
        echo " <label style='margin-left:8px'>";
        echo Html::getCheckbox([
            'name'    => "_target_entity_recursive[$index]",
            'checked' => $recursive,
            'value'   => 1,
        ]);
        echo " " . __('Incluir sub-entidades', 'avisos') . "</label>";
        echo " <a href='#' class='text-danger' onclick='pluginAvisosRemoveEntityRow(this); return false;'>"
            . "<i class='ti ti-trash'></i></a>";
        echo "</div>";
    }

    /**
     * Injeta o JS de apoio do formulário (faixa + linhas de entidade).
     *
     * @param integer $rand Sufixo aleatório.
     *
     * @return void
     */
    private function renderFormScript($rand)
    {
        $blocking = self::BEHAVIOR_BLOCKING;
        echo Html::scriptBlock(<<<JS
function pluginAvisosToggleBanner(value, rand) {
    var field = document.getElementById('avisos_keepbanner_' + rand);
    var na    = document.getElementById('avisos_keepbanner_na_' + rand);
    if (!field || !na) { return; }
    if (value === '{$blocking}') {
        field.style.display = 'none';
        na.style.display = '';
    } else {
        field.style.display = '';
        na.style.display = 'none';
    }
}
var pluginAvisosEntityIdx = 1000;
function pluginAvisosAddEntityRow(rand) {
    var container = document.getElementById('avisos_entities_' + rand);
    if (!container) { return; }
    var first = container.querySelector('.avisos-entity-row');
    if (!first) { return; }
    var clone = first.cloneNode(true);
    var idx = pluginAvisosEntityIdx++;
    clone.querySelectorAll('[name]').forEach(function (el) {
        el.setAttribute('name', el.getAttribute('name').replace(/\\[[^\\]]*\\]/, '[' + idx + ']'));
        if (el.tagName === 'SELECT') { el.selectedIndex = 0; }
        if (el.type === 'checkbox') { el.checked = false; }
    });
    // Remove widgets do select2 clonados; deixa o <select> nativo.
    clone.querySelectorAll('.select2-container').forEach(function (n) { n.remove(); });
    container.appendChild(clone);
}
function pluginAvisosRemoveEntityRow(link) {
    var row = link.closest('.avisos-entity-row');
    var container = row ? row.parentNode : null;
    if (container && container.querySelectorAll('.avisos-entity-row').length > 1) {
        row.remove();
    }
}
JS
        );
    }
}
