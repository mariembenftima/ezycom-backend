<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();
$db   = getDB();

$stmt = $db->prepare("
    SELECT id, nom, etat, photo, parent_id
    FROM categorie
    WHERE id_commercant = ?
    ORDER BY parent_id IS NOT NULL, nom ASC
");
$stmt->execute([$user['id']]);
$all = $stmt->fetchAll();

$parents = [];
foreach ($all as $row) {
    if ($row['parent_id'] === null) {
        $parents[$row['id']] = [
            'id'    => $row['id'],
            'nom'   => $row['nom'],
            'etat'  => $row['etat'],
            'photo' => $row['photo'],
            'sous'  => [],
        ];
    }
}
foreach ($all as $row) {
    if ($row['parent_id'] !== null && isset($parents[$row['parent_id']])) {
        $parents[$row['parent_id']]['sous'][] = [
            'id'  => $row['id'],
            'nom' => $row['nom'],
        ];
    }
}

respond(200, 'OK', array_values($parents));