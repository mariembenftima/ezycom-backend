<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$body  = getBody();
$email = trim($body['email'] ?? '');
$code  = trim($body['code']  ?? '');

if (empty($email) || empty($code)) {
    respond(422, 'Email et code requis.');
}

$db = getDB();

$stmt = $db->prepare("SELECT * FROM password_resets WHERE email = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email]);
$reset = $stmt->fetch();

if (!$reset || !password_verify($code, $reset['code'])) {
    respond(400, 'Code invalide ou expiré. Veuillez recommencer.');
}

respond(200, 'Code valide.');
