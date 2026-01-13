<?php
// api/auth/babysitter_payments_fetch.php - Retorna Saldo e Histórico (JSON)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// CORREÇÃO DO CAMINHO: Assumindo que este ficheiro está em /api/payments/
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

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "babysitter") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.']);
    exit();
}

$babysitter_id = (int) $_SESSION["user_id"];

// --- 1. BUSCAR SALDO ATUAL ---
$saldo_total = 0.00;
$sql_saldo = "SELECT COALESCE(balance, 0.00) AS total_recebido FROM wallets WHERE user_id = ?";
try {
    $stmt_saldo = $conn->prepare($sql_saldo);
    $stmt_saldo->bind_param("i", $babysitter_id);
    $stmt_saldo->execute();
    $saldo_total = $stmt_saldo->get_result()->fetch_assoc()['total_recebido'] ?? 0.00;
    $stmt_saldo->close();
} catch (Exception $e) {
    $saldo_total = 0.00; // Manter 0 em caso de erro de BD
}

// --- 2. BUSCAR HISTÓRICO DE RECEBIMENTOS ---
$sql_pagamentos = "
    SELECT 
        p.data_pagamento,
        p.montante,
        p.booking_id,
        u.nome_completo AS parent_nome
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN users u ON b.parent_id = u.user_id
    WHERE b.sitter_id = ? 
      AND p.type = 'BOOKING_PAYMENT'
      AND p.status_pagamento IN ('Sucesso', 'COMPLETED')
    ORDER BY p.data_pagamento DESC
";
$pagamentos = [];
$stmt_pagamentos = $conn->prepare($sql_pagamentos);

if ($stmt_pagamentos) {
    $stmt_pagamentos->bind_param("i", $babysitter_id);
    $stmt_pagamentos->execute();
    $result = $stmt_pagamentos->get_result();
    while ($row = $result->fetch_assoc()) {
        $pagamentos[] = $row;
    }
    $stmt_pagamentos->close();
}
$conn->close();

echo json_encode([
    'success' => true,
    'saldo' => number_format($saldo_total, 2, '.', ''), // Enviar como string float
    'historico' => $pagamentos
]);
?>