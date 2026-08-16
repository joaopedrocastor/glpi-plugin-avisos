<?php
/**
 * Controlador do formulário de aviso: criar, atualizar, excluir, restaurar,
 * purgar e desativar imediatamente (seção 16 — desativação com efeito imediato).
 */

include('../../../inc/includes.php');

$alert = new PluginAvisosAlert();

if (isset($_POST['add'])) {
    $alert->check(-1, CREATE, $_POST);
    $newID = $alert->add($_POST);
    if ($newID && $_SESSION['glpibackcreated']) {
        Html::redirect($alert->getFormURLWithID($newID));
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $alert->check($_POST['id'], UPDATE);
    $alert->update($_POST);
    Html::back();
} elseif (isset($_POST['deactivate'])) {
    // Desativação imediata (salvaguarda do aviso travante — seções 16 e 18).
    $alert->check($_POST['id'], UPDATE);
    $alert->update(['id' => (int) $_POST['id'], 'is_active' => 0]);
    Session::addMessageAfterRedirect(__('Aviso desativado.', 'avisos'));
    Html::back();
} elseif (isset($_POST['purge'])) {
    $alert->check($_POST['id'], PURGE);
    $alert->delete($_POST, true);
    $alert->redirectToList();
} elseif (isset($_POST['delete'])) {
    // Exclusão lógica (lixeira) — preserva registros de leitura (seção 19-Q5).
    $alert->check($_POST['id'], DELETE);
    $alert->delete($_POST);
    $alert->redirectToList();
} elseif (isset($_POST['restore'])) {
    $alert->check($_POST['id'], DELETE);
    $alert->restore($_POST);
    $alert->redirectToList();
} else {
    $id = (int) ($_GET['id'] ?? 0);

    Html::header(
        PluginAvisosAlert::getTypeName(Session::getPluralNumber()),
        $_SERVER['PHP_SELF'],
        'admin',
        'PluginAvisosAlert'
    );

    $alert->display(['id' => $id]);

    Html::footer();
}
