<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/config/database.php';

if (isset($_SESSION['nome_completo'])) {
    echo json_encode(["success" => true, "nome" => $_SESSION['nome_completo']]);
} elseif (isset($_SESSION['user_id'])) {
    // Se não estiver na sessão, busca na DB
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT nome_completo FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    echo json_encode(["success" => true, "nome" => $res['nome_completo'] ?? 'Utilizador(a)']);
} else {
    echo json_encode(["success" => false, "message" => "Não logado"]);
}