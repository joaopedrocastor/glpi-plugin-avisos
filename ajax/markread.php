<?php
/**
 * Endpoint AJAX: registra fechamento/ciência de um aviso (seções 12 e 14).
 *
 * - Usuário vem SEMPRE da sessão (seção 12.5).
 * - O aviso precisa ser elegível para a sessão atual antes de gravar
 *   (seção 12.4): não se confia no id enviado pelo cliente.
 * - CSRF conforme padrão do GLPI (seção 12.6) — valida e responde JSON no
 *   erro (em vez de encerrar com página HTML, imprópria para AJAX).
 * - Falha aberta: erro devolve HTTP 200 e não quebra o portal.
 */

header('Content-Type: application/json; charset=UTF-8');

try {
    include('../../../inc/includes.php');

    if (!Session::getLoginUserID()) {
        echo json_encode(['ok' => false, 'e' => 'nosession']);
        exit;
    }

    $alerts_id = (int) ($_POST['alerts_id'] ?? 0);
    $action    = (string) ($_POST['action'] ?? '');

    $csrf_ok  = Session::validateCSRF($_POST);
    $eligible = $alerts_id > 0
        && PluginAvisosAlert::isEligibleForCurrentSession($alerts_id);

    // Log diagnóstico temporário: mostra o gate exato que barra a gravação.
    trigger_error(sprintf(
        '[avisos] markread: csrf=%d eligible=%d alerts_id=%d action=%s',
        $csrf_ok ? 1 : 0,
        $eligible ? 1 : 0,
        $alerts_id,
        $action
    ), E_USER_WARNING);

    if (!$csrf_ok) {
        echo json_encode(['ok' => false, 'e' => 'csrf']);
        exit;
    }
    if (!$eligible) {
        echo json_encode(['ok' => false, 'e' => 'eligible']);
        exit;
    }

    $ok = PluginAvisosRead::record($alerts_id, $action, Session::getLoginUserID());
    trigger_error('[avisos] markread record=' . var_export($ok, true), E_USER_WARNING);

    echo json_encode(['ok' => (bool) $ok]);
} catch (\Throwable $e) {
    trigger_error('[avisos] markread EXC: ' . $e->getMessage(), E_USER_WARNING);
    http_response_code(200);
    echo json_encode(['ok' => false, 'e' => 'exception']);
}
