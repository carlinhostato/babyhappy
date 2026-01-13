<?php
/* sitter_ratings_api.php */

session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. CONFIGURAÇÃO DE CAMINHOS E CONEXÃO
// Ajustado para subir dois níveis: de /api/auth/ para a raiz e entrar em /backend/config/
$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: Ficheiro de configuração não encontrado.']);
    exit;
}

require_once $database_path;

try {
    // Tenta usar a função global do seu projeto ou cria uma nova instância mysqli
    $conn = function_exists('get_db_connection') ? get_db_connection() : new mysqli('localhost', 'root', '', 'babyhappy');
    
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// 2. VERIFICAÇÃO DE AUTENTICAÇÃO
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado.']);
    exit;
}

// 3. PROCESSAMENTO DE AÇÕES (POST)
$action = $_POST['action'] ?? '';

switch ($action) {
    
    case 'add_rating':
        $sitter_id = intval($_POST['sitter_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($sitter_id <= 0 || $rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Dados de avaliação inválidos.']);
            exit;
        }

        $sql = "INSERT INTO ratings (avaliador_id, recetor_id, rating, comentario, data_avaliacao) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $user_id, $sitter_id, $rating, $comentario);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Avaliação adicionada!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'edit_rating':
        $rating_id = intval($_POST['rating_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        // Segurança: Só o dono da avaliação pode editar
        $sql = "UPDATE ratings SET rating = ?, comentario = ? WHERE rating_id = ? AND avaliador_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isii", $rating, $comentario, $rating_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Avaliação atualizada!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro na atualização ou sem permissão.']);
        }
        $stmt->close();
        break;

    case 'delete_rating':
        $rating_id = intval($_POST['rating_id'] ?? 0);

        // Segurança: Só o dono da avaliação pode apagar
        $sql = "DELETE FROM ratings WHERE rating_id = ? AND avaliador_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $rating_id, $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Avaliação removida!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover ou sem permissão.']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação não especificada ou inválida.']);
}

$conn->close();