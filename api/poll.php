<?php
/**
 * api/poll.php — Real-time polling endpoint
 * IMPORTANT: session_write_close() called immediately after reading session
 * to prevent session locking from blocking concurrent requests
 */
session_start();
if (empty($_SESSION['user_id'])) {
    echo json_encode(['auth' => false]); exit;
}
$user_id  = intval($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? '';
session_write_close(); // ← release lock immediately, DB reads don't need session

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

require_once '../includes/db.php';

$last_notif_id  = intval($_GET['last_notif']   ?? 0);
$last_msg_id    = intval($_GET['last_msg']     ?? 0);
$last_service_id = intval($_GET['last_service'] ?? 0);
$last_user_id   = intval($_GET['last_user']    ?? 0);

// ── Unread notification count ──
$nc = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user=? AND lu=0");
$nc->execute([$user_id]);
$notif_count = (int)$nc->fetchColumn();

// ── New notifications since last poll ──
$nq = $pdo->prepare("
    SELECT id_notif, titre, contenu, lien, date_notif
    FROM notification
    WHERE id_user=? AND id_notif > ?
    ORDER BY id_notif ASC
");
$nq->execute([$user_id, $last_notif_id]);
$new_notifs = $nq->fetchAll(PDO::FETCH_ASSOC);

// ── Unread message count ──
$mc = $pdo->prepare("SELECT COUNT(*) FROM message WHERE id_destinataire=? AND lu=0");
$mc->execute([$user_id]);
$msg_count = (int)$mc->fetchColumn();

// ── New messages since last poll ──
$mq = $pdo->prepare("
    SELECT m.id_message, m.id_expediteur, m.contenu, m.data_envoi,
           u.nom, u.prenom
    FROM message m
    JOIN utilisateur u ON u.id_user=m.id_expediteur
    WHERE m.id_destinataire=? AND m.id_message > ?
    ORDER BY m.id_message ASC
");
$mq->execute([$user_id, $last_msg_id]);
$new_msgs = $mq->fetchAll(PDO::FETCH_ASSOC);

// ── Tech: new demandes ──
$demandes_count = 0;
$new_demandes   = [];
$pending_verif  = 0;
$new_users      = [];

if ($user_role === 'technicien') {
    $tq = $pdo->prepare("SELECT id_technicien FROM technicien WHERE id_user=?");
    $tq->execute([$user_id]);
    $tech = $tq->fetch();
    if ($tech) {
        $tid = $tech['id_technicien'];
        $dc = $pdo->prepare("SELECT COUNT(*) FROM service WHERE id_technicien=? AND statut_service='planifié'");
        $dc->execute([$tid]);
        $demandes_count = (int)$dc->fetchColumn();

        $dq = $pdo->prepare("
            SELECT s.id_service, s.date_service, s.description,
                   u.nom, u.prenom
            FROM service s
            JOIN client c ON c.id_client=s.id_client
            JOIN utilisateur u ON u.id_user=c.id_user
            WHERE s.id_technicien=? AND s.id_service > ? AND s.statut_service='planifié'
            ORDER BY s.id_service ASC
        ");
        $dq->execute([$tid, $last_service_id]);
        $new_demandes = $dq->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($user_role === 'admin') {
    $pv = $pdo->prepare("
        SELECT COUNT(*) FROM technicien t
        JOIN utilisateur u ON u.id_user=t.id_user
        WHERE t.verifie=0 AND u.statut_compte='actif'
    ");
    $pv->execute();
    $pending_verif = (int)$pv->fetchColumn();

    $uq = $pdo->prepare("
        SELECT id_user, nom, prenom, role, date_inscription
        FROM utilisateur
        WHERE id_user > ? AND role != 'admin'
        ORDER BY id_user ASC LIMIT 10
    ");
    $uq->execute([$last_user_id]);
    $new_users = $uq->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'auth'            => true,
    'notif_count'     => $notif_count,
    'new_notifs'      => $new_notifs,
    'msg_count'       => $msg_count,
    'new_msgs'        => $new_msgs,
    'demandes_count'  => $demandes_count,
    'new_demandes'    => $new_demandes,
    'pending_verif'   => $pending_verif,
    'new_users'       => $new_users,
    'ts'              => time(),
]);