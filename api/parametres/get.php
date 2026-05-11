<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/helpers/response.php';
require_once dirname(__DIR__, 2) . '/middleware/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];
$db            = getDB();

$stmt = $db->prepare("
    SELECT c.id, c.nom, c.prenom, c.email, c.tel, c.boutique, c.adresse,
           c.ville AS ville_id, v.ville AS ville_nom,
           c.pack, p.nom AS pack_nom
    FROM commercant c
    LEFT JOIN ville v ON v.id = c.ville
    LEFT JOIN pack p  ON p.id = c.pack
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id_commercant]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) respond(404, 'Utilisateur introuvable.');

respond(200, 'OK', ['profil' => $user]);