<?php
// api/auth/fetch_babysitter_ratings.php

session_start();
header('Content-Type: application/json; charset=utf-8');

// Função auxiliar para erros
function send_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// --------------------------------------------------------------
// INCLUIR DATABASE
// --------------------------------------------------------------

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

// --------------------------------------------------------------
// AUTENTICAÇÃO E ID DO UTILIZADOR
// --------------------------------------------------------------

// Usamos a Sessão para obter o ID do utilizador logado para segurança
$current_user_id = $_SESSION['user_id'] ?? null;

// Fallback: Se estiver a usar o ID no URL (menos seguro para APIs, mas útil se o JS do login falhar)
// $current_user_id = $current_user_id ?? ($_GET['user_id'] ?? null);

if (!$current_user_id || !is_numeric($current_user_id)) {
    send_error("Acesso negado. ID de utilizador inválido ou não autenticado.", 401);
}
$recetor_id = (int)$current_user_id;


// --------------------------------------------------------------
// LÓGICA DE DADOS (Função getReceivedRatings integrada)
// --------------------------------------------------------------

$sql_ratings = "
    SELECT r.rating,
           r.comentario,
           r.data_avaliacao,
           u.nome_completo AS remetente_nome
    FROM ratings r
    JOIN users u ON r.avaliador_id = u.user_id
    WHERE r.recetor_id = ?
    ORDER BY r.data_avaliacao DESC
";
        
$stmt = $conn->prepare($sql_ratings);
if ($stmt === false) {
    send_error("Erro na preparação da query de ratings: " . $conn->error, 500);
}

$stmt->bind_param("i", $recetor_id);
$stmt->execute();
$ratings_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calcular média das avaliações
$total_ratings = count($ratings_list);
$average_rating = 0;
if ($total_ratings > 0) {
    $total_sum = array_sum(array_column($ratings_list, 'rating'));
    $average_rating = round($total_sum / $total_ratings, 1);
}

// --------------------------------------------------------------
// RESPOSTA JSON
// --------------------------------------------------------------

echo json_encode([
    'success' => true,
    'data' => [
        'total_ratings' => $total_ratings,
        'average_rating' => $average_rating,
        'ratings_list' => $ratings_list
    ]
]);

$conn->close();
?>