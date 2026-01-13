<?php
// ========================================
// CONFIGURAÇÃO INICIAL
// ========================================
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Lidar com preflight requests (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

function send_error($message, $http_status = 400) {
    http_response_code($http_status);
    echo json_encode([
        'success' => false, 
        'message' => $message
    ]);
    exit();
}

function send_success($data, $message = 'Login bem-sucedido') {
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// ========================================
// INCLUIR DATABASE
// ========================================

// Ajuste o caminho conforme a sua estrutura de pastas
// Se este ficheiro está em: backend/api/auth/login.php
// E database.php está em: config/database.php
// Então o caminho relativo é:
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

// ========================================
// VERIFICAR MÉTODO HTTP
// ========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido. Use POST.', 405);
}

// ========================================
// RECEBER E VALIDAR DADOS JSON
// ========================================
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('Dados JSON inválidos', 400);
}

$email = trim($data['email'] ?? '');
$senha_digitada = $data['password'] ?? '';

// Validação básica
if (empty($email) || empty($senha_digitada)) {
    send_error('Email e senha são obrigatórios');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_error('Email inválido');
}

// ========================================
// BUSCAR UTILIZADOR
// ========================================
try {
    $stmt = $conn->prepare("
        SELECT 
            user_id, 
            nome_completo, 
            email, 
            password_hash, 
            role 
        FROM users 
        WHERE email = ? 
        LIMIT 1
    ");
    
    if (!$stmt) {
        send_error("Erro ao preparar consulta: " . $conn->error, 500);
    }
    
    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        send_error("Erro ao executar consulta: " . $stmt->error, 500);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Mensagem genérica por segurança
        send_error("Email ou senha incorretos", 401);
    }
    
    $user = $result->fetch_assoc();
    
    // ========================================
    // VERIFICAR SENHA
    // ========================================
    if (!password_verify($senha_digitada, $user['password_hash'])) {
        send_error("Email ou senha incorretos", 401);
    }
    
    // ========================================
    // LOGIN BEM-SUCEDIDO
    // ========================================
    
    // Definir sessão no servidor
    $_SESSION["user_id"] = $user['user_id'];
    $_SESSION["role"] = $user['role'];
    $_SESSION["nome_completo"] = $user['nome_completo'];
    $_SESSION["email"] = $user['email'];
    
    // Retornar dados do utilizador (sem password_hash!)
    send_success([
        'user_id' => $user['user_id'],
        'nome_completo' => $user['nome_completo'],
        'email' => $user['email'],
        'role' => $user['role']
    ]);
    
} catch (Exception $e) {
    send_error("Erro de servidor: " . $e->getMessage(), 500);
} finally {
    // Fechar statement e conexão
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}