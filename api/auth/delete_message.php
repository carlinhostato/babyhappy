<?php
// api/auth/delete_message.php - Elimina uma mensagem da base de dados se o utilizador logado for o remetente.

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

// O ID do utilizador logado deve vir da SESSÃO para segurança
$current_user_id = $_SESSION['user_id'] ?? null;

// Receber o message_id do corpo da requisição POST (FormData do JS)
$message_id = $_POST['message_id'] ?? null;

if (!$current_user_id || !is_numeric($current_user_id)) {
    send_error("Não autenticado. Sessão inválida.", 401);
}

if (!is_numeric($message_id)) {
    send_error('ID da mensagem inválido.');
}

$current_user_id = (int)$current_user_id;
$message_id = (int)$message_id;

// --------------------------------------------------------------
// ELIMINAÇÃO NA BASE DE DADOS (COM VERIFICAÇÃO DE PROPRIEDADE)
// --------------------------------------------------------------

// Query para eliminar a mensagem, mas SÓ SE o sender_id for o utilizador logado.
$sql_delete = "DELETE FROM messages WHERE message_id = ? AND sender_id = ?";

try {
    $stmt = $conn->prepare($sql_delete);
    
    if (!$stmt) {
        send_error("Erro ao preparar consulta de eliminação: " . $conn->error, 500);
    }

    $stmt->bind_param("ii", $message_id, $current_user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Mensagem eliminada com sucesso.'
            ]);
        } else {
            // Isto pode acontecer se a mensagem não existir OU se o utilizador não for o remetente (sender_id)
            send_error("Não foi possível eliminar a mensagem. Verifique se a mensagem existe e se é sua.", 403);
        }
    } else {
        send_error("Erro ao executar eliminação: " . $stmt->error, 500);
    }

} catch (Exception $e) {
    send_error("Erro de servidor: " . $e->getMessage(), 500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>