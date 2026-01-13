<?php
// api/auth/get_profile_sitter.php

session_start();
header('Content-Type: application/json');

// Inclua aqui as suas funções send_error
function send_error($message, $http_status = 401) {
    http_response_code($http_status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO E ROLE
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "babysitter") {
    // Retorna 401 (Não Autorizado) para o JavaScript
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

$current_user_id = $_SESSION["user_id"];
$default_photo = 'public/assets/images/default_profile.png'; 

// 2. LÓGICA DE CARREGAMENTO DE DADOS
try {
    // Busca dados básicos do perfil e detalhes específicos do Babysitter
    $sql = "SELECT 
                u.user_id, u.nome_completo, u.email, u.role, u.photo_url, 
                b.bio, b.hourly_rate, b.experience_years 
            FROM users u
            LEFT JOIN babysitter_details b ON u.user_id = b.user_id 
            WHERE u.user_id = ?";
            
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) { send_error("Erro ao preparar consulta.", 500); }
    
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$user_data) { send_error("Dados de utilizador não encontrados.", 404); }
    
    // 3. RESPOSTA DE SUCESSO
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => [
            'user_id' => $user_data['user_id'],
            'nome_completo' => $user_data['nome_completo'],
            'email' => $user_data['email'],
            'role' => $user_data['role'],
            'photo_url' => $user_data['photo_url'] ?? $default_photo,
            // Detalhes específicos do Babysitter
            'bio' => $user_data['bio'],
            'hourly_rate' => $user_data['hourly_rate'],
            'experience_years' => $user_data['experience_years']
        ]
    ]);

} catch (Exception $e) {
    send_error("Erro interno do servidor: " . $e->getMessage(), 500);
}