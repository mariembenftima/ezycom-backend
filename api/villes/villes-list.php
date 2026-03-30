<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/response.php';

$db   = getDB();
$stmt = $db->query("SELECT id, ville, code FROM ville ORDER BY ville ASC");
respond(200, 'OK', $stmt->fetchAll());
