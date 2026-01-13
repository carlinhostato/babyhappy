<?php
// api/auth/delete_conversation.php - Elimina todas as mensagens entre dois utilizadores (o logado e o parceiro).

session_start();
header('Content-Type: application/json; charset=utf-8');

// Função auxiliar para erros
function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// --------------------------------------------------------------
// INCLUIR DATABASE
// --------------------------------------------------------------

// Ajuste o caminho conforme a sua estrutura de pastas
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


// --------------------------------------------------------------
// AUTENTICAÇÃO E VALIDAÇÃO DE INPUT
// --------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Método não permitido.', 405);
}

// O ID do utilizador logado (quem solicita a exclusão)
$current_user_id = $_SESSION['user_id'] ?? null;

// O ID do parceiro de conversa
$partner_id = $_POST['partner_id'] ?? null;

if (!$current_user_id || !is_numeric($current_user_id)) {
    send_error("Não autenticado. Sessão inválida.", 401);
}

if (!is_numeric($partner_id)) {
    send_error('ID do parceiro de conversa inválido.');
}

$current_user_id = (int)$current_user_id;
$partner_id = (int)$partner_id;

// Prevenção: Não permitir que o utilizador tente eliminar a conversa consigo próprio
if ($current_user_id === $partner_id) {
    send_error('Não pode eliminar uma conversa consigo próprio.', 403);
}

// --------------------------------------------------------------
// ELIMINAÇÃO NA BASE DE DADOS
// --------------------------------------------------------------

// Esta query elimina as mensagens ONDE:
// 1. O remetente é o utilizador logado E o recetor é o parceiro (mensagens enviadas por mim)
// OU
// 2. O remetente é o parceiro E o recetor é o utilizador logado (mensagens recebidas por mim)

$sql_delete = "
    DELETE FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
";

try {
    $stmt = $conn->prepare($sql_delete);
    
    if (!$stmt) {
        send_error("Erro ao preparar consulta de eliminação: " . $conn->error, 500);
    }

    // Associar os 4 parâmetros: (current, partner), (partner, current)
    $stmt->bind_param("iiii", 
        $current_user_id, $partner_id, 
        $partner_id, $current_user_id
    );
    
    if ($stmt->execute()) {
        // O número de linhas afetadas pode ser 0 se já não houver mensagens
        $rows_deleted = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true,
            'message' => "Conversa eliminada com sucesso. ($rows_deleted mensagens removidas)",
            'rows_deleted' => $rows_deleted
        ]);

    } else {
        send_error("Erro ao executar eliminação da conversa: " . $stmt->error, 500);
    }

} catch (Exception $e) {
    send_error("Erro de servidor: " . $e->getMessage(), 500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>