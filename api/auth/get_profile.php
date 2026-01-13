<?php
// api/auth/get_profile.php

session_start();
header('Content-Type: application/json');

// FUNÇÕES AUXILIARES (Pode incluir um ficheiro de utilidades que contenha send_error)
function send_error($message, $http_status = 400) {
    http_response_code($http_status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO E ROLE (Substitui a lógica inicial do PHP)
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "parent") {
    send_error("Não autorizado. Faça login novamente.", 401);
}

// AJUSTE O CAMINHO PARA O SEU FICHEIRO DE CONEXÃO
$database_path = __DIR__ . '/../../backend/config/database.php';

// Verificar se o ficheiro existe
if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado", 500);
}

require_once $database_path;

// Obter conexão
try {
    $conn = get_db_connection();
    
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados", 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}

// VARIÁVEIS DE CONTEXTO
$current_user_id = $_SESSION["user_id"];
$default_photo = 'public/assets/images/default_profile.png'; // Caminho relativo ao servidor

// 2. LÓGICA DE CARREGAMENTO DE DADOS
try {
    $sql = "SELECT user_id, nome_completo, email, role, photo_url FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        send_error("Erro ao preparar consulta.", 500);
    }
    
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$user_data) {
        // Isso não deve acontecer se a sessão for válida, mas é uma segurança.
        session_destroy(); 
        send_error("Dados de utilizador não encontrados.", 404);
    }
    
    // 3. RESPOSTA DE SUCESSO
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => [
            'nome_completo' => $user_data['nome_completo'],
            'email' => $user_data['email'],
            'role' => $user_data['role'],
            // Se photo_url for nulo ou vazio, usa o padrão
            'photo_url' => $user_data['photo_url'] ?? $default_photo 
        ]
    ]);

} catch (Exception $e) {
    send_error("Erro interno do servidor: " . $e->getMessage(), 500);
}

?>