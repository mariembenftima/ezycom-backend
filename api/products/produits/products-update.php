<?php
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';
 
$commercant = requireAuth();
$db  = getDB();
$id  = intval($_REQUEST['_segments'][0] ?? 0);
if (!$id) respond(400, 'ID produit manquant.');

$stmt = $db->prepare("SELECT * FROM produit WHERE id = ? AND id_commercant = ? LIMIT 1");
$stmt->execute([$id, $commercant['id']]);
$produit = $stmt->fetch();
if (!$produit) respond(404, 'Produit introuvable.');

$nom         = trim($_POST['nom']         ?? $produit['nom']);
$ref         = trim($_POST['ref']         ?? $produit['ref']);
$marque      = trim($_POST['marque']      ?? $produit['marque']);
$modele      = trim($_POST['modele']      ?? $produit['modele']);
$couleur     = trim($_POST['couleur']     ?? $produit['couleur']);
$description = trim($_POST['description'] ?? $produit['description']);
$prix        = trim($_POST['prix']        ?? $produit['prix']);
$prix_achat  = trim($_POST['prix_achat']  ?? $produit['prix_achat']);
$seuil       = intval($_POST['seuil']     ?? $produit['seuil']);
$id_cat      = intval($_POST['id_cat']    ?? $produit['id_cat']);
$id_sous_cat = intval($_POST['id_sous_cat'] ?? $produit['id_sous_cat']);

$ancienne_qte = intval($produit['qte']);
$nouvelle_qte = isset($_POST['qte']) ? intval($_POST['qte']) : $ancienne_qte;

if (!$nom)  respond(400, "Le champ 'nom' est obligatoire.");
if (!$prix) respond(400, "Le champ 'prix' est obligatoire.");

function saveImageUpdate(array $file, string $dir): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . $filename);
    $parts = explode('/uploads/', $dir);
    return '/uploads/' . ($parts[1] ?? '') . $filename;
}

$uploadDir = __DIR__ . '/../../../../uploads/' . $commercant['id'] . '/' . date('Y/m') . '/';

$img1 = (isset($_FILES['img1']) && $_FILES['img1']['error'] === UPLOAD_ERR_OK)
    ? saveImageUpdate($_FILES['img1'], $uploadDir) : $produit['img1'];
$img2 = (isset($_FILES['img2']) && $_FILES['img2']['error'] === UPLOAD_ERR_OK)
    ? saveImageUpdate($_FILES['img2'], $uploadDir) : $produit['img2'];
$img3 = (isset($_FILES['img3']) && $_FILES['img3']['error'] === UPLOAD_ERR_OK)
    ? saveImageUpdate($_FILES['img3'], $uploadDir) : $produit['img3'];
$img4 = (isset($_FILES['img4']) && $_FILES['img4']['error'] === UPLOAD_ERR_OK)
    ? saveImageUpdate($_FILES['img4'], $uploadDir) : $produit['img4'];

$db->prepare("
    UPDATE produit SET
        nom = ?, ref = ?, marque = ?, modele = ?, couleur = ?,
        description = ?, prix = ?, prix_achat = ?, qte = ?, seuil = ?,
        id_cat = ?, id_sous_cat = ?,
        img1 = ?, img2 = ?, img3 = ?, img4 = ?
    WHERE id = ? AND id_commercant = ?
")->execute([
    $nom, $ref, $marque, $modele, $couleur,
    $description, $prix, $prix_achat ?: null, $nouvelle_qte, $seuil,
    $id_cat ?: null, $id_sous_cat ?: null,
    $img1, $img2, $img3, $img4,
    $id, $commercant['id'],
]);

$diff = $nouvelle_qte - $ancienne_qte;
if ($diff !== 0) {
    $motif = $diff > 0 ? '1' : '0';
    $raison = $diff > 0 ? 'Correction de stock (entrée)' : 'Correction de stock (sortie)';
    $db->prepare("
        INSERT INTO mouvement_stock (id_commercant, id_produit, date, motif, raison, qte)
        VALUES (?, ?, NOW(), ?, ?, ?)
    ")->execute([$commercant['id'], $id, $motif, $raison, abs($diff)]);
}

respond(200, 'Produit mis à jour avec succès.');