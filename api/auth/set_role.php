<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$role = $data['role'] ?? null;
$valid_roles = ['parent', 'babysitter'];

if (!$role || !in_array($role, $valid_roles)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Papel invalido."]);
    exit;
}

$_SESSION['role'] = $role;

echo json_encode([
    "success" => true,
    "message" => "Papel definido com sucesso.",
    "role" => $role
]);
exit;