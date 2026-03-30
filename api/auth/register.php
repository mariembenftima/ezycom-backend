<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$body = getBody();
requireFields($body, ['boutique', 'nom', 'prenom', 'email', 'tel', 'password']);

$db = getDB();

$stmt = $db->prepare("SELECT id FROM commercant WHERE email = ? LIMIT 1");
$stmt->execute([trim($body['email'])]);
if ($stmt->fetch()) respond(409, 'Cet email est déjà utilisé.');

$hash    = password_hash($body['password'], PASSWORD_BCRYPT);
$packId  = intval($body['pack'] ?? 1);
$villeId = !empty($body['ville']) ? intval($body['ville']) : null;

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO commercant (boutique, nom, prenom, email, pwd, tel, ville, adresse, pack, etat, date_add)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([
        trim($body['boutique']),
        trim($body['nom']),
        trim($body['prenom']),
        trim($body['email']),
        $hash,
        trim($body['tel']),
        $villeId,
        trim($body['adresse'] ?? ''),
        $packId,
    ]);
    $id = $db->lastInsertId();

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $db->prepare("UPDATE commercant SET token = ?, token_expiration = ? WHERE id = ?")
       ->execute([$token, $expires, $id]);

    $packStmt = $db->prepare("SELECT nom FROM pack WHERE id = ? LIMIT 1");
    $packStmt->execute([$packId]);
    $pack = $packStmt->fetch();

    $db->commit();

    respond(201, 'Compte créé avec succès. En attente d\'activation.', [
        'token' => $token,
        'user'  => [
            'id'    => $id,
            'name'  => trim($body['prenom'] . ' ' . $body['nom']),
            'email' => trim($body['email']),
        ],
        'shop'  => [
            'name' => trim($body['boutique']),
            'plan' => strtolower($pack['nom'] ?? 'gratuit'),
        ],
    ]);
} catch (Exception $e) {
    $db->rollBack();
    respond(500, 'Erreur lors de la création: ' . $e->getMessage());
}
