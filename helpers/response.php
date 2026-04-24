<?php
function respond(int $status, string $message, array $data = []): void {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    $body = ['success' => $status >= 200 && $status < 300, 'message' => $message];
    if (!empty($data)) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function requireFields(array $body, array $fields): void {
    foreach ($fields as $field) {
        if (empty($body[$field])) respond(400, "Le champ '$field' est obligatoire.");
    }
}