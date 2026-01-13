<?php
/* get_sitter_details.php */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function sendResponse($success, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

// Configuração do prefixo do projeto
$PROJECT_PREFIX = '/babyhappy_v1/';

// Incluir database
$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    sendResponse(false, 'database.php não encontrado', [], 500);
}

require_once $database_path;

// Obter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    sendResponse(false, 'ID do Babysitter em falta.', [], 400);
}

$sitterId = intval($_GET['id']);

// Conexão
try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) {
        sendResponse(false, 'Erro de conexão com a base de dados', [], 500);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    sendResponse(false, 'Erro: ' . $e->getMessage(), [], 500);
}

// -----------------------------------------------------
// 1. BUSCAR DADOS DO USUÁRIO + SITTER_PROFILE
// -----------------------------------------------------

$sql_sitter = "SELECT 
                u.user_id, u.nome_completo, u.email, u.photo_url,
                u.disponibilidade, u.proximidade, u.experiencia,
                u.localizacao, sp.preco_hora, sp.experiencia_anos,
                sp.media_rating, sp.descricao
             FROM users u
             LEFT JOIN sitter_profiles sp ON u.user_id = sp.sitter_id
             WHERE u.user_id = ? AND u.role = 'babysitter'";

$stmt = $conn->prepare($sql_sitter);
if (!$stmt) sendResponse(false, 'ERRO SQL: ' . $conn->error, [], 500);

$stmt->bind_param("i", $sitterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Babysitter não encontrado.', [], 404);
}

$sitter = $result->fetch_assoc();
$stmt->close();

// -----------------------------------------------------
// 2. BUSCAR ESTATÍSTICAS DE RATINGS
// -----------------------------------------------------

$sql_ratings_stats = "SELECT COUNT(*) as total, AVG(rating) as media FROM ratings WHERE recetor_id = ?";
$stmt_ratings = $conn->prepare($sql_ratings_stats);
$ratings_stats = ['total' => 0, 'media' => 0];

if ($stmt_ratings) {
    $stmt_ratings->bind_param("i", $sitterId);
    $stmt_ratings->execute();
    $result_ratings = $stmt_ratings->get_result();
    if ($row_stats = $result_ratings->fetch_assoc()) {
        $ratings_stats = $row_stats;
    }
    $stmt_ratings->close();
}

// -----------------------------------------------------
// 3. BUSCAR LISTA DE AVALIAÇÕES (REVIEWS)
// -----------------------------------------------------

$sql_reviews = "SELECT 
                    r.rating_id as id, r.rating AS rating_value, 
                    r.comentario AS comment, r.data_avaliacao, 
                    u.nome_completo AS avaliador_nome,
                    u.photo_url AS parent_photo, r.avaliador_id,
                    CASE WHEN r.avaliador_id = ? THEN 1 ELSE 0 END AS is_owner
                FROM ratings r
                JOIN users u ON r.avaliador_id = u.user_id 
                WHERE r.recetor_id = ?
                ORDER BY r.data_avaliacao DESC";

$stmt_reviews = $conn->prepare($sql_reviews);
$reviews = [];

if ($stmt_reviews) {
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $stmt_reviews->bind_param("ii", $current_user_id, $sitterId);
    $stmt_reviews->execute();
    $result_reviews = $stmt_reviews->get_result();
    
    while ($row = $result_reviews->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['rating_value'] = (int)$row['rating_value'];
        $row['avaliador_id'] = (int)$row['avaliador_id'];
        $row['is_owner'] = (bool)$row['is_owner'];
        
        // CORREÇÃO DO PATH DA FOTO DO AVALIADOR
        if (!empty($row['parent_photo'])) {
            if (strpos($row['parent_photo'], 'http') === false && strpos($row['parent_photo'], $PROJECT_PREFIX) === false) {
                $row['parent_photo'] = $PROJECT_PREFIX . ltrim($row['parent_photo'], '/');
            }
        } else {
            $row['parent_photo'] = $PROJECT_PREFIX . 'frontend/assets/images/default_profile.png';
        }
        
        $reviews[] = $row;
    }
    $stmt_reviews->close();
}

$conn->close();

// -----------------------------------------------------
// 4. MONTAR RESPOSTA FINAL (FOTO DO BABYSITTER)
// -----------------------------------------------------

$photo_url = $sitter['photo_url'];
if (!empty($photo_url)) {
    if (strpos($photo_url, 'http') === false && strpos($photo_url, $PROJECT_PREFIX) === false) {
        $photo_url = $PROJECT_PREFIX . ltrim($photo_url, '/');
    }
} else {
    $photo_url = $PROJECT_PREFIX . 'frontend/assets/images/default_profile.png';
}

$media_final = $ratings_stats['media'] > 0 ? $ratings_stats['media'] : ($sitter['media_rating'] ?? 0);

$response_data = [
    'user_id' => (int)$sitter['user_id'],
    'nome_completo' => $sitter['nome_completo'],
    'email' => $sitter['email'],
    'photo_url' => $photo_url,
    'localizacao' => $sitter['localizacao'] ?? 'N/D',
    'preco_hora' => (float)($sitter['preco_hora'] ?? 0),
    'experiencia' => $sitter['experiencia'] ?? 'N/D',
    'experiencia_anos' => (int)($sitter['experiencia_anos'] ?? 0),
    'disponibilidade' => $sitter['disponibilidade'] ?? 'N/D',
    'proximidade' => $sitter['proximidade'] ?? 'N/D',
    'descricao' => $sitter['descricao'] ?? '',
    'media_avaliacao' => round((float)$media_final, 1),
    'total_avaliacoes' => (int)($ratings_stats['total'] ?? 0)
];

sendResponse(true, 'Detalhes carregados com sucesso.', [
    'sitter' => $response_data,
    'reviews' => $reviews
], 200);