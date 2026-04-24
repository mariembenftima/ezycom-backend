<?php
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/helpers/response.php';

$commercant = requireAuth();
$db = getDB();

// Lire JSON ou form-data
$body        = json_decode(file_get_contents('php://input'), true) ?? [];

$nom         = trim($body['nom']          ?? $_POST['nom']          ?? '');
$ref         = trim($body['ref']          ?? $_POST['ref']          ?? '');
$marque      = trim($body['marque']       ?? $_POST['marque']       ?? '');
$modele      = trim($body['modele']       ?? $_POST['modele']       ?? '');
$couleur     = trim($body['couleur']      ?? $_POST['couleur']      ?? '');
$description = trim($body['description']  ?? $_POST['description']  ?? '');
$prix        = trim((string)($body['prix']       ?? $_POST['prix']       ?? ''));
$prix_achat  = trim((string)($body['prix_achat'] ?? $_POST['prix_achat'] ?? ''));
$qte         = intval($body['qte']        ?? $_POST['qte']          ?? 0);
$seuil       = intval($body['seuil']      ?? $_POST['seuil']        ?? 1);
$id_cat      = intval($body['id_cat']     ?? $_POST['id_cat']       ?? 0);
$id_sous_cat = intval($body['id_sous_cat'] ?? $_POST['id_sous_cat'] ?? 0);

if (!$nom)  respond(400, "Le champ 'nom' est obligatoire.");
if (!$prix) respond(400, "Le champ 'prix' est obligatoire.");

$art_id = bin2hex(random_bytes(16));

$uploadDir = __DIR__ . '/../../../../uploads/' . $commercant['id'] . '/' . date('Y/m') . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function saveImage(array $file, string $dir): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . strtolower($ext);
    move_uploaded_file($file['tmp_name'], $dir . $filename);
    return '/uploads/' . basename(dirname(dirname($dir))) . '/' . basename(dirname($dir)) . '/' . basename($dir) . $filename;
}

$img1 = isset($_FILES['img1']) ? saveImage($_FILES['img1'], $uploadDir) : null;
$img2 = isset($_FILES['img2']) ? saveImage($_FILES['img2'], $uploadDir) : null;
$img3 = isset($_FILES['img3']) ? saveImage($_FILES['img3'], $uploadDir) : null;
$img4 = isset($_FILES['img4']) ? saveImage($_FILES['img4'], $uploadDir) : null;

$stmt = $db->prepare("
    INSERT INTO produit
        (art_id, id_commercant, id_cat, id_sous_cat, nom, ref, marque, modele, couleur,
         description, prix, prix_achat, qte, seuil, etat, img1, img2, img3, img4, date_add)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())
");
$stmt->execute([
    $art_id, $commercant['id'], $id_cat ?: null, $id_sous_cat ?: null,
    $nom, $ref, $marque, $modele, $couleur,
    $description, $prix, $prix_achat ?: null, $qte, $seuil,
    $img1, $img2, $img3, $img4,
]);

$newId = $db->lastInsertId();

if ($qte > 0) {
    $db->prepare("
        INSERT INTO mouvement_stock (id_commercant, id_produit, date, motif, raison, qte)
        VALUES (?, ?, NOW(), '1', 'Ajout initial du produit', ?)
    ")->execute([$commercant['id'], $newId, $qte]);
}

respond(201, 'Produit créé avec succès.', ['id' => $newId]);