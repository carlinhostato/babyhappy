<?php
// api/auth/babysitter_list.php - Lista as reservas para a babysitter logada.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
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


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado.']);
    exit();
}

$babysitter_id = (int) $_SESSION['user_id'];

$query = "
    SELECT 
        b.booking_id,
        b.data_inicio,
        b.data_fim,
        b.status_reserva,
        b.montante_total,
        u.nome_completo AS nome_pai,
        u.phone AS telefone_pai
    FROM bookings b
    JOIN users u ON b.parent_id = u.user_id
    WHERE b.sitter_id = ?
    ORDER BY b.data_inicio DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na consulta: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $babysitter_id);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $bookings]);
?>