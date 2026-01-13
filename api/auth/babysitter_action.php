<?php
/**
 * api/auth/babysitter_action.php
 * Processa a aprovação ou recusa de uma reserva por parte da babysitter.
 */

// Impede que erros/avisos sujem a resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

// 1. Conexão à Base de Dados
require_once __DIR__ . '/../../backend/config/database.php';

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Sessão expirada. Faça login novamente."]);
    exit;
}

$conn = get_db_connection();
$user_id = $_SESSION["user_id"]; // ID da Babysitter logada

// 2. Capturar dados enviados pelo FormData
$booking_id = $_POST['booking_id'] ?? null;
$action = $_POST['action'] ?? null; // 'aprovar' ou 'recusar'

// 3. Validação Inicial
if (!$booking_id || !$action) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Dados incompletos para processar a ação."]);
    exit;
}

// 4. Mapear o estado para a Base de Dados
$novo_status = ($action === 'aprovar') ? 'aprovada' : 'recusada';

try {
    // 5. Query de Atualização
    // Importante: Usamos 'status_reserva' (corrigido do erro anterior)
    // E garantimos que o sitter_id é o do utilizador logado para segurança.
    $sql = "UPDATE bookings 
            SET status_reserva = ? 
            WHERE booking_id = ? AND sitter_id = ?";
            
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Erro na preparação da query: " . $conn->error);
    }

    $stmt->bind_param("sii", $novo_status, $booking_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                "success" => true, 
                "message" => "Reserva " . $novo_status . " com sucesso!",
                "new_status" => $novo_status
            ]);
        } else {
            // Caso o ID não exista ou não pertença a este sitter
            echo json_encode([
                "success" => false, 
                "message" => "Nenhuma alteração feita. Verifique se a reserva lhe pertence."
            ]);
        }
    } else {
        throw new Exception("Erro ao executar atualização.");
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}

$conn->close();
?>