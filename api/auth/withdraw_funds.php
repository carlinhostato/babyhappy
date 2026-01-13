<?php
// api/auth/withdraw_funds.php - Processa o pedido de levantamento (API JSON)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
$database_path = __DIR__ . '/../../backend/config/database.php';

// Função de segurança para enviar erros
function send_error($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Verificar se o ficheiro existe
if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado", 500);
}

require_once $database_path;

// Obter conexão
try {
    // Assume que get_db_connection() devolve um objeto mysqli ou null/false em caso de falha.
    $conn = get_db_connection();
    
    // Verifica se a conexão falhou (se devolveu NULL ou erro)
    if (!$conn || $conn->connect_error) { 
        send_error("Erro ao conectar à base de dados", 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}


if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilizador não autenticado.']);
    $conn->close(); // Fechar conexão se não autenticado
    exit();
}


$babysitter_id = (int) $_SESSION["user_id"];
$data = json_decode(file_get_contents('php://input'), true);

$amount = floatval($data['amount'] ?? 0);
$method = $data['method'] ?? '';
$details_value = trim($data['details_value'] ?? '');

// 1. Validação de Saldo
$sql_saldo = "SELECT COALESCE(balance, 0.00) AS balance FROM wallets WHERE user_id = ?";
$stmt_saldo = $conn->prepare($sql_saldo);

// ✅ CORREÇÃO CRÍTICA (Evita o Fatal Error Call to a member function bind_param() on bool)
if ($stmt_saldo === false) {
    // Se a preparação falhar, devolvemos o erro exato do MySQL
    send_error("Erro de preparação SQL (Saldo). Verifique a sintaxe ou a tabela 'wallets': " . $conn->error, 500);
}

$stmt_saldo->bind_param("i", $babysitter_id);
$stmt_saldo->execute();
$current_balance = $stmt_saldo->get_result()->fetch_assoc()['balance'] ?? 0.00;
$stmt_saldo->close();

if ($amount <= 0 || $amount > $current_balance) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Montante inválido ou superior ao saldo disponível.']);
    $conn->close();
    exit();
}

// 2. Validação Específica dos Detalhes
if ($method === 'MBWAY' && (!preg_match('/^\d{9}$/', $details_value) || strlen($details_value) !== 9)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de telemóvel MB Way deve ter 9 dígitos.']);
    $conn->close();
    exit();
} elseif ($method === 'DEBIT_CARD' && strlen($details_value) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Detalhes do IBAN/Cartão inválidos.']);
    $conn->close();
    exit();
}

// 3. Processamento de Levantamento (Transação)
$conn->begin_transaction();
try {
    // 3a. Atualizar o saldo (Diminuir)
    $update_wallet = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
    if ($update_wallet === false) {
        throw new Exception("Erro de preparação SQL (Wallet): " . $conn->error);
    }
    $update_wallet->bind_param("di", $amount, $babysitter_id);
    $update_wallet->execute();

    // 3b. Criar registo de transação de levantamento
    $insert_transaction = $conn->prepare("
        INSERT INTO payments (user_id, montante, type, status_pagamento, details) 
        VALUES (?, ?, 'WITHDRAWAL', 'PENDENTE', CONCAT(?, ':', ?))
    ");
    
    if ($insert_transaction === false) {
        throw new Exception("Erro de preparação SQL (Transaction): " . $conn->error);
    }
    
    // Tipos de parâmetros: i (user_id), d (amount), s (method), s (details_value)
    $insert_transaction->bind_param("idss", $babysitter_id, $amount, $method, $details_value);
    $insert_transaction->execute();

    $conn->commit();
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Pedido de levantamento de ' . number_format($amount, 2, ',', '.') . ' € submetido com sucesso. Será processado em breve.'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar o levantamento. Contacte o suporte. Detalhe: ' . $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>