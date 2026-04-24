<?php
require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

// ── 1. Authentification ──────────────────────────────────────────────────────
$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

// ── 2. ID produit ────────────────────────────────────────────────────────────
$segments   = $_REQUEST['_segments'] ?? [];
$id_produit = isset($segments[0]) ? (int) $segments[0] : 0;

if ($id_produit <= 0) {
    respond(400, 'ID produit invalide.');
}

// ── 3. Récupérer le slot depuis le body (JSON ou form-data) ─────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];
$slot = trim($body['slot'] ?? $_REQUEST['slot'] ?? '');

$slots_valides = ['img1', 'img2', 'img3', 'img4'];
if (!in_array($slot, $slots_valides)) {
    respond(400, 'Slot invalide. Valeurs acceptées : img1, img2, img3, img4.');
}

// ── 4. Vérifier que le produit appartient à ce commerçant ───────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, img1, img2, img3, img4 FROM produit
     WHERE id = ? AND id_commercant = ? LIMIT 1"
);
$stmt->execute([$id_produit, $id_commercant]);
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    respond(404, 'Produit introuvable ou accès refusé.');
}

$chemin_relatif = $produit[$slot] ?? '';

if (empty($chemin_relatif)) {
    respond(400, "Le slot {$slot} est déjà vide.");
}

// ── 5. Supprimer le fichier physique ────────────────────────────────────────
$racine_backend = dirname(__DIR__, 2);
$chemin_absolu  = $racine_backend . $chemin_relatif;

if (file_exists($chemin_absolu)) {
    @unlink($chemin_absolu);
    // Si le dossier est vide après suppression, on le nettoie
    $dossier = dirname($chemin_absolu);
    if (is_dir($dossier) && count(glob($dossier . '/*')) === 0) {
        @rmdir($dossier);
    }
}

// ── 6. Mettre à jour la DB : vider le slot ──────────────────────────────────
$stmt = $db->prepare(
    "UPDATE produit SET {$slot} = NULL WHERE id = ? AND id_commercant = ?"
);
$stmt->execute([$id_produit, $id_commercant]);

// ── 7. Relire et retourner l'état mis à jour ─────────────────────────────────
$stmt2 = $db->prepare(
    "SELECT img1, img2, img3, img4 FROM produit WHERE id = ? LIMIT 1"
);
$stmt2->execute([$id_produit]);
$updated = $stmt2->fetch(PDO::FETCH_ASSOC);

respond(200, "Image {$slot} supprimée avec succès.", [
    'img1' => $updated['img1'] ?? null,
    'img2' => $updated['img2'] ?? null,
    'img3' => $updated['img3'] ?? null,
    'img4' => $updated['img4'] ?? null,
]);