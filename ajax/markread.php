<?php
/**
 * Endpoint AJAX: registra fechamento/ciência de um aviso (seções 12 e 14).
 *
 * - Usuário vem SEMPRE da sessão (seção 12.5).
 * - O aviso precisa ser elegível para a sessão atual (seção 12.4).
 * - CSRF conforme padrão do GLPI (seção 12.6) — valida e responde JSON.
 * - Falha aberta: erro devolve HTTP 200 e não quebra o portal.
 *
 * NOTA: contém log de diagnóstico temporário em files/_log/avisos-debug.log.
 */

// Log direto em arquivo (não passa pelo filtro de erros do GLPI).
$avisos_dbg = dirname(__DIR__, 3) . '/files/_log/avisos-debug.log';
@file_put_contents($avisos_dbg, date('c') . " HIT\n", FILE_APPEND);

header('Content-Type: application/json; charset=UTF-8');

try {
    include('../../../inc/includes.php');

    if (!Session::getLoginUserID()) {
        @file_put_contents($avisos_dbg, date('c') . " nosession\n", FILE_APPEND);
        echo json_encode(['ok' => false, 'e' => 'nosession']);
        exit;
    }

    // CSRF já foi validado pelo próprio GLPI (inc/includes.php) para este
    // POST — se chegou até aqui, o token do cabeçalho X-Glpi-Csrf-Token
    // passou. Não é preciso revalidar (seção 12.6 atendida pelo padrão GLPI).

    $alerts_id = (int) ($_POST['alerts_id'] ?? 0);
    $action    = (string) ($_POST['action'] ?? '');

    $eligible = $alerts_id > 0
        && PluginAvisosAlert::isEligibleForCurrentSession($alerts_id);

    @file_put_contents($avisos_dbg, sprintf(
        "%s gate eligible=%d id=%d action=%s\n",
        date('c'),
        $eligible ? 1 : 0,
        $alerts_id,
        $action
    ), FILE_APPEND);

    if (!$eligible) {
        echo json_encode(['ok' => false, 'e' => 'eligible']);
        exit;
    }

    $ok = PluginAvisosRead::record($alerts_id, $action, Session::getLoginUserID());
    @file_put_contents($avisos_dbg, date('c') . ' record=' . var_export($ok, true) . "\n", FILE_APPEND);

    echo json_encode(['ok' => (bool) $ok]);
} catch (\Throwable $e) {
    @file_put_contents($avisos_dbg, date('c') . ' EXC ' . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(200);
    echo json_encode(['ok' => false, 'e' => 'exception']);
}
