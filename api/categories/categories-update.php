<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();
$body = getBody();
$id   = intval($_REQUEST['_segments'][0] ?? 0);

if (!$id) respond(400, 'ID manquant.');
requireFields($body, ['nom']);

$db = getDB();

$check = $db->prepare("SELECT id FROM categorie WHERE id = ? AND id_commercant = ? LIMIT 1");
$check->execute([$id, $user['id']]);
if (!$check->fetch()) respond(404, 'Catégorie introuvable.');

$db->beginTransaction();
try {
    $db->prepare("UPDATE categorie SET nom = ? WHERE id = ?")
       ->execute([trim($body['nom']), $id]);

    $newSubs = $body['sous_new'] ?? [];
    foreach ($newSubs as $subNom) {
        $subNom = trim($subNom);
        if ($subNom === '') continue;
        $db->prepare("
            INSERT INTO categorie (id_commercant, parent_id, nom, etat, date_add)
            VALUES (?, ?, ?, 1, NOW())
        ")->execute([$user['id'], $id, $subNom]);
    }

    $deleteSubs = $body['sous_delete'] ?? [];
    foreach ($deleteSubs as $subId) {
        $db->prepare("DELETE FROM categorie WHERE id = ? AND id_commercant = ? AND parent_id = ?")
           ->execute([intval($subId), $user['id'], $id]);
    }

    $db->commit();
    respond(200, 'Catégorie mise à jour.');
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur: ' . $e->getMessage());
}
