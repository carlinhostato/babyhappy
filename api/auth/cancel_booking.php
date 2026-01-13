<?php
// api/auth/cancel_booking.php - Processa o cancelamento de uma reserva via AJAX

session_start();
header('Content-Type: application/json'); // Resposta em JSON

// ⚠️ AJUSTE ESTE CAMINHO CONFORME A SUA ESTRUTURA
$database_path = __DIR__ . '/../../../config/database.php';
if (!file_exists($database_path)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro de configuração: database.php não encontrado."]);
    exit();
}
require_once $database_path; 

// 1. Verificar autenticação e método
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "parent" || $_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado ou sessão expirada.']);
    exit();
}

$conn = get_db_connection();
$parent_id = $_SESSION["user_id"];

// 2. Obter o booking_id a partir do corpo POST (via FormData do JS)
// Nota: Usamos filter_input(INPUT_POST) que funciona com o FormData do JS
$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

// 3. Validação do ID
if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de reserva inválido.']);
    exit();
}

try {
    // 4. Verifica o status atual da reserva e se pertence ao Pai
    $sql_check = "SELECT status_reserva FROM bookings WHERE booking_id = ? AND parent_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    
    if ($stmt_check === false) {
        throw new Exception("Erro ao preparar a verificação de status.");
    }
    
    $stmt_check->bind_param("ii", $booking_id, $parent_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $booking_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$booking_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reserva não encontrada ou não pertence a si.']);
        exit();
    }

    // Verifica se o status permite o cancelamento pelo pai
    // Nota: 'Confirmada' corresponde a 'aprovada' em alguns fluxos anteriores, mantemos a compatibilidade
    $allowed_statuses = ['Solicitada', 'Confirmada', 'aprovada']; 
    $current_status = $booking_data['status_reserva'];
    
    if (!in_array($current_status, $allowed_statuses)) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Esta reserva não pode ser cancelada neste estado (' . $current_status . ').']);
        exit();
    }

    // 5. Executar a Atualização para 'Cancelada'
    $new_status = 'Cancelada';
    $sql_update = "UPDATE bookings SET status_reserva = ? WHERE booking_id = ? AND parent_id = ?";
    $stmt_update = $conn->prepare($sql_update);

    if ($stmt_update === false) {
        throw new Exception("Erro interno na DB ao preparar a query de atualização.");
    }

    $stmt_update->bind_param("sii", $new_status, $booking_id, $parent_id);

    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reserva cancelada com sucesso!']);
    } else {
        throw new Exception('Erro ao executar o cancelamento: ' . $stmt_update->error);
    }

    $stmt_update->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>