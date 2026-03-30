<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireAuth();

getDB()->prepare("UPDATE commercant SET token = NULL, token_expiration = NULL WHERE id = ?")
       ->execute([$user['id']]);

respond(200, 'Déconnexion réussie.');
