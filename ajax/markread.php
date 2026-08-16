<?php
/**
 * Endpoint AJAX: registra fechamento/ciência de um aviso (seções 12 e 14).
 *
 * - Usuário vem SEMPRE da sessão (seção 12.5).
 * - O aviso precisa ser elegível para a sessão atual (seção 12.4): não se
 *   confia no id enviado pelo cliente.
 * - CSRF: validado pelo próprio GLPI em inc/includes.php para o POST AJAX
 *   (token no cabeçalho X-Glpi-Csrf-Token, enviado pelo portal.js) — seção 12.6.
 * - Falha aberta: erro devolve HTTP 200 e não quebra o portal.
 */

header('Content-Type: application/json; charset=UTF-8');

try {
    include('../../../inc/includes.php');

    if (!Session::getLoginUserID()) {
        echo json_encode(['ok' => false]);
        exit;
    }

    $alerts_id = (int) ($_POST['alerts_id'] ?? 0);
    $action    = (string) ($_POST['action'] ?? '');

    // Só grava se o aviso for de fato elegível para esta sessão.
    if ($alerts_id <= 0 || !PluginAvisosAlert::isEligibleForCurrentSession($alerts_id)) {
        echo json_encode(['ok' => false]);
        exit;
    }

    $ok = PluginAvisosRead::record($alerts_id, $action, Session::getLoginUserID());
    echo json_encode(['ok' => (bool) $ok]);
} catch (\Throwable $e) {
    if (function_exists('trigger_error')) {
        trigger_error('[avisos] markread: ' . $e->getMessage(), E_USER_WARNING);
    }
    http_response_code(200);
    echo json_encode(['ok' => false]);
}
