<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$body = getBody();
requireFields($body, ['email', 'password']);

$db = getDB();

$stmt = $db->prepare("
    SELECT c.*, p.nom AS pack_nom
    FROM commercant c
    LEFT JOIN pack p ON p.id = c.pack
    WHERE c.email = ?
    LIMIT 1
");
$stmt->execute([$body['email']]);
$user = $stmt->fetch();

if (!$user || !password_verify($body['password'], $user['pwd'])) {
    respond(401, 'Email ou mot de passe incorrect.');
}

if ($user['etat'] != 1) {
    respond(403, 'Compte inactif. Contactez l\'administrateur.');
}

$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

$db->prepare("
    UPDATE commercant SET token = ?, token_expiration = ? WHERE id = ?
")->execute([$token, $expiresAt, $user['id']]);

respond(200, 'Connexion réussie.', [
    'token' => $token,
    'user'  => [
        'id'    => $user['id'],
        'name'  => trim($user['prenom'] . ' ' . $user['nom']),
        'email' => $user['email'],
    ],
    'shop'  => [
        'name' => $user['boutique'],
        'plan' => strtolower($user['pack_nom'] ?? 'gratuit'),
    ],
]);