<?php
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$commercant = requireAuth();
$db         = getDB();
$body       = getBody();

$id_produit = intval($body['id_produit'] ?? 0);
$qte        = intval($body['qte']        ?? 0);
$raison     = trim($body['raison']       ?? '');

if (!$id_produit) respond(400, 'Veuillez sélectionner un produit.');
if ($qte <= 0)    respond(400, 'La quantité doit être supérieure à 0.');
if (!$raison)     respond(400, 'Le motif de sortie est obligatoire.');

$stmt = $db->prepare("SELECT id, qte FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id_produit, $commercant['id']]);
$produit = $stmt->fetch();

if (!$produit) respond(404, 'Produit introuvable.');
if ($produit['qte'] < $qte) respond(400, 'Stock insuffisant. Quantité disponible : ' . $produit['qte'] . '.');

$db->prepare("UPDATE produit SET qte = qte - ? WHERE id = ?")
   ->execute([$qte, $id_produit]);

$db->prepare("
    INSERT INTO mouvement_stock (id_commercant, id_produit, date, motif, raison, qte)
    VALUES (?, ?, NOW(), '0', ?, ?)
")->execute([$commercant['id'], $id_produit, $raison, $qte]);

respond(200, 'Sortie de stock enregistrée avec succès.');