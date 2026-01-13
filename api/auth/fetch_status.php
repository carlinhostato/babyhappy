<?php
// api/auth/fetch_status.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Reportar erros para ajudar no debug (podes remover isto depois de funcionar)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar no output para não quebrar o JSON

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// 1. CONEXÃO
$database_path = __DIR__ . '/../../backend/config/database.php'; 
if (!file_exists($database_path)) send_error("database.php não encontrado", 500);

require_once $database_path;

try {
    $conn = get_db_connection();
    if (!$conn) send_error("Falha na conexão à BD", 500);
} catch (Exception $e) {
    send_error("Exceção de conexão: " . $e->getMessage(), 500);
}

// 2. VALIDAÇÃO
$current_user_id = $_SESSION['user_id'] ?? null;
$partner_id = $_GET['partner_id'] ?? null;

if (!$current_user_id || !$partner_id || !is_numeric($partner_id)) {
    send_error("IDs inválidos ou sessão expirada.", 401);
}

// 3. CONSULTA (Detecta automaticamente se a coluna é 'ultima_atividade' ou 'last_activity')
try {
    // Primeiro, tentamos descobrir qual coluna existe na tua tabela
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'ultima_atividade'");
    $column_name = ($res->num_rows > 0) ? 'ultima_atividade' : 'last_activity';

    $stmt = $conn->prepare("SELECT $column_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        send_error("Parceiro não encontrado.", 404);
    }

    $user_data = $result->fetch_assoc();
    $last_act_raw = $user_data[$column_name];
    $stmt->close();

    // 4. LÓGICA DE STATUS
    $is_online = false;
    $status_html = '<span class="status-offline">Offline</span>';

    if ($last_act_raw) {
        $last_activity_ts = strtotime($last_act_raw);
        $time_diff = time() - $last_activity_ts;

        if ($time_diff <= 60) {
            $is_online = true;
            $status_html = '<span class="status-online">Online</span>';
        } else {
            $last_seen = date('H:i', $last_activity_ts);
            $status_html = '<span class="status-offline">Visto às ' . $last_seen . '</span>';
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $is_online ? 'online' : 'offline',
        'status_html' => $status_html
    ]);

} catch (Exception $e) {
    send_error("Erro de SQL: " . $e->getMessage(), 500);
} finally {
    if (isset($conn)) $conn->close();
}