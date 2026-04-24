<?php
// api/products/list-attributs.php
// GET /api/products/attributs
// Retourne tous les attributs + leurs valeurs pour ce commerçant
// Ex: [ { id:4, nom:"Taille", valeurs:[{id:4,valeur:"XS"}, ...] }, ... ]

require_once dirname(__DIR__, 3) . '/helpers/response.php';
require_once dirname(__DIR__, 3) . '/middleware/auth.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
$commercant    = requireAuth();
$id_commercant = (int) $commercant['id'];

$db = getDB();

// Récupérer attributs de ce commerçant
$stmt = $db->prepare(
    "SELECT a.id, a.nom,
            va.id   AS val_id,
            va.valeur
     FROM attribut a
     LEFT JOIN valeur_attribut va ON va.id_attribut = a.id
     WHERE a.id_commercant = ?
     ORDER BY a.id, va.id"
);
$stmt->execute([$id_commercant]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par attribut
$attributs = [];
foreach ($rows as $row) {
    $aid = $row['id'];
    if (!isset($attributs[$aid])) {
        $attributs[$aid] = [
            'id'      => (int) $row['id'],
            'nom'     => $row['nom'],
            'valeurs' => [],
        ];
    }
    if ($row['val_id']) {
        $attributs[$aid]['valeurs'][] = [
            'id'     => (int) $row['val_id'],
            'valeur' => $row['valeur'],
        ];
    }
}

respond(200, 'OK', array_values($attributs));