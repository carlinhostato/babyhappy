<?php
// /babyhappy_v1/api/auth/fetch_payment_history.php

header('Content-Type: application/json');
if (!isset($_SESSION)) {
    session_start();
}

// 1. Verificação de Autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit();
}

$babysitter_id = (int) $_SESSION['user_id'];

// ATENÇÃO: Verifique o caminho da database.php
$database_path = __DIR__ . '/../../backend/config/database.php';
if (!file_exists($database_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de configuração: Ficheiro DB não encontrado.']);
    exit();
}
require_once $database_path;

try {
    $conn = get_db_connection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de conexão com o DB.']);
    exit();
}

// 2. Consulta de Histórico de Transações
// Seleciona pagamentos feitos pela mãe e o nome da mãe/cliente
$sql = "
    SELECT 
        p.payment_date, 
        p.amount, 
        p.booking_id,
        u.nome_completo AS parent_name
    FROM 
        payments p
    JOIN 
        users u ON p.parent_id = u.user_id
    WHERE 
        p.babysitter_id = ? AND p.status = 'concluído' 
    ORDER BY 
        p.payment_date DESC
";

$stmt = $conn->prepare($sql);

$history = [];

if ($stmt) {
    $stmt->bind_param("i", $babysitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'data' => date('d/m/Y', strtotime($row['payment_date'])),
            'cliente' => htmlspecialchars($row['parent_name']),
            'valor' => number_format($row['amount'], 2, ',', '.') . ' €',
            'reserva_id' => $row['booking_id']
        ];
    }
    $stmt->close();
}

$conn->close();

// 3. Retorno JSON
echo json_encode([
    'success' => true,
    'history' => $history
]);
?>