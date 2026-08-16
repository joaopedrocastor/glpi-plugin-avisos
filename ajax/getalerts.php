<?php
/**
 * Endpoint AJAX: retorna os avisos pendentes do usuário logado (seção 9).
 *
 * A lista deriva SEMPRE da sessão — não aceita id de aviso do cliente
 * (seção 12.4). Falha aberta: qualquer erro devolve lista vazia com HTTP 200,
 * para nunca impedir o uso do portal (seção 16 / critério 14).
 */

// @phpcs desativado para o header: precisamos responder JSON sempre.
header('Content-Type: application/json; charset=UTF-8');

try {
    include('../../../inc/includes.php');

    // Sessão válida é obrigatória; sem login, nada a exibir.
    if (!Session::getLoginUserID()) {
        echo json_encode(['alerts' => []]);
        exit;
    }

    $alerts = PluginAvisosAlert::getPendingForCurrentSession();

    // Anexa um token CSRF por aviso, consumido no registro (markread).
    foreach ($alerts as &$a) {
        $a['csrf'] = Session::getNewCSRFToken();
    }
    unset($a);

    echo json_encode(['alerts' => array_values($alerts)]);
} catch (\Throwable $e) {
    // Falha aberta: registra e devolve vazio, sem quebrar o portal.
    if (function_exists('trigger_error')) {
        trigger_error('[avisos] getalerts: ' . $e->getMessage(), E_USER_WARNING);
    }
    http_response_code(200);
    echo json_encode(['alerts' => []]);
}
