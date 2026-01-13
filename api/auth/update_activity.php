<?php
// api/auth/update_activity.php

session_start();
header('Content-Type: application/json; charset=utf-8');

// Função de erro silenciosa para o heartbeat
function silent_exit($message, $code = 403) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// 1. Verificar Autenticação
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    silent_exit("Sessão ausente", 403);
}

// 2. Conexão
require_once __DIR__ . '/../../backend/config/database.php';
$conn = get_db_connection();

if (!$conn) {
    silent_exit("Erro de ligação à BD", 500);
}

// 3. Atualizar atividade (Coluna: ultima_atividade)
try {
    $stmt = $conn->prepare("UPDATE users SET ultima_atividade = NOW() WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'status' => 'online']);
} catch (Exception $e) {
    silent_exit($e->getMessage(), 500);
} finally {
    $conn->close();
}