<?php
// make_booking.php - Localização: /api/auth/

session_start();
header('Content-Type: application/json');

// --------------------------------------------------------------
// 0. FUNÇÃO AUXILIAR DE ERRO (Com responsabilidade de fechar a conexão)
// --------------------------------------------------------------

/**
 * Envia uma resposta de erro JSON e termina a execução.
 * @param string $message A mensagem de erro.
 * @param int $status_code O código de status HTTP (padrão 500).
 * @param mysqli|null $conn A conexão DB para fechar.
 */
function send_error(string $message, int $status_code = 500, $conn = null) {
    http_response_code($status_code); 
    echo json_encode(["success" => false, "message" => $message]);
    
    // FECHA A CONEXÃO APENAS SE ELA EXISTIR, FOR UMA INSTÂNCIA DE mysqli, e ESTIVER ATIVA
    if ($conn && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
    exit(); 
}

// --------------------------------------------------------------
// 1. CONEXÃO COM A BASE DE DADOS
// --------------------------------------------------------------

$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado.", 500);
}

require_once $database_path;

// Obter conexão
$conn = get_db_connection();
    
if (!$conn) {
    send_error("Erro ao conectar à base de dados. Verifique a porta/credenciais/MySQL ativo.", 500); 
}


// --------------------------------------------------------------
// 2. INICIALIZAÇÃO E AUTENTICAÇÃO
// --------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Método não permitido. Use POST.", 405, $conn);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se o utilizador está logado e tem a role 'parent'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== 'parent') {
    send_error("Não autorizado. Necessita de ser um utilizador 'parent' logado.", 401, $conn); 
}
$current_user_id = $_SESSION['user_id'];

// --------------------------------------------------------------
// 3. VALIDAÇÃO DOS DADOS RECEBIDOS
// --------------------------------------------------------------

$sitter_id       = filter_var($data['sitter_id'] ?? null, FILTER_VALIDATE_INT);
$data_inicio_str = $data['data_inicio'] ?? '';
$data_fim_str    = $data['data_fim'] ?? '';
$preco_hora      = filter_var($data['preco_hora'] ?? 0.0, FILTER_VALIDATE_FLOAT); 


// 3.1: Validação Básica
if ($sitter_id === false || $sitter_id <= 0) {
    send_error("Falha de Validação (400): O ID do Babysitter é inválido ou não foi enviado.", 400, $conn);
}
if (!$data_inicio_str || !$data_fim_str) {
    send_error("Falha de Validação (400): Datas de Início e Fim são obrigatórias.", 400, $conn);
}
if ($preco_hora === false || $preco_hora <= 0) {
    send_error("Falha de Validação (400): Preço por hora inválido ou não foi enviado.", 400, $conn);
}

// 3.2: Validação de Data/Hora e Lógica
try {
    // Cria objetos DateTime para manipulação de tempo
    $data_inicio = new DateTime($data_inicio_str); 
    $data_fim    = new DateTime($data_fim_str);     

    // Verifica a ordem cronológica
    if ($data_fim <= $data_inicio) {
        send_error("Falha de Validação (400): A data/hora de fim deve ser estritamente posterior à de início.", 400, $conn);
    }
    
    // Verifica se a reserva é para o futuro.
    $timezone = new DateTimeZone('Europe/Lisbon'); 
    $now = new DateTime('now', $timezone);

    if ($data_inicio <= $now) {
        send_error("Falha de Validação (400): Não é possível solicitar reservas no passado ou no momento atual.", 400, $conn);
    }

} catch (Exception $e) {
    send_error("Falha de Validação (400): Formato de data/hora inválido ou malformado. Detalhes: " . $e->getMessage(), 400, $conn);
}


// --------------------------------------------------------------
// 4. CÁLCULO E INSERÇÃO NA BASE DE DADOS
// --------------------------------------------------------------

// Cálculo do Montante Total
$segundos       = $data_fim->getTimestamp() - $data_inicio->getTimestamp();
$horas          = max(0, $segundos / 3600); // Garante que nunca é negativo
$montante_total = round($horas * $preco_hora, 2); // Arredonda para duas casas decimais

// Preparação da Query SQL
// 'pendente' é o status inicial
$sql_booking = "INSERT INTO bookings (parent_id, sitter_id, data_inicio, data_fim, status_reserva, montante_total)
             VALUES (?, ?, ?, ?, 'pendente', ?)";

$stmt_booking = $conn->prepare($sql_booking);

if ($stmt_booking === false) { 
    send_error("Erro ao preparar a reserva (SQL): " . $conn->error, 500, $conn);
}

$data_inicio_db = $data_inicio->format('Y-m-d H:i:s');
$data_fim_db    = $data_fim->format('Y-m-d H:i:s');

// Tipos de Parâmetros: i (int), i (int), s (string), s (string), d (double/float)
$stmt_booking->bind_param(
    "iissd",
    $current_user_id,
    $sitter_id,
    $data_inicio_db,
    $data_fim_db,
    $montante_total
);

// Execução da Query
if ($stmt_booking->execute()) {
    $booking_id = $conn->insert_id;
    $stmt_booking->close();
    $conn->close(); // FECHO DE CONEXÃO FINAL E ÚNICO EM CASO DE SUCESSO
    
    // --------------------------------------------------------------
    // 5. RESPOSTA DE SUCESSO
    // --------------------------------------------------------------
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Reserva solicitada com sucesso! Aguardando confirmação.",
        "booking_id" => $booking_id
    ]);
    exit(); 
} else {
    // --------------------------------------------------------------
    // 6. RESPOSTA DE ERRO NA EXECUÇÃO
    // --------------------------------------------------------------
    $error = $stmt_booking->error;
    $stmt_booking->close();
    // Passamos $conn para a função de erro para que ela feche a conexão.
    send_error("Erro ao registar a reserva na base de dados: " . $error, 500, $conn); 
}
// Não há tag de fecho PHP para evitar bytes em branco.