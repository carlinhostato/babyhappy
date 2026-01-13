<?php
// api/v1/auth/check_auth.php

// Inicia a sessão para aceder às variáveis de autenticação
session_start();

// Configuração básica do cabeçalho
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajuste se necessário

// Funções auxiliares (usadas para retornar respostas JSON)
function send_response($success, $message, $data = [], $http_status = 200) {
    http_response_code($http_status);
    echo json_encode([
        'success' => $success, 
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// 1. VERIFICAÇÃO DO MÉTODO HTTP
// Apenas aceita requisições GET para verificar o estado
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(false, 'Método não permitido.', [], 405);
}

// 2. VERIFICAÇÃO DO ESTADO DA SESSÃO
if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])) {
    
    // Sessão válida encontrada
    $auth_data = [
        'user_id' => $_SESSION["user_id"],
        'role' => $_SESSION["role"],
        'nome_completo' => $_SESSION["nome_completo"] ?? null
    ];
    
    // Retorna a informação de autenticação
    send_response(true, 'Utilizador autenticado.', $auth_data, 200);

} else {
    
    // Sessão inválida ou inexistente
    // Retorna um código 401 para o frontend saber que precisa redirecionar para o login
    send_response(false, 'Utilizador não autenticado.', [], 401);
}

// O ficheiro não tem a tag de fecho ?> para evitar vazamentos (boa prática em APIs).