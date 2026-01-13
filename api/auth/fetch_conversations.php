<?php
// api/AUTH/fetch_conversations.php - Retorna a lista de conversas em JSON

session_start();
header('Content-Type: application/json');

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

$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']); exit;
}

$sql_conversations = "
    SELECT DISTINCT
        u.user_id AS partner_id,
        u.nome_completo AS partner_name,
        u.photo_url AS partner_photo,
        (SELECT conteudo FROM messages
         WHERE (sender_id=? AND receiver_id=u.user_id) OR (sender_id=u.user_id AND receiver_id=?)
         ORDER BY timestamp DESC LIMIT 1) AS last_message,
        (SELECT timestamp FROM messages
         WHERE (sender_id=? AND receiver_id=u.user_id) OR (sender_id=u.user_id AND receiver_id=?)
         ORDER BY timestamp DESC LIMIT 1) AS last_timestamp,
        (SELECT COUNT(message_id) FROM messages
         WHERE receiver_id=? AND sender_id=u.user_id AND is_read=0) AS unread_count
    FROM users u
    WHERE u.user_id IN (
        SELECT receiver_id FROM messages WHERE sender_id=?
        UNION
        SELECT sender_id FROM messages WHERE receiver_id=?
    )
    AND u.user_id!=?
    ORDER BY last_timestamp DESC
";
$stmt = $conn->prepare($sql_conversations);
$stmt->bind_param("iiiiiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id,
                             $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'conversations' => $conversations]);
$conn->close();
?>