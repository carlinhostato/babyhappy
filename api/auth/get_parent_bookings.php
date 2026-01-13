<?php
// api/auth/get_parent_bookings.php - VERSÃO COMPLETA E REFORÇADA

session_start();
header('Content-Type: application/json');

// --------------------------------------------------------------
// 0. FUNÇÃO AUXILIAR DE ERRO (Replicada para robustez)
// --------------------------------------------------------------

function send_error(string $message, int $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}

// Incluir conexão DB e funções de erro
$database_path = __DIR__ . '/../../backend/config/database.php';

if (!file_exists($database_path)) {
    send_error("Erro de configuração: database.php não encontrado.", 500);
}
require_once $database_path;


// --------------------------------------------------------------
// 1. AUTENTICAÇÃO E CONEXÃO
// --------------------------------------------------------------

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    send_error("Não autorizado. Necessita de ser um utilizador logado e pai/mãe.", 401);
}

$parent_id = $_SESSION['user_id'];

try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados. Detalhes: " . $conn->connect_error, 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão (Exceção): " . $e->getMessage(), 500);
} 

// --------------------------------------------------------------
// 2. QUERY DE RESERVAS
// --------------------------------------------------------------

// A query está correta, fazendo JOIN na tabela 'users' para obter os dados do sitter.
$sql = "
    SELECT 
        b.booking_id,
        b.data_inicio,
        b.data_fim,
        b.status_reserva,
        b.montante_total,
        b.sitter_id, /* Incluir para referências futuras */
        u.nome_completo AS sitter_nome, 
        u.photo_url AS photo_url 
    FROM 
        bookings b
    JOIN 
        users u ON b.sitter_id = u.user_id
    WHERE 
        b.parent_id = ?
    ORDER BY 
        b.data_inicio DESC";

$bookings = [];

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    send_error("Erro ao preparar a consulta de reservas: " . $conn->error . ". Query: " . $sql, 500);
}

// Assumindo que parent_id é um inteiro (i)
$stmt->bind_param("i", $parent_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Se a execução falhar, irá para a função de erro.
    send_error("Erro ao executar a consulta de reservas: " . $stmt->error, 500);
}

$stmt->close();
$conn->close();

// --------------------------------------------------------------
// 4. RESPOSTA JSON
// --------------------------------------------------------------

http_response_code(200);
echo json_encode([
    "success" => true, 
    "bookings" => $bookings
]);

?>