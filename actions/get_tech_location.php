<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit();
}

$spec  = trim($_GET['spec']??'');
$ville = trim($_GET['ville']??'');
$today_fr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'][date('l')] ?? 'Lundi';

$where = ["t.verifie=1","u.statut_compte='actif'","t.localisation_active=1","t.latitude IS NOT NULL"];
$params = [];
if ($spec)  { $where[]="t.specialité LIKE ?"; $params[]="%$spec%"; }
if ($ville) { $where[]="u.ville LIKE ?"; $params[]="%$ville%"; }

$sql = "SELECT t.id_technicien, t.specialité, t.tarif, t.latitude, t.longitude,
               u.nom, u.prenom, u.ville,
           COALESCE(AVG(a.note),0) as avg_note, COUNT(DISTINCT a.id_avis) as nb_avis,
           MAX(CASE WHEN d.id_disponibilite IS NOT NULL THEN 1 ELSE 0 END) as available_now
        FROM technicien t
        JOIN utilisateur u ON t.id_user=u.id_user
        LEFT JOIN avis a ON a.id_technicien=t.id_technicien
    LEFT JOIN disponibilite d ON d.id_technicien=t.id_technicien AND d.jour=? AND CURTIME() BETWEEN d.heure_debut AND d.heure_fin
        WHERE ".implode(' AND ',$where)."
        GROUP BY t.id_technicien";

$q = $pdo->prepare($sql);
$q->execute(array_merge([$today_fr], $params));
echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));