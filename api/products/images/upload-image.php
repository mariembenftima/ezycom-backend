<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

$segments   = $_REQUEST['_segments'] ?? [];
$id_produit = isset($segments[0]) ? (int) $segments[0] : 0;
if ($id_produit <= 0) respond(400, 'ID produit invalide.');

$db   = getDB();
$stmt = $db->prepare("SELECT id, img1, img2, img3, img4 FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id_produit, $id_commercant]);
$produit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$produit) respond(404, 'Produit introuvable ou accès refusé.');

// Trouver slot libre
$slots      = ['img1', 'img2', 'img3', 'img4'];
$slot_libre = null;
foreach ($slots as $slot) {
    if (empty($produit[$slot])) { $slot_libre = $slot; break; }
}
if ($slot_libre === null) respond(400, 'Ce produit a déjà 4 images.');

// Vérifier fichier reçu
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    respond(400, 'Aucun fichier reçu ou erreur upload. Code: ' . ($_FILES['image']['error'] ?? 'N/A'));
}

$fichier   = $_FILES['image'];
$tmp_path  = $fichier['tmp_name'];
$taille    = $fichier['size'];

// Détecter le vrai type MIME
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$type_mime = finfo_file($finfo, $tmp_path);
finfo_close($finfo);

$mimes_ok  = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($type_mime, $mimes_ok)) {
    respond(415, 'Format non supporté: ' . $type_mime);
}
if ($taille > 5 * 1024 * 1024) respond(413, 'Image trop lourde. Maximum 5 Mo.');

// Extension selon MIME
$ext_map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext     = $ext_map[$type_mime] ?? 'jpg';

// Construire chemin
$annee           = date('Y');
$mois            = date('m');
$dossier_relatif = "/uploads/{$id_commercant}/{$annee}/{$mois}";
$racine_backend  = dirname(__DIR__, 3);
$dossier_absolu  = $racine_backend . $dossier_relatif;

if (!is_dir($dossier_absolu)) {
    mkdir($dossier_absolu, 0755, true);
}

$nom_fichier    = substr(uniqid('', true), 0, 13) . '.' . $ext;
$chemin_absolu  = $dossier_absolu . DIRECTORY_SEPARATOR . $nom_fichier;
$chemin_relatif = $dossier_relatif . '/' . $nom_fichier;

// Déplacer le fichier — pas de conversion, on garde le format original
if (!move_uploaded_file($tmp_path, $chemin_absolu)) {
    respond(500, 'Impossible de sauvegarder le fichier. Vérifiez les permissions du dossier uploads/');
}

// Mettre à jour la DB
$stmt = $db->prepare("UPDATE produit SET {$slot_libre} = ? WHERE id = ? AND id_commercant = ?");
$stmt->execute([$chemin_relatif, $id_produit, $id_commercant]);

if ($stmt->rowCount() === 0) {
    @unlink($chemin_absolu);
    respond(500, 'Erreur mise à jour base de données.');
}

$stmt2 = $db->prepare("SELECT img1, img2, img3, img4 FROM produit WHERE id = ? LIMIT 1");
$stmt2->execute([$id_produit]);
$updated = $stmt2->fetch(PDO::FETCH_ASSOC);

respond(200, 'Image uploadée avec succès.', [
    'slot' => $slot_libre,
    'path' => $chemin_relatif,
    'img1' => $updated['img1'] ?? null,
    'img2' => $updated['img2'] ?? null,
    'img3' => $updated['img3'] ?? null,
    'img4' => $updated['img4'] ?? null,
]);