<?php
/**
 * api/auth/fetch_babysitter_profile.php
 * Versão Corrigida: Retorna dados do perfil com path correto da foto
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Função auxiliar de erro
function send_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}

// Verifica autenticação
if (!isset($_SESSION["user_id"])) {
    send_error('Sessão expirada. Faça login novamente.', 401);
}

require_once __DIR__ . '/../../backend/config/database.php';

try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados", 500);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}

$user_id = $_SESSION["user_id"];

// Query para buscar dados de ambas as tabelas
$sql = "SELECT 
            u.user_id,
            u.nome_completo,
            u.email,
            u.phone,
            u.localizacao,
            u.disponibilidade,
            u.experiencia,
            u.photo_url,
            u.role,
            s.preco_hora,
            s.descricao
        FROM users u
        LEFT JOIN sitter_profiles s ON u.user_id = s.sitter_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    send_error("Erro ao preparar consulta: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_error('Utilizador não encontrado.', 404);
}

$user = $result->fetch_assoc();

// CORREÇÃO CRÍTICA: Normaliza o path da foto
$photo_url = $user['photo_url'];

if (!empty($photo_url)) {
    // Remove qualquer "../" do início
    $photo_url = preg_replace('/^\.\.\//', '', $photo_url);
    
    // Garante que o path começa com /babyhappy_v1/
    if (!preg_match('/^https?:\/\//', $photo_url)) {
        // Remove barra inicial se existir
        $photo_url = ltrim($photo_url, '/');
        
        // Adiciona /babyhappy_v1/ se não estiver presente
        if (!preg_match('/^babyhappy_v1\//', $photo_url)) {
            $photo_url = 'babyhappy_v1/' . $photo_url;
        }
        
        // Adiciona barra inicial
        $photo_url = '/' . $photo_url;
    }
} else {
    // Path padrão se não houver foto
    $photo_url = '/babyhappy_v1/frontend/assets/images/default-avatar.png';
}

// Prepara resposta com dados formatados
$response = [
    "success" => true,
    "user_id" => $user['user_id'],
    "nome_completo" => $user['nome_completo'] ?? '',
    "email" => $user['email'] ?? '',
    "phone" => $user['phone'] ?? '',
    "localizacao" => $user['localizacao'] ?? '',
    "disponibilidade" => $user['disponibilidade'] ?? 'Disponível',
    "experiencia" => $user['experiencia'] ?? 'Iniciante',
    "photo_url" => $photo_url,
    "role" => $user['role'] ?? 'babysitter',
    "preco_hora" => $user['preco_hora'] ?? 0,
    "descricao" => $user['descricao'] ?? ''
];

// Debug (remover em produção)
error_log("Photo URL original: " . ($user['photo_url'] ?? 'NULL'));
error_log("Photo URL corrigido: " . $photo_url);

echo json_encode($response, JSON_UNESCAPED_SLASHES);

$stmt->close();
$conn->close();
?>