<?php
/* ajax_get_ratings.php - VERSÃO CORRIGIDA */
session_start();

// Incluir configuração da base de dados
$path = dirname(__DIR__, 3) . '/config/database.php';
require_once $path;

// Obter conexão
$conn = get_db_connection();

// Validar parâmetro
$sitter_id = filter_input(INPUT_GET, 'sitter_id', FILTER_VALIDATE_INT);
if (!$sitter_id || $sitter_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID do babysitter inválido']);
    exit;
}

// Query com estrutura correta
$sql = "SELECT 
            r.rating,
            r.comentario,
            r.data_avaliacao,
            u.nome_completo AS remetente_nome
        FROM ratings r
        JOIN users u ON r.avaliador_id = u.user_id
        WHERE r.recetor_id = ?
        ORDER BY r.data_avaliacao DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erro na query: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $sitter_id);

if (!$stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erro na execução: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
$ratings = $result->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'ratings' => $ratings
]);

$stmt->close();
$conn->close();
?>