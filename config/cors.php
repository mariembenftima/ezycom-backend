<?php
$allowedOrigins = [
    'http://localhost:8081',
    'http://localhost:19006',
    'https://ezycom.tn',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: " . (in_array($origin, $allowedOrigins) ? $origin : '*'));
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
