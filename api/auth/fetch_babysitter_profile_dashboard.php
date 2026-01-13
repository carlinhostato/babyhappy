<?php
// /babyhappy_v1/api/auth/fetch_babysitter_profile.php

header('Content-Type: application/json');
if (!isset($_SESSION)) {
    session_start();
}

/**
 * Envia uma resposta JSON e termina o script.
 * @param bool $success Indica sucesso ou falha.
 * @param string $message Mensagem para o usuário.
 * @param array $data Dados a serem retornados em caso de sucesso.
 * @param int $status_code Código de status HTTP.
 */
function send_response(bool $success, string $message, array $data = [], int $status_code = 200) {
    http_response_code($status_code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// 1. Verificação de Autenticação (Apenas a Babysitter logada pode ver/editar o seu próprio perfil)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'babysitter') {
    send_response(false, 'Acesso não autorizado ou sessão expirada.', [], 401);
}

$user_id = (int) $_SESSION['user_id'];

// ATENÇÃO: Verifique o caminho da database.php (Relativo a api/auth/)
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

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "babysitter") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.']);
    exit();
}



// 2. Consulta à Base de Dados
// Busca campos da tabela 'users' (dados pessoais) e os campos específicos de 'babysitter' (experiência, disponibilidade)
// NOTA: Assumo que 'experiencia' e 'disponibilidade' estão na tabela 'users' ou 'sitter_profiles'
$sql_sitter = "
    SELECT 
        u.nome_completo, u.email, u.localizacao, u.photo_url, u.experiencia, u.disponibilidade
    FROM users u
    WHERE u.user_id = ? AND u.role = 'babysitter'
";

$stmt = $conn->prepare($sql_sitter);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sitter_data = $result->fetch_assoc();
    $stmt->close();
} else {
    send_response(false, 'Erro ao preparar a query de busca de perfil.', [], 500);
}

$conn->close();

if (!$sitter_data) {
    send_response(false, 'Perfil não encontrado para o utilizador atual.', [], 404);
}

// 3. Sucesso: Retorna os dados esperados pelo JavaScript
send_response(true, 'Dados do perfil carregados com sucesso.', [
    // Corresponde exatamente aos campos usados no seu JavaScript:
    'nome_completo' => $sitter_data['nome_completo'],
    'email' => $sitter_data['email'],
    'localizacao' => $sitter_data['localizacao'],
    'photo_url' => $sitter_data['photo_url'],
    'experiencia' => $sitter_data['experiencia'], // Valor (Ex: 'Intermediária')
    'disponibilidade' => $sitter_data['disponibilidade'] // Valor (Ex: 'Disponível')
], 200);

?>