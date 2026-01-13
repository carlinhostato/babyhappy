<?php
// api/chat/fetch_messages.php - Retorna detalhes do chat ativo e mensagens

session_start();
header('Content-Type: application/json');

// ===============================================
// ✅ FUNÇÕES AUXILIARES DEFINIDAS AQUI PARA EVITAR ERROS FATAIS
// ===============================================

// Função auxiliar para tratamento de erros
function send_error($message, $code = 400) {
    // Garante que o cabeçalho JSON é enviado, mesmo em caso de erro PHP
    header('Content-Type: application/json'); 
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Função para formatar o status (Online/Visto pela última vez)
function format_partner_status($last_activity, $threshold_seconds = 30) {
    if (!$last_activity) return '<span class="status-offline">Offline</span>';

    // Calcula a diferença de tempo (em segundos)
    $time_diff = time() - strtotime($last_activity); 
    
    if ($time_diff < $threshold_seconds) {
        return '<span class="status-online">Online</span>';
    } else {
        // Formata o tempo para H:i
        $last_seen = date('H:i', strtotime($last_activity));
        return '<span class="status-offline">Visto pela última vez às ' . $last_seen . '</span>';
    }
}
// ===============================================


$database_path = __DIR__ . '/../../backend/config/database.php';

// Verificar se o ficheiro existe
if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado", 500);
}

require_once $database_path;

// Obter conexão
try {
    // Assumindo que get_db_connection() é definida em database.php
    $conn = get_db_connection();
    
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados", 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}

// -----------------------------------------------------------------------
// VALIDAÇÃO E BUSCA DE DADOS
// -----------------------------------------------------------------------

$current_user_id = $_SESSION['user_id'] ?? null;
// NOTA: No fetch_client.js atualizado, o ID do utilizador é obtido pela API,
// mas a lógica fetchActiveChat ainda passa o ID no URL (compatibilidade).
// Se o seu JS estiver a usar a API 'FETCH_ID', esta linha depende do URL, 
// o que pode ser uma inconsistência. Mantenha por enquanto se a autenticação via sessão falhar.

// Se estiver a usar o JS mais recente (com API), $current_user_id deve vir da Sessão,
// mas a verificação de autenticação é essencial.
if (!$current_user_id) {
    send_error("Não autenticado. Sessão inválida.", 401);
}

$active_chat_id = (int) ($_GET['chat_with'] ?? 0);

if ($active_chat_id <= 0) {
    send_error('ID do parceiro de chat em falta ou inválido.');
}

// 1. Obter detalhes do parceiro ativo e status
$stmt = $conn->prepare("SELECT nome_completo, last_activity FROM users WHERE user_id=?");
if (!$stmt) send_error("Erro de preparação (Parceiro): " . $conn->error, 500);

$stmt->bind_param("i", $active_chat_id);
$stmt->execute();
$partner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$partner) {
    send_error('Parceiro não encontrado.', 404);
}

// 2. Obter mensagens
$stmt = $conn->prepare("
    SELECT message_id, conteudo AS message_text, sender_id, timestamp, is_read
    FROM messages
    WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)
    ORDER BY timestamp ASC
");
if (!$stmt) send_error("Erro de preparação (Mensagens): " . $conn->error, 500);

$stmt->bind_param("iiii", $current_user_id, $active_chat_id, $active_chat_id, $current_user_id);
$stmt->execute();
$messages_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Marcar como lido (execução silenciosa, não afeta o JSON de retorno)
$stmt = $conn->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?");
if (!$stmt) { /* Log error, but proceed */ } else {
    $stmt->bind_param("ii", $current_user_id, $active_chat_id);
    $stmt->execute();
    $stmt->close();
}

// 4. Formatar e retornar dados
$formatted_messages = [];
foreach ($messages_data as $msg) {
    $formatted_messages[] = [
        'message_id' => $msg['message_id'],
        'text' => htmlspecialchars($msg['message_text']),
        'is_my' => $msg['sender_id'] == $current_user_id,
        'time' => date('H:i', strtotime($msg['timestamp'])),
        // Se a mensagem for minha, mostro status de leitura. Se não for, vazio.
        'visto' => ($msg['sender_id'] == $current_user_id) ? ($msg['is_read'] ? '✓✓' : '✓') : ''
    ];
}

echo json_encode([
    'success' => true,
    'partner_name' => htmlspecialchars($partner['nome_completo']),
    'partner_status_html' => format_partner_status($partner['last_activity']),
    'messages' => $formatted_messages
]);
$conn->close();
?>