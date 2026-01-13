<?php
// submit_rating.php - Localizado em /babyhappy_v1/api/auth/
session_start();
header('Content-Type: application/json');

// Incluir o ficheiro de conexão com a base de dados
// VERIFIQUE O CAMINHO, AJUSTEI PARA O PADRÃO QUE FUNCIONOU ANTES: ../../config/db_connect.php
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


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado.']);
    exit();
} 

// Função de resposta JSON
function sendResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// 1. VERIFICAÇÃO DE AUTENTICAÇÃO
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "parent") {
    sendResponse(false, 'Não autenticado. Faça login como Encarregado de Educação.');
}

$parent_id = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse(false, 'Método de acesso inválido.');
}

// 2. RECOLHA DE DADOS JSON
// Lê o corpo da requisição e decodifica o JSON
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

if ($data === null) {
    sendResponse(false, 'Dados JSON inválidos.');
}

// Mapear dados JSON para variáveis
$sitter_id = $data['sitter_id'] ?? null;
$rating_value = $data['rating_value'] ?? null;
$comentario = trim($data['comment'] ?? ''); // O JS envia 'comment'

// 3. VALIDAÇÃO ESSENCIAL
if (!$conn) {
    sendResponse(false, 'Erro de conexão com a base de dados.');
}

if (!filter_var($sitter_id, FILTER_VALIDATE_INT) || 
    !filter_var($rating_value, FILTER_VALIDATE_INT) || 
    $rating_value < 1 || $rating_value > 5) {
    
    sendResponse(false, "Dados de avaliação incompletos ou inválidos. (Falta Sitter ID ou Rating válido)");
}

// 4. INSERÇÃO NA BASE DE DADOS
// Use a tabela 'sitter_ratings' (como no JS) ou a sua 'ratings' (como no PHP original)
// Vou usar 'sitter_ratings' para manter a consistência com o que o 'get_sitter_details.php' usa.
// SE A SUA TABELA FOR 'ratings', MUDE ABAIXO.
$sql_insert = "INSERT INTO sitter_ratings (sitter_id, encarregado_id, rating_value, comment, data_avaliacao) 
               VALUES (?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql_insert);

if ($stmt === false) {
    sendResponse(false, "Erro interno do sistema (Falha na preparação da query): " . $conn->error);
}

// Os parâmetros são 4 (sitter_id, parent_id, rating_value, comment)
$stmt->bind_param("iiis", $sitter_id, $parent_id, $rating_value, $comentario);

$result = $stmt->execute();
$stmt->close();

if ($result) {
    // 5. ATUALIZAR O RATING MÉDIO DO SITTER
    // A sua lógica de atualização de rating está correta, mas deve referenciar a mesma tabela
    // Aqui assumo que a tabela é 'sitter_ratings' para consistência.
    $sql_update_avg = "
        UPDATE users u
        SET u.average_rating = (
            SELECT AVG(r.rating_value) 
            FROM sitter_ratings r 
            WHERE r.sitter_id = u.user_id
        ),
        u.total_ratings = (
            SELECT COUNT(r.rating_value)
            FROM sitter_ratings r
            WHERE r.sitter_id = u.user_id
        )
        WHERE u.user_id = ? AND u.role = 'babysitter'
    ";
    $stmt_avg = $conn->prepare($sql_update_avg);
    $stmt_avg->bind_param("i", $sitter_id);
    $stmt_avg->execute();
    $stmt_avg->close();
    
    sendResponse(true, "Avaliação submetida e média atualizada com sucesso!");
    
} else {
    sendResponse(false, "Falha ao inserir a avaliação na base de dados. Detalhes: " . $conn->error);
}
?>