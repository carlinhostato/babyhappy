<?php
// api/auth/logout.php
// Logout robusto: limpa sessão e remove cookie de sessão usando os parâmetros reais da sessão.

session_start();
header('Content-Type: application/json; charset=utf-8');

// Aceita GET e POST (ou outros se preferir)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método não permitido."]);
    exit();
}

// Limpar variáveis de sessão
$_SESSION = [];

// Remover cookie de sessão usando os mesmos parâmetros com que foi criado
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    // define cookie com tempo expirado usando os parâmetros atuais (path, domain, secure, httponly)
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? false
    );

    // Se quiser garantir SameSite (PHP < 7.3 não suporta array de options), pode enviar cabeçalho manual:
    // header('Set-Cookie: ' . session_name() . '=; Expires=' . gmdate('D, d M Y H:i:s T', time()-42000) . '; Path=' . ($params['path'] ?? '/') . '; Domain=' . ($params['domain'] ?? '') . '; SameSite=Lax; HttpOnly');
}

// Destruir sessão no servidor
session_destroy();

// Resposta JSON
http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Sessão terminada com sucesso."
]);