<?php
// api/auth/process_payment.php
// Processa carregamentos (LOAD_BALANCE) e pagamentos de reserva (BOOKING_PAYMENT)
// Agora: para pagamento de reserva usa o saldo da wallet do parent (debit) se houver saldo suficiente.
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../backend/config/database.php';

function send_error(string $message, int $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    send_error('Não autenticado', 401);
}
$user_id = (int) $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) send_error('Request inválida', 400);

$booking_id = isset($input['booking_id']) ? (int)$input['booking_id'] : 0;
$amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
$method = isset($input['method']) ? trim($input['method']) : 'Cartão/MBWAY';

if ($amount <= 0) send_error('Montante inválido.', 400);
if ($booking_id < 0) send_error('booking_id inválido.', 400);

$conn = null;
$inTransaction = false;

try {
    $conn = get_db_connection();
    $conn->begin_transaction();
    $inTransaction = true;

    $reference = 'REF-' . time() . '-' . rand(100,999);

    if ($booking_id > 0) {
        // Pagamento de reserva: devemos usar o saldo da wallet do parent (debit) e creditar o sitter.
        // 1) Verificar reserva e pertença ao parent
        $stmt = $conn->prepare("SELECT sitter_id, status_reserva FROM bookings WHERE booking_id = ? AND parent_id = ? LIMIT 1");
        if (!$stmt) throw new Exception('Erro ao preparar consulta de reserva: ' . $conn->error);
        $stmt->bind_param('ii', $booking_id, $user_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) {
            throw new Exception('Reserva não encontrada ou não pertence ao utilizador.');
        }
        if (strtolower($booking['status_reserva']) === 'paga') {
            throw new Exception('Reserva já paga.');
        }

        $sitter_id = (int)$booking['sitter_id'];

        // 2) Obter saldo do parent (wallet) com lock FOR UPDATE
        $stmtBal = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
        if (!$stmtBal) throw new Exception('Erro ao preparar consulta wallet: ' . $conn->error);
        $stmtBal->bind_param('i', $user_id);
        $stmtBal->execute();
        $row = $stmtBal->get_result()->fetch_assoc();
        $stmtBal->close();

        $current_balance = $row ? (float)$row['balance'] : 0.0;
        if ($current_balance < $amount) {
            // saldo insuficiente — rollback e devolver erro com mensagem clara
            $conn->rollback();
            $inTransaction = false;
            echo json_encode(['success' => false, 'message' => 'Saldo insuficiente. Por favor carregue saldo antes de pagar a reserva.']);
            exit;
        }

        // 3) Debitar a wallet do parent
        $stmtDeduct = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        if (!$stmtDeduct) throw new Exception('Erro ao preparar dedução de wallet: ' . $conn->error);
        $stmtDeduct->bind_param('di', $amount, $user_id);
        $stmtDeduct->execute();
        if ($stmtDeduct->affected_rows === 0) {
            throw new Exception('Falha ao actualizar saldo do parent.');
        }
        $stmtDeduct->close();

        // 4) Registar payment do tipo BOOKING_PAYMENT (no histórico/payments)
        $stmtP = $conn->prepare("INSERT INTO payments (user_id, booking_id, status_pagamento, montante, type, referencia_gateway) VALUES (?, ?, 'Sucesso', ?, 'BOOKING_PAYMENT', ?)");
        if (! $stmtP) throw new Exception('Erro ao preparar inserção em payments: ' . $conn->error);
        $stmtP->bind_param('iids', $user_id, $booking_id, $amount, $reference);
        $stmtP->execute();
        $stmtP->close();

        // 5) Creditar wallet do sitter (cria se não existir)
        $stmtW = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
        if (! $stmtW) throw new Exception('Erro ao preparar wallet update (sitter): ' . $conn->error);
        $stmtW->bind_param('id', $sitter_id, $amount);
        $stmtW->execute();
        $stmtW->close();

        // 6) Actualizar status da reserva
        $stmtU = $conn->prepare("UPDATE bookings SET status_reserva = 'paga' WHERE booking_id = ?");
        if (! $stmtU) throw new Exception('Erro ao preparar update booking: ' . $conn->error);
        $stmtU->bind_param('i', $booking_id);
        $stmtU->execute();
        $stmtU->close();

        $conn->commit();
        $inTransaction = false;
        echo json_encode(['success' => true, 'message' => 'Pagamento da reserva efectuado usando saldo da wallet.', 'reference' => $reference]);
        exit;
    } else {
        // Carregamento de saldo (LOAD_BALANCE) — comportamento inalterado
        $stmtP = $conn->prepare("INSERT INTO payments (user_id, status_pagamento, montante, type, referencia_gateway) VALUES (?, 'Sucesso', ?, 'LOAD_BALANCE', ?)");
        if (! $stmtP) throw new Exception('Erro ao preparar inserção em payments: ' . $conn->error);
        $stmtP->bind_param('ids', $user_id, $amount, $reference);
        $stmtP->execute();
        $stmtP->close();

        $stmtW = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
        if (! $stmtW) throw new Exception('Erro ao preparar wallet update: ' . $conn->error);
        $stmtW->bind_param('id', $user_id, $amount);
        $stmtW->execute();
        $stmtW->close();

        $conn->commit();
        $inTransaction = false;
        echo json_encode(['success' => true, 'message' => 'Carregamento registado com sucesso.', 'reference' => $reference]);
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $inTransaction) {
        try { $conn->rollback(); } catch (Throwable $t) { error_log('Rollback falhou: ' . $t->getMessage()); }
        $inTransaction = false;
    }
    error_log("process_payment error: " . $e->getMessage());
    send_error('Erro ao processar pagamento. ' . $e->getMessage(), 500);
}