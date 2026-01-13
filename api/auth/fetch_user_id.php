<?php
// api/auth/fetch_user_id.php

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'user_id' => 0
];

// LÓGICA REAL DE SESSÃO
if (isset($_SESSION['user_id'])) {
    $response['success'] = true;
    $response['user_id'] = (int)$_SESSION['user_id'];
    $response['user_name'] = $_SESSION['nome_completo'] ?? 'Utilizador';
    $response['role'] = $_SESSION['role'] ?? '';
    
    // Tratamento da URL da foto para evitar duplicação de pastas
    if (isset($_SESSION['photo_url']) && !empty($_SESSION['photo_url'])) {
        $photo = $_SESSION['photo_url'];
        // Se o caminho já tiver o prefixo do projeto, garantimos que começa com /
        if (strpos($photo, '/babyhappy_v1/') === false && strpos($photo, 'http') === false) {
            $photo = '/babyhappy_v1/' . ltrim($photo, '/');
        }
        $response['photo_url'] = $photo;
    } else {
        $response['photo_url'] = '/babyhappy_v1/frontend/assets/images/default_profile.png';
    }
} else {
    $response['message'] = 'Nenhum utilizador autenticado.';
}

echo json_encode($response);