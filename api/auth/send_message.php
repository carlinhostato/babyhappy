<?php
// api/auth/send_message.php - Recebe dados do formulário de chat e insere a mensagem na base de dados.

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

// O ID do remetente (sender_id) é o utilizador logado. 
// O chat_client.js envia o sender_id no FormData, mas vamos priorizar a SESSÃO por segurança.
$sender_id = $_SESSION['user_id'] ?? null;

// Receber o receiver_id e o conteúdo do corpo da requisição POST (FormData do JS)
$receiver_id = $_POST['receiver_id'] ?? null;
$conteudo = $_POST['message'] ?? null;

if (!$sender_id || !is_numeric($sender_id)) {
    // Se a sessão falhar, tenta usar o ID enviado pelo formulário (menos seguro, mas útil como fallback)
    $sender_id = $_POST['sender_id'] ?? null;
    if (!$sender_id || !is_numeric($sender_id)) {
        send_error("Não autenticado. Sessão ou ID do remetente inválido.", 401);
    }
}

if (!is_numeric($receiver_id) || empty($conteudo)) {
    send_error('Recetor inválido ou mensagem vazia.');
}

// Conversão segura para inteiro e limpeza do conteúdo
$sender_id = (int)$sender_id;
$receiver_id = (int)$receiver_id;
$conteudo_limpo = trim($conteudo);


// --------------------------------------------------------------
// INSERÇÃO NA BASE DE DADOS
// --------------------------------------------------------------

$sql_insert = "INSERT INTO messages (sender_id, receiver_id, conteudo, timestamp, is_read) 
               VALUES (?, ?, ?, NOW(), 0)";

try {
    $stmt = $conn->prepare($sql_insert);
    
    if (!$stmt) {
        send_error("Erro ao preparar consulta: " . $conn->error, 500);
    }

    $stmt->bind_param("iis", $sender_id, $receiver_id, $conteudo_limpo);
    
    if ($stmt->execute()) {
        $last_id = $conn->insert_id;
        
        // Formatar o tempo de envio para feedback instantâneo no frontend
        $time_sent = date('H:i'); 
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensagem enviada com sucesso.',
            'message_id' => $last_id,
            'message_text' => htmlspecialchars($conteudo_limpo),
            'time' => $time_sent 
            // O checkmark '✓' ou '✓✓' será adicionado no JavaScript (chat_client.js)
        ]);

    } else {
        send_error("Erro ao executar inserção: " . $stmt->error, 500);
    }

} catch (Exception $e) {
    send_error("Erro de servidor: " . $e->getMessage(), 500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>