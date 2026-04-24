<?php
// api/orders/get.php
// GET /api/orders/{id}
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 2) . '/helpers/response.php';
require_once dirname(__DIR__, 2) . '/middleware/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];
$db            = getDB();

$id = (int) ($_REQUEST['_segments'][0] ?? 0);
if (!$id) respond(400, 'ID commande manquant.');

// Commande
$stmt = $db->prepare("
    SELECT * FROM commande
    WHERE id = ? AND id_commercant = ?
    LIMIT 1
");
$stmt->execute([$id, $id_commercant]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$commande) respond(404, 'Commande introuvable.');

// Articles de la commande avec infos produit
$stmt2 = $db->prepare("
    SELECT d.id, d.id_prod, d.id_variation, d.qte, d.prix, d.etat,
           p.nom AS produit_nom, p.ref AS produit_ref,
           p.img1 AS produit_img,
           pv.sku AS variation_sku
    FROM detail_cmd d
    LEFT JOIN produit p          ON p.id  = d.id_prod
    LEFT JOIN produit_variation pv ON pv.id = d.id_variation
    WHERE d.id_cmd = ?
    ORDER BY d.id
");
$stmt2->execute([$id]);
$articles = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Historique statuts
$stmt3 = $db->prepare("
    SELECT id, etat, date, motif
    FROM historique_commande
    WHERE id_cmd = ?
    ORDER BY date ASC
");
$stmt3->execute([$id]);
$historique = $stmt3->fetchAll(PDO::FETCH_ASSOC);

respond(200, 'OK', [
    'commande'   => $commande,
    'articles'   => $articles,
    'historique' => $historique,
]);