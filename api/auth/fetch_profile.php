<?php
// api/auth/fetch_profile.php - Retorna os dados do perfil em JSON para o frontend.

session_start();
header('Content-Type: application/json; charset=utf-8');

// Função auxiliar para erros
function send_error(string $message, int $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}

// --- 1. AUTENTICAÇÃO E CONEXÃO ---
if (!isset($_SESSION["user_id"])) {
    send_error('Não autenticado.', 401);
}

$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado", 500);
}

require_once $database_path;

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

// --- 2. BUSCA DE DADOS ---
// Ajustei a Query para incluir os dados da sitter_profiles também, caso existam
$sql = "SELECT 
            u.user_id, u.nome_completo, u.email, u.role, u.photo_url, 
            u.bio, u.phone, u.disponibilidade, u.proximidade, 
            u.experiencia, u.localizacao,
            sp.preco_hora, sp.descricao
        FROM users u
        LEFT JOIN sitter_profiles sp ON u.user_id = sp.sitter_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    send_error('Erro interno ao preparar a busca.', 500);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user_data) {
    send_error('Utilizador não encontrado.', 404);
}

// --- 3. TRATAMENTO DO CAMINHO DA FOTO ---
$project_prefix = '/babyhappy_v1/';

if (!empty($user_data['photo_url'])) {
    // Se a foto não for um link externo (http) e não tiver o prefixo do projeto
    if (strpos($user_data['photo_url'], 'http') === false && strpos($user_data['photo_url'], $project_prefix) === false) {
        // Limpa barras iniciais e adiciona o prefixo do projeto
        $clean_path = ltrim($user_data['photo_url'], '/');
        $user_data['photo_url'] = $project_prefix . $clean_path;
    }
} else {
    // Caminho padrão caso não haja foto
    $user_data['photo_url'] = $project_prefix . 'frontend/assets/images/default_profile.png';
}

echo json_encode(['success' => true, 'data' => $user_data]);