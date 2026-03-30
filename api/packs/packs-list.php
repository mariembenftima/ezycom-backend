<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$db   = getDB();
$stmt = $db->query("SELECT id, nom, prix, nb_produit, nb_cmd, nb_user, nb_categories FROM pack ORDER BY id ASC");
respond(200, 'OK', $stmt->fetchAll());
