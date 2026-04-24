<?php
// api/products/list-variations.php
// GET /api/products/{id}/variations
// Retourne toutes les variantes d'un produit avec leurs attributs

require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

$segments   = $_REQUEST['_segments'] ?? [];
$id_produit = isset($segments[0]) ? (int) $segments[0] : 0;

if ($id_produit <= 0) respond(400, 'ID produit invalide.');

$db = getDB();

// Vérifier appartenance
$stmt = $db->prepare(
    "SELECT id FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1"
);
$stmt->execute([$id_produit, $id_commercant]);
if (!$stmt->fetch()) respond(404, 'Produit introuvable ou accès refusé.');

// Récupérer variations + attributs joints
$stmt = $db->prepare("
    SELECT pv.id, pv.sku, pv.prix, pv.prix_achat, pv.qte, pv.seuil,
           va.id_valeur_attribut,
           val.valeur,
           a.nom AS attribut_nom,
           a.id  AS attribut_id
    FROM produit_variation pv
    LEFT JOIN variation_attribut va  ON va.id_variation      = pv.id
    LEFT JOIN valeur_attribut val    ON val.id               = va.id_valeur_attribut
    LEFT JOIN attribut a             ON a.id                 = val.id_attribut
    WHERE pv.id_produit = ?
    ORDER BY pv.id, a.id
");
$stmt->execute([$id_produit]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par variation
$variations = [];
foreach ($rows as $row) {
    $vid = $row['id'];
    if (!isset($variations[$vid])) {
        $variations[$vid] = [
            'id'         => (int) $row['id'],
            'sku'        => $row['sku'],
            'prix'       => (float) $row['prix'],
            'prix_achat' => $row['prix_achat'] ? (float) $row['prix_achat'] : null,
            'qte'        => (int) $row['qte'],
            'seuil'      => (int) $row['seuil'],
            'attributs'  => [],
        ];
    }
    if ($row['id_valeur_attribut']) {
        $variations[$vid]['attributs'][] = [
            'attribut_id'   => (int) $row['attribut_id'],
            'attribut_nom'  => $row['attribut_nom'],
            'valeur_id'     => (int) $row['id_valeur_attribut'],
            'valeur'        => $row['valeur'],
        ];
    }
}

respond(200, 'OK', array_values($variations));