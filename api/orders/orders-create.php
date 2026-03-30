<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();
$body = getBody();
requireFields($body, ['nom', 'items']);

if (empty($body['items']) || !is_array($body['items'])) {
    respond(422, 'La commande doit contenir au moins un produit.');
}

$db      = getDB();
$nom     = trim($body['nom']);
$prenom  = trim($body['prenom'] ?? '');
$tel     = trim($body['tel']    ?? '');
$items   = $body['items'];

$total = array_reduce($items, fn($sum, $item) =>
    $sum + (floatval($item['prix'] ?? 0) * intval($item['qte'] ?? 1)), 0
);

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO commande (id_commercant, nom, prenom, tel, prix, etat, date_add)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$user['id'], $nom, $prenom, $tel, $total]);
    $cmdId = $db->lastInsertId();

    $stmtItem = $db->prepare("
        INSERT INTO detail_cmd (id_cmd, id_commercant, id_prod, qte, prix, etat)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    foreach ($items as $item) {
        $stmtItem->execute([
            $cmdId,
            $user['id'],
            $item['id_prod'] ?? null,
            intval($item['qte']  ?? 1),
            floatval($item['prix'] ?? 0),
        ]);
    }

    $db->commit();

    respond(201, 'Vente enregistrée.', ['id_cmd' => $cmdId, 'total' => $total]);
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur lors de la création: ' . $e->getMessage());
}
