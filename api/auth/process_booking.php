<?php
// process_booking.php - API em JSON para o Babysitter gerir reservas

session_start();
header('Content-Type: application/json');

// Função auxiliar para enviar resposta
function send_response($success, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// --- 1. AUTENTICAÇÃO E CONFIGURAÇÃO ---
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "babysitter") {
    send_response(false, "Não autorizado ou sessão inválida.", 401);
}

require_once '../../../config/database.php';
$conn = get_db_connection();
if (!$conn) {
    send_response(false, "Erro de conexão com a base de dados.", 500);
}

// --- 2. RECEBER DADOS (DEVE SER POST, mas vamos aceitar JSON ou form-data) ---
// Vamos assumir que o FrontEnd envia JSON (melhor prática para AJAX)
$input_data = json_decode(file_get_contents('php://input'), true);

// Se não houver JSON, tenta o método POST tradicional (form-data)
if (!$input_data) {
    $input_data = $_POST;
}

$booking_id = filter_var($input_data['booking_id'] ?? null, FILTER_VALIDATE_INT);
$action = $input_data['action'] ?? '';
$sitter_id = (int)$_SESSION['user_id'];

// --- 3. VALIDAÇÃO ---
if (!$booking_id || !in_array($action, ['aceitar', 'rejeitar', 'concluir'])) {
    send_response(false, "Dados inválidos para processar a reserva.", 400);
}

// Mapeamento de ações para status na DB
$status_map = [
    'aceitar' => 'aceite',
    'rejeitar' => 'rejeitada', 
    'concluir' => 'concluida'
];

$new_status = $status_map[$action];

// --- 4. PROCESSAMENTO DA DB (Atualização) ---
$sql = "UPDATE bookings SET status_reserva = ? WHERE booking_id = ? AND babysitter_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    send_response(false, "Erro na preparação da query.", 500);
}

// Nota: Usei babysitter_id no bind, pois é mais claro que o sitter_id. Ajuste o nome da coluna se necessário.
$stmt->bind_param("sii", $new_status, $booking_id, $sitter_id);

if ($stmt->execute()) {
    $rows_affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    if ($rows_affected > 0) {
        // Sucesso: Devolve o novo status para o JavaScript atualizar
        send_response(true, "Reserva {$action}da com sucesso!", 200);
    } else {
        // Falha: Reserva não encontrada ou status já era o mesmo (0 linhas afetadas)
        send_response(false, "Ação não foi efetuada. A reserva não existe ou o status já estava definido.", 409);
    }
} else {
    $error_message = $stmt->error;
    $stmt->close();
    $conn->close();
    send_response(false, "Erro ao processar reserva: " . $error_message, 500);
}
?>