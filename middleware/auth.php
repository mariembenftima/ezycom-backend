<?php
require_once __DIR__ . '/../helpers/response.php';

function requireAuth(): array {
    $token = null;

    $allHeaders = [];
    if (function_exists('getallheaders')) {
        $allHeaders = getallheaders();
    }

    foreach ($allHeaders as $key => $value) {
        if (strtolower($key) === 'x-token') {
            $token = trim($value);
            break;
        }
    }

    if (!$token && isset($_SERVER['HTTP_X_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_TOKEN']);
    }

    if (!$token) respond(401, 'Token manquant ou invalide.');

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT c.id, c.nom, c.prenom, c.email, c.boutique, c.tel,
               c.pack, p.nom AS pack_nom
        FROM commercant c
        LEFT JOIN pack p ON p.id = c.pack
        WHERE c.token = ?
          AND c.token_expiration > NOW()
          AND c.etat = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) respond(401, 'Session expirée, veuillez vous reconnecter.');
    return $user;
}