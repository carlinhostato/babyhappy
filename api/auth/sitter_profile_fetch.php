<?php
// /api/auth/sitter_profile_fetch.php
header('Content-Type: application/json');

// --- 1. VERIFICAÇÃO DE SESSÃO E SEGURANÇA ---
if (!isset($_SESSION)) {
    session_start();
}

// Verifica se a sessão está ativa (Acesso restrito)
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Por favor, faça login novamente.']);
    exit();
}

// Função auxiliar para retornar erro JSON
function send_error(string $message, int $status_code = 500) {
    http_response_code($status_code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// O ID da Babysitter deve ser passado como parâmetro GET
$sitter_id = $_GET['id'] ?? null;

if (!$sitter_id) {
    send_error("ID da Babysitter em falta.", 400);
}

// --- 2. CONEXÃO COM A BASE DE DADOS ---
$database_path = __DIR__ . '/../../../config/database.php';
if (!file_exists($database_path)) {
    send_error("Erro de configuração: Ficheiro de base de dados não encontrado.", 500);
}
require_once $database_path;

$conn = null;
try {
    // Assumimos que get_db_connection() devolve a ligação ou lança exceção
    $conn = get_db_connection(); 
} catch (Exception $e) {
    send_error("Erro de conexão: Não foi possível conectar à base de dados.", 500);
}

// --- 3. BUSCA DE DADOS ---
$sql_sitter = "
    SELECT 
        u.nome_completo, u.photo_url, u.localizacao, u.user_id,
        sp.preco_hora, sp.experiencia_anos, sp.media_rating, sp.descricao
    FROM users u
    -- LEFT JOIN permite que o perfil seja listado mesmo se a tabela sitter_profiles não tiver entrada
    LEFT JOIN sitter_profiles sp ON u.user_id = sp.sitter_id
    WHERE u.user_id = ? AND u.role = 'babysitter'
";

$sitter_data = null;
$stmt = $conn->prepare($sql_sitter);
if ($stmt) {
    $stmt->bind_param("i", $sitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sitter_data = $result->fetch_assoc();
    $stmt->close();
} else {
    $error_message = $conn->error;
    $conn->close();
    send_error("Erro ao preparar a consulta: " . $error_message, 500);
}

$conn->close();

if (!$sitter_data) {
    send_error("Perfil de Babysitter não encontrado.", 404);
}

// --- 4. FORMATAR E RETORNAR DADOS ---
// Define URL Base para imagens e default
$base_url = '/'; // Ajuste se a raiz não for '/'
// Use um fallback mais robusto para a foto
$default_photo = $base_url . 'public/assets/images/default_profile.png'; 

echo json_encode([
    'success' => true,
    'data' => [
        // CRÍTICO: Garantir que os tipos numéricos são tratados como números no JSON
        'sitter_id' => (int)$sitter_id,
        'nome_completo' => $sitter_data['nome_completo'] ?? 'N/D',
        'photo_url' => $sitter_data['photo_url'] ?? $default_photo,
        'localizacao' => $sitter_data['localizacao'] ?? 'Localização não especificada',
        'preco_hora' => (float)($sitter_data['preco_hora'] ?? 0.00),
        'experiencia_anos' => (int)($sitter_data['experiencia_anos'] ?? 0),
        'media_rating' => (float)($sitter_data['media_rating'] ?? 0.0),
        'descricao' => $sitter_data['descricao'] ?? 'Esta babysitter ainda não forneceu uma descrição detalhada.'
    ]
]);
?>