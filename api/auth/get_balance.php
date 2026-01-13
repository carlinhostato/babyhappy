<?php
// api/auth/get_balance.php
// Devolve saldo (wallet) e histórico combinado (payments + withdrawals)
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../backend/config/database.php';

function send_error(string $message, int $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    send_error('Não autenticado', 401);
}
$user_id = (int) $_SESSION['user_id'];

try {
    $conn = get_db_connection();
} catch (Exception $e) {
    send_error('Erro ao conectar BD: ' . $e->getMessage(), 500);
}

// 1) saldo via wallets
$current_balance = 0.0;
$stmt = $conn->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r && isset($r['balance'])) $current_balance = (float)$r['balance'];
}

// 2) histórico - combinamos payments e withdrawals
$history = [];

// payments
$stmtP = $conn->prepare("SELECT payment_id AS id, data_pagamento, type, referencia_gateway AS reference, montante AS amount, status_pagamento AS status FROM payments WHERE user_id = ?");
if ($stmtP) {
    $stmtP->bind_param('i', $user_id);
    $stmtP->execute();
    $payments = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtP->close();
} else {
    $payments = [];
}

// withdrawals
$stmtW = $conn->prepare("SELECT withdrawal_id AS id, created_at AS data_pagamento, 'WITHDRAWAL' AS type, reference AS reference, montante AS amount, status FROM withdrawals WHERE user_id = ?");
if ($stmtW) {
    $stmtW->bind_param('i', $user_id);
    $stmtW->execute();
    $withdrawals = $stmtW->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtW->close();
} else {
    $withdrawals = [];
}

// Normalize and combine
foreach ($payments as $p) {
    $type = strtoupper($p['type'] ?? '');
    $signed = ($type === 'LOAD_BALANCE') ? (float)$p['amount'] : -1.0 * (float)$p['amount'];
    $history[] = [
        'id' => $p['id'],
        'data_pagamento' => $p['data_pagamento'],
        'type' => $type,
        'referencia_gateway' => $p['reference'] ?? null,
        'montante' => abs((float)$p['amount']),
        'signed_montante' => $signed,
        'status' => $p['status'] ?? null,
    ];
}
foreach ($withdrawals as $w) {
    $signed = -1.0 * (float)$w['amount'];
    $history[] = [
        'id' => $w['id'],
        'data_pagamento' => $w['data_pagamento'],
        'type' => 'WITHDRAWAL',
        'referencia_gateway' => $w['reference'] ?? null,
        'montante' => abs((float)$w['amount']),
        'signed_montante' => $signed,
        'status' => $w['status'] ?? null,
    ];
}

// Ordena cronologicamente asc e calcula saldo_apos cumulativo
usort($history, function($a,$b){
    $at = strtotime($a['data_pagamento'] ?? '1970-01-01');
    $bt = strtotime($b['data_pagamento'] ?? '1970-01-01');
    if ($at === $bt) return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    return $at <=> $bt;
});

$running = 0.0;
$computed = [];
foreach ($history as $item) {
    $running = round($running + $item['signed_montante'], 2);
    $computed[] = [
        'id' => $item['id'],
        'data_pagamento' => $item['data_pagamento'],
        'type' => $item['type'],
        'referencia_gateway' => $item['referencia_gateway'],
        'montante' => $item['montante'],
        'signed_montante' => $item['signed_montante'],
        'saldo_apos' => $running,
        'status' => $item['status'] ?? null,
    ];
}

// Ajustar delta para bater com wallets.balance
$computed_final = $running;
$delta = round($current_balance - $computed_final, 2);
if (abs($delta) > 0.0001 && count($computed) > 0) {
    foreach ($computed as &$c) {
        $c['saldo_apos'] = round($c['saldo_apos'] + $delta, 2);
    }
    unset($c);
}
$computed = array_reverse($computed);

echo json_encode([
    'success' => true,
    'balance' => (float)$current_balance,
    'history' => $computed
]);
$conn->close();