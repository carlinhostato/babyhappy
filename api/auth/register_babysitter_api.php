<?php
// Limpa qualquer output anterior
ob_clean();
ob_start();

// Ativar erros temporariamente para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para JSON e CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder requisições OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log de debug
file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " - Script iniciado\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "Método: " . $_SERVER["REQUEST_METHOD"] . "\n", FILE_APPEND);

// Verifica o método da requisição
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit();
}

file_put_contents(__DIR__ . '/debug.log', "Método POST aceito\n", FILE_APPEND);

// Conexão com o Banco de Dados - CORRIGIDO: apenas 2 níveis acima
$dbPath = __DIR__ . '/../../backend/config/database.php';
file_put_contents(__DIR__ . '/debug.log', "Caminho DB: $dbPath\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "Caminho real: " . realpath($dbPath) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "Arquivo existe? " . (file_exists($dbPath) ? 'SIM' : 'NÃO') . "\n", FILE_APPEND);

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Arquivo database.php não encontrado no caminho: $dbPath"]);
    exit();
}

require_once $dbPath;
file_put_contents(__DIR__ . '/debug.log', "database.php carregado\n", FILE_APPEND);

// Tenta conectar ao banco
try {
    $conn = get_db_connection();
    file_put_contents(__DIR__ . '/debug.log', "Conexão BD OK\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/debug.log', "Erro conexão: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao conectar ao banco de dados."]);
    exit();
}

// Capturar os dados enviados via JSON
$rawInput = file_get_contents("php://input");
file_put_contents(__DIR__ . '/debug.log', "Input raw: $rawInput\n", FILE_APPEND);

$data = json_decode($rawInput, true);
file_put_contents(__DIR__ . '/debug.log', "Data decoded: " . print_r($data, true) . "\n", FILE_APPEND);

// Se o JSON é inválido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "JSON inválido: " . json_last_error_msg()]);
    exit();
}

$nome_completo = trim($data["nome_completo"] ?? ""); 
$email = trim($data["email"] ?? "");
$senha_raw = $data["password"] ?? "";
$localizacao = trim($data["localizacao"] ?? "");
$role = trim($data["role"] ?? "");

// Validação
if ($nome_completo === "" || $email === "" || $senha_raw === "" || $role === "") {
    file_put_contents(__DIR__ . '/debug.log', "Validação falhou - campos vazios\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Preencha todos os campos obrigatórios (nome, email, senha, role)."]);
    exit();
}

file_put_contents(__DIR__ . '/debug.log', "Validação OK\n", FILE_APPEND);

$senha = password_hash($senha_raw, PASSWORD_DEFAULT);

// Verifica se o email já existe
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
if (!$check) {
    file_put_contents(__DIR__ . '/debug.log', "Erro prepare SELECT: " . $conn->error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao verificar email."]);
    exit();
}

$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    file_put_contents(__DIR__ . '/debug.log', "Email já existe: $email\n", FILE_APPEND);
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "Este email já está registado."]);
    exit();
}

file_put_contents(__DIR__ . '/debug.log', "Email disponível\n", FILE_APPEND);

// Insere novo usuário
$stmt = $conn->prepare("INSERT INTO users (nome_completo, email, password_hash, role, localizacao) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    file_put_contents(__DIR__ . '/debug.log', "Erro prepare INSERT: " . $conn->error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao preparar inserção."]);
    exit();
}

$stmt->bind_param("sssss", $nome_completo, $email, $senha, $role, $localizacao);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;
    file_put_contents(__DIR__ . '/debug.log', "INSERT OK - ID: $user_id\n", FILE_APPEND);
    
    http_response_code(201);
    echo json_encode([
        "success" => true, 
        "message" => "Registo bem-sucedido.",
        "user_id" => $user_id,
        "role" => $role
    ]);
} else {
    file_put_contents(__DIR__ . '/debug.log', "Erro INSERT: " . $stmt->error . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Erro ao criar conta: " . $stmt->error]);
}

$stmt->close();
$conn->close();

// Envia o buffer e termina
ob_end_flush();

file_put_contents(__DIR__ . '/debug.log', "Script finalizado com sucesso\n\n", FILE_APPEND);