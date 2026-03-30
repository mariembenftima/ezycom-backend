<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$body     = getBody();
$email    = trim($body['email']    ?? '');
$code     = trim($body['code']     ?? '');
$password = trim($body['password'] ?? '');

if (empty($email) || empty($code) || empty($password)) {
    respond(422, 'Tous les champs sont requis.');
}
if (strlen($password) < 8) {
    respond(422, 'Le mot de passe doit contenir au moins 8 caractères.');
}

$db = getDB();

$stmt = $db->prepare("
    SELECT * FROM password_resets
    WHERE email = ? AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$email]);
$reset = $stmt->fetch();

if (!$reset || !password_verify($code, $reset['code'])) {
    respond(400, 'Session expirée. Veuillez recommencer.');
}

$db->prepare("UPDATE commercant SET pwd = ? WHERE email = ?")
   ->execute([password_hash($password, PASSWORD_DEFAULT), $email]);

$db->prepare("UPDATE commercant SET token = NULL, token_expiration = NULL WHERE email = ?")
   ->execute([$email]);

$db->prepare("DELETE FROM password_resets WHERE email = ?")
   ->execute([$email]);

respond(200, 'Mot de passe réinitialisé avec succès.');
