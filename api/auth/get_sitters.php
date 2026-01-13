<?php
// api/auth/get_sitters.php - VERSÃO CORRIGIDA E FINAL

session_start();
header('Content-Type: application/json');

$PROJECT_ROOT = '/babyhappy_v1/'; 

// --------------------------------------------------------------
// 0. FUNÇÃO AUXILIAR DE ERRO
// --------------------------------------------------------------

function send_error(string $message, int $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
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

try {
    $conn = get_db_connection();
    
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados. Detalhes: " . $conn->connect_error, 500);
    }
} catch (Exception $e) {
    send_error("Erro de conexão (Exceção): " . $e->getMessage(), 500);
} 


// --------------------------------------------------------------
// 2. AUTENTICAÇÃO E VERIFICAÇÃO DE UTILIZADOR
// --------------------------------------------------------------

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    send_error("Não autorizado. Necessita de ser um utilizador logado e pai/mãe para pesquisar.", 401);
}
$current_user_id = $_SESSION['user_id'];

// --------------------------------------------------------------
// 3. FUNÇÕES AUXILIARES DE LÓGICA (Proximidade)
// --------------------------------------------------------------

function calculateProximity(string $user_location, string $sitter_location): string
{
    $user_loc   = strtolower(trim($user_location));
    $sitter_loc = strtolower(trim($sitter_location));

    if ($user_loc === $sitter_loc && !empty($user_loc)) {
        return 'Muito Próximo';
    }

    $user_city   = explode(',', $user_loc)[0];
    $sitter_city = explode(',', $sitter_loc)[0];
    $user_city   = strtolower(trim($user_city));
    $sitter_city = strtolower(trim($sitter_city));

    if ($user_city === $sitter_city && !empty($user_city)) {
        return 'Próximo';
    }

    $porto_proximidade = [
        'porto'              => ['vila nova de gaia', 'maia', 'valongo', 'gondomar', 'paredes', 'vilarreal'],
        'vila nova de gaia'  => ['porto', 'maia', 'aveiro', 'espinho'],
        'maia'               => ['porto', 'vila nova de gaia', 'valongo', 'paredes'],
        'aveiro'             => ['vila nova de gaia', 'espinho', 'santa maria da feira'],
        'braga'              => ['guimarães', 'famalicão', 'barcelos'],
    ];

    foreach ($porto_proximidade as $city => $nearby) {
        if (($user_city === $city && in_array($sitter_city, $nearby, true)) ||
            ($sitter_city === $city && in_array($user_city, $nearby, true))) {
            return 'Moderado';
        }
    }

    return 'Longe';
}
// --------------------------------------------------------------
// 4. PREPARAÇÃO DA PESQUISA E FILTROS
// --------------------------------------------------------------

$search_query           = $_GET['query'] ?? '';
$filter_proximidade     = $_GET['proximidade'] ?? '';
$filter_disponibilidade = $_GET['disponibilidade'] ?? '';
$filter_experiencia     = $_GET['experiencia'] ?? '';

// 4.1: Obter localização do pai/mãe
$user_location = '';
if (isset($conn)) {
    $user_location_query = "SELECT localizacao FROM users WHERE user_id = ?";
    $user_location_stmt  = $conn->prepare($user_location_query);
    
    if ($user_location_stmt) {
        $user_location_stmt->bind_param("i", $current_user_id);
        $user_location_stmt->execute();
        $user_location_res = $user_location_stmt->get_result();
        $user_loc_data     = $user_location_res->fetch_assoc();
        $user_location     = $user_loc_data['localizacao'] ?? '';
        $user_location_stmt->close();
    }
}

// 4.2: CONSTRUÇÃO DA QUERY COM FILTROS DINÂMICOS

$conditions = ["u.role = 'babysitter'"]; // CONDIÇÃO OBRIGATÓRIA
$params = [];
$types  = '';

// FILTRO DE PESQUISA (Nome e Localização)
if (!empty($search_query)) {
    $search_query_like = '%' . $search_query . '%';
    $conditions[] = "(u.nome_completo LIKE ? OR u.localizacao LIKE ?)";
    $params[] = $search_query_like;
    $params[] = $search_query_like;
    $types  .= 'ss';
}

// FILTRO DE DISPONIBILIDADE (Não usa LIKE, deve ser um valor exato, se enviado)
if (!empty($filter_disponibilidade)) {
    $conditions[] = "u.disponibilidade = ?";
    $params[] = $filter_disponibilidade;
    $types  .= 's';
}

// FILTRO DE EXPERIÊNCIA (Não usa LIKE, deve ser um valor exato, se enviado)
if (!empty($filter_experiencia)) {
    $conditions[] = "u.experiencia = ?";
    $params[] = $filter_experiencia;
    $types  .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $conditions);


// 4.3: Query principal
$sql = "SELECT
            u.user_id,
            u.nome_completo,
            u.localizacao,
            u.photo_url,
            u.disponibilidade,
            u.experiencia,
            s.preco_hora,
            s.descricao,
            (SELECT AVG(rating) FROM ratings WHERE recetor_id = u.user_id) AS media_avaliacao
        FROM users u
        LEFT JOIN sitter_profiles s ON u.user_id = s.sitter_id
        {$where_clause}
        ORDER BY u.disponibilidade DESC, media_avaliacao DESC, u.nome_completo ASC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    send_error("Erro ao preparar a consulta de Babysitters: " . $conn->error . " Query: " . $sql, 500);
}

// O bind_param só é chamado se houver parâmetros (ou seja, se houver filtros aplicados)
if (!empty($params)) {
    // Usamos o operador splat (...) para desempacotar o array $params
    $stmt->bind_param($types, ...$params); 
}

$stmt->execute();
$result       = $stmt->get_result();
$all_sitters = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --------------------------------------------------------------
// 5. PÓS-PROCESSAMENTO E FILTRAGEM DE PROXIMIDADE (PHP)
// --------------------------------------------------------------

$DEFAULT_PHOTO_URL = "{$PROJECT_ROOT}frontend/assets/images/default_profile.png";

$sitters = [];
foreach ($all_sitters as $s) {
    

    $prox = calculateProximity($user_location, $s['localizacao'] ?? '');
    $s['proximidade_calculada'] = $prox;
    

    if (!empty($filter_proximidade) && $prox !== $filter_proximidade) {
        continue;
    }



    if (empty($s['photo_url'])) {
        $s['photo_url'] = $DEFAULT_PHOTO_URL;
    } 

    $raw_preco = $s['preco_hora'] ?? 0;
    $s['preco_hora'] = is_string($raw_preco)
        ? (float) str_replace(',', '.', $raw_preco)
        : (float) $raw_preco;

    $sitters[] = $s;
}

// --------------------------------------------------------------
// 6. RESPOSTA FINAL JSON
// --------------------------------------------------------------

http_response_code(200);
echo json_encode([
    "success" => true, 
    "sitters" => $sitters, 
    "user_location" => $user_location 
]);

if ($conn) {
    $conn->close();
}
?>