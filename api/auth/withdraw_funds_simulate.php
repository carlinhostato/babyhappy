<?php
// C:\xampp\htdocs\babyhappy_v1\api\auth\withdraw_funds.php
// Objetivo: DEBITA o saldo na DB (APENAS na tabela wallets).

// 1. Configuração e Funções
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// Função de segurança para enviar erros JSON
function send_error($message, $code = 400, $conn = null) {
    // Se houver uma conexão aberta, faz rollback antes de enviar o erro
    if ($conn) {
        $conn->rollback();
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// Nota: Adicione esta função auxiliar para formatar a mensagem
function format_currency_php($amount) {
    return number_format($amount, 2, ',', '.');
}

// 2. Verificação de Autenticação
if (!isset($_SESSION['user_id'])) {
    send_error("Utilizador não autenticado. Sessão inválida.", 401);
}
$user_id = (int) $_SESSION["user_id"];


// 3. Configuração da Base de Dados
$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado", 500);
}
require_once $database_path;

try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) { 
        send_error("Erro ao conectar à base de dados", 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}

// 4. Receber e Sanitizar Dados
$data = json_decode(file_get_contents('php://input'), true);

$amount = isset($data['amount']) ? (float) $data['amount'] : 0;
$method = $data['method'] ?? '';
$details_value = substr(trim($data['details_value'] ?? ''), 0, 50); 
$min_amount = 1.00;


// 5. Validações de Negócio (Mantidas)
if ($amount <= 0 || $amount < $min_amount) {
    send_error('Montante inválido (Mínimo ' . format_currency_php($min_amount) . ' €).', 400, $conn);
}
$valid_methods = ['DEBIT_CARD', 'MBWAY'];
if (!in_array($method, $valid_methods)) {
    send_error('Método de levantamento inválido.', 400, $conn);
}
if (empty($details_value)) {
    send_error('Os detalhes do método são obrigatórios.', 400, $conn);
}

if ($method === 'MBWAY') {
    $details_value_cleaned = preg_replace('/\D/', '', $details_value);
    if (strlen($details_value_cleaned) != 9) {
        send_error('O número de telemóvel MB Way deve ter 9 dígitos.', 400, $conn);
    }
}


// 6. INÍCIO DA TRANSAÇÃO (Foco apenas no débito do saldo)
$conn->begin_transaction();

try {
    // A. LEITURA E BLOQUEIO DO SALDO ATUAL (SELECT FOR UPDATE)
    $sql_check = "SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE";
    $stmt_check = $conn->prepare($sql_check);
    
    if (!$stmt_check) {
        throw new Exception("Erro de preparação SQL (Check Saldo): " . $conn->error);
    }

    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $current_balance = $result->fetch_assoc()['balance'] ?? 0.00;
    $stmt_check->close();

    // B. VALIDAÇÃO CRÍTICA (Saldo)
    if ($amount > $current_balance) {
        throw new Exception('Saldo insuficiente: Disponível ' . format_currency_php($current_balance) . ' €.');
    }
    
    $new_balance = $current_balance - $amount;

    // C. DÉBITO DO SALDO (Atualização da tabela wallets)
    $sql_update = "UPDATE wallets SET balance = ? WHERE user_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    
    if (!$stmt_update) {
        throw new Exception("Erro de preparação SQL (Update Saldo): " . $conn->error);
    }
    
    $stmt_update->bind_param("di", $new_balance, $user_id);
    $stmt_update->execute();
    $stmt_update->close();

    // ⚠️ ETAPA REMOVIDA: Remoção da inserção na tabela 'withdrawals'

    // D. SUCESSO E COMMIT
    $conn->commit();
    $confirmation_detail = $method === 'MBWAY' ? 'Telemóvel ' . $details_value : 'IBAN/Cartão';

    http_response_code(200);
    echo json_encode([
        'success' => true,
        // Envia o novo saldo para o JavaScript atualizar
        'new_balance_simulated' => $new_balance, 
        'message' => '✅ Levantamento de ' . format_currency_php($amount) . ' € DEBITADO do seu saldo com sucesso. Detalhes: ' . $confirmation_detail
    ]);

} catch (Exception $e) {
    // Em caso de qualquer erro, faz ROLLBACK
    send_error('❌ ERRO: Falha ao debitar o saldo. ' . $e->getMessage(), 500, $conn);
} finally {
    // Fechar conexão
    if (isset($conn)) {
        $conn->close();
    }
}
?>