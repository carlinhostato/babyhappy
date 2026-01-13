<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../backend/config/database.php';

function send_error(string $msg, int $code = 400) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
}

/* ===============================
   1. AUTENTICAÇÃO
================================ */
if (!isset($_SESSION['user_id'])) {
    send_error("Sessão expirada.", 401);
}

$user_id = (int) $_SESSION['user_id'];

/* ===============================
   2. LOCK DE SESSÃO (CRÍTICO)
   ISTO É O QUE RESOLVE DE VEZ
================================ */
if (!empty($_SESSION['payment_lock'])) {
    send_error("Pagamento já em processamento. Aguarde.", 429);
}
$_SESSION['payment_lock'] = true;

/* ===============================
   3. INPUT
================================ */
$data = json_decode(file_get_contents('php://input'), true);

$amount = (float) ($data['amount'] ?? 0);
$method = trim($data['method'] ?? '');

if ($amount <= 0 || $method === '') {
    unset($_SESSION['payment_lock']);
    send_error("Dados inválidos.");
}

/* ===============================
   4. DB
================================ */
$conn = get_db_connection();
if (!$conn) {
    unset($_SESSION['payment_lock']);
    send_error("Erro de base de dados.", 500);
}

/* ===============================
   5. TRANSACTION
================================ */
$conn->begin_transaction();

try {

    // referência apenas para logging
    $gateway_ref = 'WALLET_' . strtoupper(bin2hex(random_bytes(6)));

    // INSERT payments (UMA VEZ)
    $stmt = $conn->prepare("
        INSERT INTO payments
            (user_id, booking_id, status_pagamento, montante, type, referencia_gateway, data_pagamento)
        VALUES
            (?, NULL, 'Sucesso', ?, 'LOAD_BALANCE', ?, NOW())
    ");
    $stmt->bind_param("ids", $user_id, $amount, $gateway_ref);
    $stmt->execute();
    $stmt->close();

    // UPDATE wallet
    $stmt = $conn->prepare("
        UPDATE wallets
        SET balance = balance + ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("di", $amount, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    unset($_SESSION['payment_lock']);

    echo json_encode([
        "success" => true,
        "message" => "💰 Saldo adicionado com sucesso via {$method}."
    ]);
    exit();

} catch (Throwable $e) {

    $conn->rollback();
    unset($_SESSION['payment_lock']);

    send_error("Erro ao processar pagamento.", 500);
}
