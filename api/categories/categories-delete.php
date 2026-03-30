<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();
$id   = intval($_REQUEST['_segments'][0] ?? 0);

if (!$id) respond(400, 'ID manquant.');

$db = getDB();

$check = $db->prepare("SELECT id FROM categorie WHERE id = ? AND id_commercant = ? AND parent_id IS NULL LIMIT 1");
$check->execute([$id, $user['id']]);
if (!$check->fetch()) respond(404, 'Catégorie introuvable.');

$db->prepare("DELETE FROM categorie WHERE parent_id = ? AND id_commercant = ?")
   ->execute([$id, $user['id']]);
$db->prepare("DELETE FROM categorie WHERE id = ? AND id_commercant = ?")
   ->execute([$id, $user['id']]);

respond(200, 'Catégorie supprimée.');
