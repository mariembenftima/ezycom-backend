<?php
// api/products/delete-variation.php
// DELETE /api/products/{id}/variation/{variation_id}
// Supprime une variante et ses attributs liés

require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

$segments     = $_REQUEST['_segments'] ?? [];
$id_produit   = isset($segments[0]) ? (int) $segments[0] : 0;
$id_variation = isset($segments[1]) ? (int) $segments[1] : 0;

if ($id_produit <= 0 || $id_variation <= 0) respond(400, 'IDs invalides.');

$db = getDB();

// Vérifier que la variation appartient à un produit de ce commerçant
$stmt = $db->prepare("
    SELECT pv.id FROM produit_variation pv
    JOIN produit p ON p.id = pv.id_produit
    WHERE pv.id = ? AND pv.id_produit = ? AND p.id_commercant = ?
    LIMIT 1
");
$stmt->execute([$id_variation, $id_produit, $id_commercant]);
if (!$stmt->fetch()) respond(404, 'Variante introuvable ou accès refusé.');

$db->beginTransaction();
try {
    // Supprimer les attributs liés d'abord (FK)
    $db->prepare("DELETE FROM variation_attribut WHERE id_variation = ?")
       ->execute([$id_variation]);

    // Supprimer la variation
    $db->prepare("DELETE FROM produit_variation WHERE id = ?")
       ->execute([$id_variation]);

    $db->commit();
    respond(200, 'Variante supprimée avec succès.');
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur lors de la suppression : ' . $e->getMessage());
}