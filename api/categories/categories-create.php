<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();
$body = getBody();
requireFields($body, ['nom']);

$db = getDB();

$db->beginTransaction();
try {
    $stmt = $db->prepare("
        INSERT INTO categorie (id_commercant, nom, etat, date_add)
        VALUES (?, ?, 1, NOW())
    ");
    $stmt->execute([$user['id'], trim($body['nom'])]);
    $parentId = $db->lastInsertId();

    $subs = $body['sous'] ?? [];
    foreach ($subs as $subNom) {
        $subNom = trim($subNom);
        if ($subNom === '') continue;
        $db->prepare("
            INSERT INTO categorie (id_commercant, parent_id, nom, etat, date_add)
            VALUES (?, ?, ?, 1, NOW())
        ")->execute([$user['id'], $parentId, $subNom]);
    }

    $db->commit();
    respond(201, 'Catégorie créée.', ['id' => $parentId]);
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur: ' . $e->getMessage());
}
