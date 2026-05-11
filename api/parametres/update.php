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

$body = getBody();

$boutique = trim($body['boutique'] ?? '');
$nom      = trim($body['nom']      ?? '');
$prenom   = trim($body['prenom']   ?? '');
$tel      = trim($body['tel']      ?? '');
$adresse  = trim($body['adresse']  ?? '');
$ville    = !empty($body['ville'])  ? (int) $body['ville'] : null;

if (!$boutique || !$nom || !$prenom || !$tel) {
    respond(400, 'Les champs boutique, nom, prénom et téléphone sont obligatoires.');
}

$stmt = $db->prepare("
    UPDATE commercant
    SET boutique = ?, nom = ?, prenom = ?, tel = ?, adresse = ?, ville = ?
    WHERE id = ?
");
$stmt->execute([$boutique, $nom, $prenom, $tel, $adresse, $ville, $id_commercant]);

respond(200, 'Profil mis à jour avec succès.');