<?php
// api/products/upload-image.php
// POST /api/products/{id}/image
// Headers : X-Token: <token>
// Body    : multipart/form-data  →  champ "image" (fichier)
// Réponse : { slot, path, img1, img2, img3, img4 }

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../config/database.php';

// ── 1. Authentification ──────────────────────────────────────────────────────
$commercant = requireAuth();
$id_commercant = (int) $commercant['id'];

// ── 2. ID produit depuis l'URL (/api/products/{id}/image) ───────────────────
$segments = $_REQUEST['_segments'] ?? [];
$id_produit = isset($segments[0]) ? (int) $segments[0] : 0;

if ($id_produit <= 0) {
    respond(400, 'ID produit invalide.');
}

// ── 3. Vérifier que le produit appartient à ce commerçant ───────────────────
$db = getDB();
$stmt = $db->prepare(
    "SELECT id, img1, img2, img3, img4 FROM produit
     WHERE id = ? AND id_commercant = ? LIMIT 1"
);
$stmt->execute([$id_produit, $id_commercant]);
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produit) {
    respond(404, 'Produit introuvable ou accès refusé.');
}

// ── 4. Trouver un slot libre (img1 → img4) ──────────────────────────────────
$slots = ['img1', 'img2', 'img3', 'img4'];
$slot_libre = null;

foreach ($slots as $slot) {
    if (empty($produit[$slot])) {
        $slot_libre = $slot;
        break;
    }
}

if ($slot_libre === null) {
    respond(400, 'Ce produit a déjà 4 images. Supprimez-en une avant d\'en ajouter.');
}

// ── 5. Validation du fichier uploadé ────────────────────────────────────────
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur).',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire).',
        UPLOAD_ERR_PARTIAL    => 'Upload incomplet, réessayez.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier reçu.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque.',
    ];
    $code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    respond(400, $codes[$code] ?? 'Erreur upload inconnue.');
}

$fichier    = $_FILES['image'];
$tmp_path   = $fichier['tmp_name'];
$taille     = $fichier['size'];
$type_mime  = mime_content_type($tmp_path);   // vérification réelle, pas l'extension

// Types autorisés
$mimes_ok = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($type_mime, $mimes_ok)) {
    respond(415, 'Format non supporté. Utilisez JPG, PNG, WEBP ou GIF.');
}

// Taille max : 5 Mo
$max_bytes = 5 * 1024 * 1024;
if ($taille > $max_bytes) {
    respond(413, 'Image trop lourde. Maximum 5 Mo.');
}

// ── 6. Construire le chemin de destination ───────────────────────────────────
// Pattern du projet : /uploads/{id_commercant}/{annee}/{mois}/{hash}.webp
$annee  = date('Y');
$mois   = date('m');
$dossier_relatif = "/uploads/{$id_commercant}/{$annee}/{$mois}";

// Chemin absolu sur le serveur (XAMPP : htdocs/ezycom-backend/uploads/...)
// __DIR__ est api/products/ → on remonte 3 niveaux jusqu'à la racine du backend
$racine_backend = dirname(__DIR__, 2);
$dossier_absolu = $racine_backend . $dossier_relatif;

if (!is_dir($dossier_absolu)) {
    if (!mkdir($dossier_absolu, 0755, true)) {
        respond(500, 'Impossible de créer le dossier d\'upload.');
    }
}

// Nom de fichier unique (même format que l'existant : hash hex 13 chars)
$nom_fichier = substr(uniqid('', true), 0, 13) . '.webp';
$chemin_absolu   = $dossier_absolu . '/' . $nom_fichier;
$chemin_relatif  = $dossier_relatif . '/' . $nom_fichier;  // ce qui sera en DB

// ── 7. Conversion en WebP et sauvegarde ─────────────────────────────────────
$image_resource = null;

switch ($type_mime) {
    case 'image/jpeg':
    case 'image/jpg':
        $image_resource = imagecreatefromjpeg($tmp_path);
        break;
    case 'image/png':
        $image_resource = imagecreatefrompng($tmp_path);
        break;
    case 'image/webp':
        $image_resource = imagecreatefromwebp($tmp_path);
        break;
    case 'image/gif':
        $image_resource = imagecreatefromgif($tmp_path);
        break;
}

if ($image_resource === false || $image_resource === null) {
    respond(500, 'Impossible de lire l\'image.');
}

// Redimensionner si > 1200px (garde les proportions)
$largeur_orig  = imagesx($image_resource);
$hauteur_orig  = imagesy($image_resource);
$largeur_max   = 1200;

if ($largeur_orig > $largeur_max) {
    $ratio         = $largeur_max / $largeur_orig;
    $nouvelle_l    = $largeur_max;
    $nouvelle_h    = (int) round($hauteur_orig * $ratio);
    $resized       = imagecreatetruecolor($nouvelle_l, $nouvelle_h);

    // Préserver transparence PNG/WebP
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefilledrectangle($resized, 0, 0, $nouvelle_l, $nouvelle_h, $transparent);

    imagecopyresampled($resized, $image_resource, 0, 0, 0, 0,
        $nouvelle_l, $nouvelle_h, $largeur_orig, $hauteur_orig);
    imagedestroy($image_resource);
    $image_resource = $resized;
}

// Sauvegarder en WebP (qualité 85)
$ok = imagewebp($image_resource, $chemin_absolu, 85);
imagedestroy($image_resource);

if (!$ok) {
    respond(500, 'Échec de la conversion en WebP.');
}

// ── 8. Mettre à jour la DB ───────────────────────────────────────────────────
$stmt = $db->prepare(
    "UPDATE produit SET {$slot_libre} = ? WHERE id = ? AND id_commercant = ?"
);
$stmt->execute([$chemin_relatif, $id_produit, $id_commercant]);

if ($stmt->rowCount() === 0) {
    // Nettoyer le fichier si la DB a échoué
    @unlink($chemin_absolu);
    respond(500, 'Erreur lors de la mise à jour de la base de données.');
}

// ── 9. Relire le produit mis à jour pour retourner tous les slots ────────────
$stmt2 = $db->prepare(
    "SELECT img1, img2, img3, img4 FROM produit WHERE id = ? LIMIT 1"
);
$stmt2->execute([$id_produit]);
$updated = $stmt2->fetch(PDO::FETCH_ASSOC);

respond(200, 'Image uploadée avec succès.', [
    'slot'       => $slot_libre,                    // ex: "img2"
    'path'       => $chemin_relatif,                // ex: "/uploads/1/2025/04/abc123.webp"
    'img1'       => $updated['img1'] ?? null,
    'img2'       => $updated['img2'] ?? null,
    'img3'       => $updated['img3'] ?? null,
    'img4'       => $updated['img4'] ?? null,
]);