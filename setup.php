<?php
/**
 * Plugin de Avisos ao Usuário — GLPI 10.0.x
 *
 * Desenvolvido do zero. Não deriva de nenhum plugin existente.
 *
 * @license Proprietário — de propriedade da empresa.
 */

define('PLUGIN_AVISOS_VERSION', '1.0.0');

// Faixa de compatibilidade. Versão-alvo: GLPI 10.0.x (seção 5).
define('PLUGIN_AVISOS_MIN_GLPI', '10.0.0');
define('PLUGIN_AVISOS_MAX_GLPI', '11.0.0'); // exclusivo

/**
 * Inicialização do plugin. Chamado em toda página do GLPI.
 *
 * @return void
 */
function plugin_init_avisos()
{
    global $PLUGIN_HOOKS;

    // Todas as ações de gravação usam token CSRF do GLPI (seção 12.6).
    $PLUGIN_HOOKS['csrf_compliant']['avisos'] = true;

    // Aba de direitos por perfil (seção 13).
    Plugin::registerClass('PluginAvisosProfile', [
        'addtabon' => ['Profile'],
    ]);

    // Classe principal de aviso: habilita busca/lixeira padrão do GLPI.
    Plugin::registerClass('PluginAvisosAlert');

    // Só monta menu/hook de exibição se o plugin estiver instalado e ativo.
    if (Plugin::isPluginActive('avisos')) {
        // Menu de administração dos avisos.
        if (Session::haveRight('plugin_avisos_alert', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['avisos'] = [
                'admin' => 'PluginAvisosAlert',
            ];
        }

        // Injeção da camada de exibição SOMENTE no portal de autoatendimento
        // (interface helpdesk) — a interface do técnico não recebe modal
        // (seção 9). Fica isolada aqui de propósito para que a migração ao
        // GLPI 11 toque só nesta camada (seção 5).
        if (
            Session::getLoginUserID()
            && Session::getCurrentInterface() === 'helpdesk'
        ) {
            $PLUGIN_HOOKS['add_javascript']['avisos'][] = 'public/js/portal.js';
            $PLUGIN_HOOKS['add_css']['avisos'][]        = 'public/css/portal.css';
        }
    }
}

/**
 * Metadados do plugin exibidos na tela de plugins do GLPI.
 *
 * @return array
 */
function plugin_version_avisos()
{
    return [
        'name'           => __('Avisos ao usuário', 'avisos'),
        'version'        => PLUGIN_AVISOS_VERSION,
        'author'         => 'João Pedro Castor Quirino',
        'license'        => 'Proprietário',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_AVISOS_MIN_GLPI,
                'max' => PLUGIN_AVISOS_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Verificação de pré-requisitos antes da instalação.
 *
 * @return boolean
 */
function plugin_avisos_check_prerequisites()
{
    // A checagem de versão declarada em plugin_version_avisos() já é feita
    // pelo núcleo do GLPI 10.0.x; nada adicional é necessário aqui.
    return true;
}

/**
 * Verificação de configuração. Retorna true quando o plugin pode operar.
 *
 * @param boolean $verbose Exibe mensagens ao usuário.
 *
 * @return boolean
 */
function plugin_avisos_check_config($verbose = false)
{
    return true;
}
