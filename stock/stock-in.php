<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$commercant = requireAuth();
$db         = getDB();
$body       = getBody();

$id_produit = intval($body['id_produit'] ?? 0);
$qte        = intval($body['qte']        ?? 0);
$raison     = trim($body['raison']       ?? 'Ajout de stock');

if (!$id_produit) respond(400, 'Veuillez sélectionner un produit.');
if ($qte <= 0)    respond(400, 'La quantité doit être supérieure à 0.');

// Vérifie que le produit appartient au commerçant
$stmt = $db->prepare("SELECT id FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id_produit, $commercant['id']]);
if (!$stmt->fetch()) respond(404, 'Produit introuvable.');

// Incrémente le stock
$db->prepare("UPDATE produit SET qte = qte + ? WHERE id = ?")
   ->execute([$qte, $id_produit]);

// Enregistre le mouvement — motif '1' = entrée
$db->prepare("
    INSERT INTO mouvement_stock (id_commercant, id_produit, date, motif, raison, qte)
    VALUES (?, ?, NOW(), '1', ?, ?)
")->execute([$commercant['id'], $id_produit, $raison, $qte]);

respond(200, 'Entrée de stock enregistrée avec succès.');