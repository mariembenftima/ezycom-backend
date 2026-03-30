<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$commercant = requireAuth();
$db = getDB();
$id = intval($_REQUEST['_segments'][0] ?? 0);
if (!$id) respond(400, 'ID produit manquant.');

$stmt = $db->prepare("SELECT id FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id, $commercant['id']]);
if (!$stmt->fetch()) respond(404, 'Produit introuvable.');

$db->prepare("DELETE FROM mouvement_stock WHERE id_produit = ? AND id_commercant = ?")
   ->execute([$id, $commercant['id']]);

$db->prepare("DELETE FROM produit WHERE id = ? AND id_commercant = ?")
   ->execute([$id, $commercant['id']]);

respond(200, 'Produit supprimé avec succès.');