<?php
// api/auth/welcome_init.php

session_start();
header('Content-Type: application/json');

// 1. VERIFICAÇÃO DO MÉTODO HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método não permitido. Use GET."]);
    exit();
}

// 2. LÓGICA DE INICIALIZAÇÃO
// Verifica se há um utilizador logado (embora este ficheiro seja para novos/deslogados)
$is_authenticated = isset($_SESSION['user_id']);
$session_id = session_id();

// Aqui você pode adicionar lógica, como limpar variáveis de sessão antigas,
// ou verificar se o utilizador já escolheu um papel anteriormente.

// 3. RESPOSTA DE SUCESSO
http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Sessão inicializada.",
    "session_id" => $session_id,
    "is_authenticated" => $is_authenticated
]);

// O ficheiro não tem a tag de fecho ?>