<?php
// pagamento.php - O SCRIPT QUE FAZ O INSERT NA BD (O processador)

session_start();
header('Content-Type: application/json');

// --- 1. CONFIGURAÇÃO E PRÉ-CHECKS ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Autenticação necessária.']);
    exit;
}

// Assumindo que este script está dentro de um caminho relativo à config
$path = dirname(__DIR__, 3) . '/config/database.php';
require_once $path;

try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) { 
        throw new Exception("Erro ao conectar à base de dados.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de sistema: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'Ação não especificada.'];

if ($action === 'simulate_payment') {
    // Sanitização e validação inicial
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT); 
    
    $parent_id = (int)$_SESSION['user_id'];
    $sitter_id = null;
    
    // Validação básica do input
    if (!$booking_id || $amount <= 0) {
        http_response_code(400);
        $response['message'] = 'Dados de pagamento inválidos.';
        echo json_encode($response);
        exit;
    }

    $conn->begin_transaction();
    try {
        // A. OBTER SITTER_ID E VALIDAR A RESERVA (SELECT FOR UPDATE bloqueia a linha)
        $sql_check_booking = "SELECT babysitter_id, status_reserva 
                              FROM bookings 
                              WHERE booking_id = ? AND parent_id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($sql_check_booking);
        
        if ($stmt_check === false) {
            throw new Exception('Falha ao preparar check da Reserva: ' . $conn->error);
        }
        $stmt_check->bind_param("ii", $booking_id, $parent_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows === 0) {
             throw new Exception('Reserva não encontrada ou não pertence ao utilizador.');
        }
        
        $booking_data = $result->fetch_assoc();
        $sitter_id = $booking_data['babysitter_id'];
        
        if ($booking_data['status_reserva'] === 'paga') {
             throw new Exception('Esta reserva já se encontra paga.');
        }
        
        $stmt_check->close();
        
        // B. INSERIR NA TABELA PAYMENTS
        $sql_payment = "INSERT INTO payments 
                        (user_id, booking_id, status_pagamento, montante, type, referencia_gateway) 
                        VALUES (?, ?, 'Sucesso', ?, 'BOOKING_PAYMENT', ?)";
        
        $stmt_payment = $conn->prepare($sql_payment);
        
        if ($stmt_payment === false) {
            throw new Exception('Falha ao preparar o INSERT de Pagamento: ' . $conn->error);
        }
        
        // Gerar referência única (simulada)
        $gateway_ref = 'BOOK_' . strtoupper(substr(md5(uniqid()), 0, 10));
        
        // user_id é o parent_id
        $stmt_payment->bind_param("iids", $parent_id, $booking_id, $amount, $gateway_ref); 
        
        if (!$stmt_payment->execute()) {
            throw new Exception('Falha na execução do INSERT de Pagamento: ' . $stmt_payment->error);
        }
        
        $payment_id = $conn->insert_id;
        $stmt_payment->close();

        // C. ATUALIZAR RESERVA para 'paga'
        $sql_update_booking = "UPDATE bookings SET status_reserva = 'paga' WHERE booking_id = ?";
        $stmt_update = $conn->prepare($sql_update_booking);
        $stmt_update->bind_param("i", $booking_id);
        if (!$stmt_update->execute()) {
            throw new Exception('Falha ao atualizar status da Reserva: ' . $stmt_update->error);
        }
        $stmt_update->close();
        
        // D. CRÉDITO AO BABYSITTER (A CHAVE DO SEU SISTEMA)
        // Adiciona o montante à carteira do babysitter
        $sql_credit = "UPDATE wallets SET balance = balance + ? WHERE user_id = ?";
        $stmt_credit = $conn->prepare($sql_credit);
        
        if ($stmt_credit === false) {
            throw new Exception('Falha ao preparar o Crédito na Carteira: ' . $conn->error);
        }
        
        $stmt_credit->bind_param("di", $amount, $sitter_id);
        
        if (!$stmt_credit->execute()) {
            throw new Exception('Falha ao executar o Crédito na Carteira: ' . $stmt_credit->error);
        }
        $stmt_credit->close();


        // E. SUCESSO FINAL
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = '✅ Pagamento processado com sucesso. Reserva paga e saldo creditado ao babysitter.';
        $response['payment_id'] = $payment_id;

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        $response['message'] = '❌ ERRO DE TRANSAÇÃO: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>