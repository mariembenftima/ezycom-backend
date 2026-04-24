<?php
// api/products/create-variation.php
// POST /api/products/{id}/variation
// Body JSON : {
//   "prix": 150,
//   "prix_achat": 85,
//   "qte": 20,
//   "sku": "REF-S-NOIR",          (optionnel)
//   "valeurs": [5, 24]            (ids de valeur_attribut — ex: S + Noir)
// }

require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

// ID produit depuis l'URL
$segments   = $_REQUEST['_segments'] ?? [];
$id_produit = isset($segments[0]) ? (int) $segments[0] : 0;

if ($id_produit <= 0) respond(400, 'ID produit invalide.');

// Vérifier que le produit appartient à ce commerçant
$db   = getDB();
$stmt = $db->prepare(
    "SELECT id FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1"
);
$stmt->execute([$id_produit, $id_commercant]);
if (!$stmt->fetch()) respond(404, 'Produit introuvable ou accès refusé.');

// Body
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$prix      = isset($body['prix'])      ? (float) $body['prix']      : null;
$prixAchat = isset($body['prix_achat'])? (float) $body['prix_achat']: null;
$qte       = isset($body['qte'])       ? (int)   $body['qte']       : 0;
$sku       = trim($body['sku'] ?? '');
$valeurs   = $body['valeurs'] ?? [];   // tableau d'ids valeur_attribut

if ($prix === null) respond(400, 'Le prix est obligatoire.');
if (!is_array($valeurs) || count($valeurs) === 0) {
    respond(400, 'Au moins une valeur d\'attribut est requise (taille ou couleur).');
}

$db->beginTransaction();
try {
    // 1. Insérer la variation
    $stmt = $db->prepare(
        "INSERT INTO produit_variation (id_produit, sku, prix, prix_achat, qte, seuil)
         VALUES (?, ?, ?, ?, ?, 0)"
    );
    $stmt->execute([
        $id_produit,
        $sku ?: null,
        $prix,
        $prixAchat,
        $qte,
    ]);
    $id_variation = (int) $db->lastInsertId();

    // 2. Lier les valeurs d'attributs
    $stmtAttr = $db->prepare(
        "INSERT INTO variation_attribut (id_variation, id_valeur_attribut) VALUES (?, ?)"
    );
    foreach ($valeurs as $id_valeur) {
        $stmtAttr->execute([$id_variation, (int) $id_valeur]);
    }

    $db->commit();

    respond(201, 'Variante créée avec succès.', [
        'id'         => $id_variation,
        'id_produit' => $id_produit,
        'sku'        => $sku ?: null,
        'prix'       => $prix,
        'prix_achat' => $prixAchat,
        'qte'        => $qte,
        'valeurs'    => $valeurs,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur lors de la création de la variante : ' . $e->getMessage());
}