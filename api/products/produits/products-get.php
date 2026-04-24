<?php
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
 
$commercant = requireAuth();
$db  = getDB();
$id  = intval($_REQUEST['_segments'][0] ?? 0);

if (!$id) respond(400, 'ID produit manquant.');

$stmt = $db->prepare("
    SELECT p.*,
           c.nom  AS categorie_nom,
           sc.nom AS sous_categorie_nom
    FROM produit p
    LEFT JOIN categorie c  ON c.id  = p.id_cat
    LEFT JOIN categorie sc ON sc.id = p.id_sous_cat
    WHERE p.id = ? AND p.id_commercant = ?
    LIMIT 1
");
$stmt->execute([$id, $commercant['id']]);
$produit = $stmt->fetch();

if (!$produit) respond(404, 'Produit introuvable.');

respond(200, 'OK', ['produit' => $produit]);