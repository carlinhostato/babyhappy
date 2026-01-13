<?php
/**
 * api/auth/update_profile.php
 * Processa a atualização do perfil em duas tabelas: users e sitter_profiles.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// --- Função auxiliar de erro ---
function send_error(string $message, int $code = 500) {
    http_response_code($code);
    echo json_encode(["success" => false, "message" => $message]);
    exit();
}

// --- 1. AUTENTICAÇÃO E CONEXÃO ---

if (!isset($_SESSION["user_id"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    send_error('Acesso negado ou método inválido.', 401);
}

require_once __DIR__ . '/../../backend/config/database.php';

try {
    $conn = get_db_connection();
    if (!$conn || $conn->connect_error) {
        send_error("Erro ao conectar à base de dados", 500);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    send_error("Erro de conexão: " . $e->getMessage(), 500);
}

$user_id = $_SESSION["user_id"];

// --- 2. RECOLHA E VALIDAÇÃO DE DADOS --- 

$nome = trim($_POST['nome_completo'] ?? '');
$email = trim($_POST['email'] ?? '');
$localizacao = trim($_POST['localizacao'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$disponibilidade = trim($_POST['disponibilidade'] ?? '');
$experiencia = trim($_POST['experiencia'] ?? '');
$password = $_POST['password'] ?? '';

// Dados específicos da tabela sitter_profiles (Babysitter)
$preco = $_POST['preco_hora'] ?? 0;
$descricao = trim($_POST['descricao'] ?? '');

if (empty($nome) || empty($email)) {
    send_error("Nome e Email são obrigatórios.", 400);
}

// --- 3. TRATAMENTO DA FOTO DE PERFIL ---
$photo_url_new = null;

if (isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] === UPLOAD_ERR_OK) {
    // Caminho físico absoluto para o move_uploaded_file funcionar no XAMPP
    $upload_dir = __DIR__ . '/../../frontend/assets/images/'; 
    
    // Garante que a pasta existe
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_ext = strtolower(pathinfo($_FILES['new_photo']['name'], PATHINFO_EXTENSION));
    $file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['new_photo']['tmp_name'], $target_file)) {
        // CAMINHO PARA A DB: Guardamos sem "../" para o frontend resolver com o PROJECT_ROOT
        $photo_url_new = 'frontend/assets/images/' . $file_name;
    } else {
        send_error("Erro ao mover o ficheiro para a pasta de destino.");
    }
}

// --- 4. ATUALIZAÇÃO NAS DUAS TABELAS (TRANSAÇÃO) ---

$conn->begin_transaction();

try {
    // 4.1. Atualizar Tabela 'users'
    $fields_u = ["nome_completo = ?", "email = ?", "localizacao = ?", "phone = ?", "disponibilidade = ?", "experiencia = ?"];
    $params_u = [$nome, $email, $localizacao, $phone, $disponibilidade, $experiencia];
    $types_u = "ssssss";

    if (!empty($password)) {
        $fields_u[] = "password_hash = ?";
        $params_u[] = password_hash($password, PASSWORD_DEFAULT);
        $types_u .= "s";
    }
    
    if ($photo_url_new) {
        $fields_u[] = "photo_url = ?";
        $params_u[] = $photo_url_new;
        $types_u .= "s";
    }

    $sql_u = "UPDATE users SET " . implode(', ', $fields_u) . " WHERE user_id = ?";
    $params_u[] = $user_id;
    $types_u .= "i";

    $stmt_u = $conn->prepare($sql_u);
    $stmt_u->bind_param($types_u, ...$params_u);
    $stmt_u->execute();

    // 4.2. Atualizar ou Criar Tabela 'sitter_profiles' (apenas se os dados existirem)
    // Se o preço foi enviado, assumimos que é um perfil de babysitter ou atualização
    $sql_s = "INSERT INTO sitter_profiles (sitter_id, preco_hora, descricao) 
              VALUES (?, ?, ?) 
              ON DUPLICATE KEY UPDATE 
                preco_hora = VALUES(preco_hora), 
                descricao = VALUES(descricao)";
    
    $stmt_s = $conn->prepare($sql_s);
    $stmt_s->bind_param("ids", $user_id, $preco, $descricao);
    $stmt_s->execute();

    // Confirmar alterações
    $conn->commit();
    
    // Atualizar dados na sessão para refletir no header/perfil imediatamente
    $_SESSION['nome_completo'] = $nome;
    if ($photo_url_new) {
        $_SESSION['photo_url'] = '/babyhappy_v1/' . $photo_url_new;
    }

    echo json_encode([
        "success" => true, 
        "message" => "Dados atualizados com sucesso!",
        "new_photo_url" => $photo_url_new ? '/babyhappy_v1/' . $photo_url_new : null
    ]);

} catch (Exception $e) {
    $conn->rollback();
    send_error("Erro crítico ao guardar dados: " . $e->getMessage());
} finally {
    if (isset($conn)) $conn->close();
}