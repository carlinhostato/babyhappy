<?php
/* ==============================================================
   rating_handler.php
   • GET  → devolve JSON com as avaliações de um babysitter
   • POST → grava uma nova avaliação e devolve JSON de sucesso/erro (JSON Input)
   ============================================================== */

session_start();

/* ------------------- 1️⃣ INCLUIR CONEXÃO ------------------- */
require_once __DIR__ . '/../../../config/database.php';

try {
    $conn = get_db_connection();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['type' => 'error', 'text' => 'Erro na conexão com a base de dados.']);
    exit();
}

/* ------------------- 2️⃣ GARANTIR AUTENTICAÇÃO ------------------- */
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['type' => 'error', 'text' => 'Acesso negado – faça login.']);
    exit();
}

$avaliador_id = (int)$_SESSION['user_id'];   
header('Content-Type: application/json');

/* ==============================================================
   3️⃣  MÉTODO GET → LISTAR AVALIAÇÕES
   ============================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $sitter_id = filter_input(INPUT_GET, 'sitter_id', FILTER_VALIDATE_INT);
    
    // Permite que o ID seja passado como 'id' ou 'sitter_id'
    if (!$sitter_id) {
        $sitter_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    }

    if ($sitter_id === false || $sitter_id <= 0) {
        echo json_encode(['type' => 'error', 'text' => 'ID do babysitter inválido ou em falta.']);
        exit();
    }

    try {
        $sql = "
            SELECT
                r.rating,
                r.comentario,
                DATE_FORMAT(r.data_avaliacao, '%d-%m-%Y %H:%i') AS data_avaliacao,
                u.nome_completo AS avaliador_nome
            FROM ratings r
            JOIN users u ON r.avaliador_id = u.user_id
            WHERE r.recetor_id = ?
            ORDER BY r.data_avaliacao DESC
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Falha ao preparar a query GET: ' . $conn->error);
        }

        $stmt->bind_param('i', $sitter_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'ratings' => $list]);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['type' => 'error', 'text' => 'Erro ao buscar avaliações: ' . $e->getMessage()]);
        exit();
    }
}

/* ==============================================================
   4️⃣  MÉTODO POST → ADICIONAR AVALIAÇÃO (Lê JSON)
   ============================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ----- 4.1  RECEBER DADOS JSON -----
    $json_data = file_get_contents("php://input");
    $data = json_decode($json_data, true);
    
    if ($data === null) {
        echo json_encode(['type' => 'error', 'text' => 'Dados JSON inválidos ou em falta.']);
        exit();
    }

    // Mapeamento de campos do JS (rating_value e comment)
    $sitter_id = filter_var($data['sitter_id'] ?? null, FILTER_VALIDATE_INT);
    $rating = filter_var($data['rating_value'] ?? null, FILTER_VALIDATE_INT); 
    $comentario = trim($data['comment'] ?? '');
    $comentario = $comentario !== '' ? $comentario : null;

    // ----- 4.2  VALIDAÇÕES -----
    if ($sitter_id === false || $sitter_id <= 0) {
        echo json_encode(['type' => 'error', 'text' => 'ID do babysitter inválido.']);
        exit();
    }
    
    if ($rating === false || $rating < 1 || $rating > 5) {
        echo json_encode(['type' => 'error', 'text' => 'A nota deve estar entre 1 e 5 estrelas.']);
        exit();
    }
    
    if ($avaliador_id === $sitter_id) {
        echo json_encode(['type' => 'error', 'text' => 'Não pode avaliar a si mesmo.']);
        exit();
    }

    // ----- 4.3  VERIFICAR SE O BABYSITTER EXISTE -----
    try {
        $check_sql = "SELECT user_id, role FROM users WHERE user_id = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt === false) {
            throw new Exception('Falha ao preparar verificação: ' . $conn->error);
        }
        
        $check_stmt->bind_param('i', $sitter_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $sitter = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if (!$sitter) {
            echo json_encode(['type' => 'error', 'text' => 'Babysitter não encontrado.']);
            exit();
        }
        
        if ($sitter['role'] !== 'babysitter') {
            echo json_encode(['type' => 'error', 'text' => 'O utilizador selecionado não é um babysitter.']);
            exit();
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['type' => 'error', 'text' => 'Erro na verificação: ' . $e->getMessage()]);
        exit();
    }

    // ----- 4.4  VERIFICAR SE JÁ AVALIOU -----
    try {
        $dup_sql = "SELECT rating_id FROM ratings WHERE avaliador_id = ? AND recetor_id = ? LIMIT 1";
        $dup_stmt = $conn->prepare($dup_sql);
        
        if ($dup_stmt === false) {
            throw new Exception('Falha ao verificar duplicação: ' . $conn->error);
        }
        
        $dup_stmt->bind_param('ii', $avaliador_id, $sitter_id);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        $dup_stmt->close();
        
        if ($dup_result->num_rows > 0) {
            echo json_encode(['type' => 'error', 'text' => 'Você já avaliou este babysitter.']);
            exit();
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['type' => 'error', 'text' => 'Erro na verificação de duplicação: ' . $e->getMessage()]);
        exit();
    }

    // ----- 4.5  INSERT -----
    try {
        // Iniciar transação para garantir consistência
        $conn->begin_transaction();
        
        $sql = "
            INSERT INTO ratings
                (booking_id, avaliador_id, recetor_id, rating, comentario, data_avaliacao)
            VALUES (NULL, ?, ?, ?, ?, NOW())
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Falha ao preparar a query de inserção: ' . $conn->error);
        }

        $stmt->bind_param('iiis', $avaliador_id, $sitter_id, $rating, $comentario);

        if (!$stmt->execute()) {
            throw new Exception('Erro ao gravar avaliação: ' . $stmt->error);
        }
        
        $stmt->close();

        // ----- 4.6  ATUALIZAR MÉDIA DO BABYSITTER -----
        $sql_update_avg = "
            UPDATE users u
            SET u.average_rating = (
                SELECT AVG(r.rating) 
                FROM ratings r 
                WHERE r.recetor_id = u.user_id
            ),
            u.total_ratings = (
                SELECT COUNT(r.rating)
                FROM ratings r
                WHERE r.recetor_id = u.user_id
            )
            WHERE u.user_id = ? AND u.role = 'babysitter'
        ";
        
        $stmt_avg = $conn->prepare($sql_update_avg);
        if ($stmt_avg === false) {
            throw new Exception('Falha ao preparar atualização de média: ' . $conn->error);
        }
        
        $stmt_avg->bind_param('i', $sitter_id);
        
        if (!$stmt_avg->execute()) {
            throw new Exception('Erro ao atualizar média: ' . $stmt_avg->error);
        }
        
        $stmt_avg->close();
        
        // Confirmar transação
        $conn->commit();
        
        echo json_encode(['type' => 'success', 'text' => 'Avaliação enviada com sucesso!']);
        exit();
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode(['type' => 'error', 'text' => 'Erro ao processar avaliação: ' . $e->getMessage()]);
        exit();
    }
}

/* --------------------------------------------------------------
   Caso o método não seja GET nem POST → 405 Method Not Allowed
   -------------------------------------------------------------- */
http_response_code(405);
echo json_encode(['type' => 'error', 'text' => 'Método não permitido.']);
exit();