<?php
/**
 * api/messages.php
 * GET  ?action=fetch&with=UID&last_id=N  → new messages since last_id
 * POST action=send, id_destinataire, contenu → send message
 */
session_start();
if (empty($_SESSION['user_id'])) { echo json_encode(['auth'=>false]); exit; }
$user_id = intval($_SESSION['user_id']);
session_write_close(); // release session lock immediately

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

require_once '../includes/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'fetch') {
    $with    = intval($_GET['with']    ?? 0);
    $last_id = intval($_GET['last_id'] ?? 0);
    if (!$with) { echo json_encode(['auth'=>true,'msgs'=>[]]); exit; }

    // Mark received messages as read
    $pdo->prepare("UPDATE message SET lu=1 WHERE id_destinataire=? AND id_expediteur=?")
        ->execute([$user_id, $with]);

    $q = $pdo->prepare("
        SELECT m.id_message, m.id_expediteur, m.contenu, m.data_envoi,
               u.nom, u.prenom
        FROM message m
        JOIN utilisateur u ON u.id_user=m.id_expediteur
        WHERE ((m.id_expediteur=? AND m.id_destinataire=?)
            OR (m.id_expediteur=? AND m.id_destinataire=?))
          AND m.id_message > ?
        ORDER BY m.data_envoi ASC, m.id_message ASC
    ");
    $q->execute([$user_id,$with,$with,$user_id,$last_id]);
    echo json_encode(['auth'=>true, 'msgs'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'send') {
    $dest    = intval($_POST['id_destinataire'] ?? 0);
    $contenu = trim($_POST['contenu'] ?? '');
    if (!$dest || !$contenu) { echo json_encode(['ok'=>false,'err'=>'empty']); exit; }

    $pdo->prepare("INSERT INTO message (id_message,id_expediteur,id_destinataire,contenu,data_envoi,lu) SELECT COALESCE(MAX(id_message),0)+1, ?, ?, ?, NOW(), 0 FROM message")
        ->execute([$user_id, $dest, $contenu]);
    $msg_id = $pdo->lastInsertId();

    // Notify recipient
    require_once '../includes/helpers.php';
    $sq = $pdo->prepare("SELECT nom, prenom FROM utilisateur WHERE id_user=?");
    $sq->execute([$user_id]); $sender = $sq->fetch();
    $preview = mb_substr($contenu, 0, 60);
    notifier($pdo, $dest, 'Nouveau message', ($sender['prenom']??'').' : '.$preview, '');

    echo json_encode(['ok'=>true, 'id_message'=>$msg_id, 'data_envoi'=>date('Y-m-d H:i:s')]);
    exit;
}

echo json_encode(['ok'=>false,'err'=>'unknown action']);