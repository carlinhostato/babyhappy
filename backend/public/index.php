<?php

// ------------------------------------------------
// 1. Configuração Inicial e Definição de Caminhos
// ------------------------------------------------

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Lisbon');

// Define o caminho absoluto para a pasta raiz do projeto.
// O seu index.php está dentro de public/, então voltamos um nível.
define('ROOT_PATH', dirname(__DIR__) . '/'); // ALTERAÇÃO AQUI: dirname(__DIR__) sobe para a raiz do projeto

// ------------------------------------------------
// 2. Autoloading
// ------------------------------------------------
// Carrega todas as classes e dependências via Composer
require ROOT_PATH . 'vendor/autoload.php';


// ------------------------------------------------
// 3. Configurações da Aplicação
// ------------------------------------------------
// Carrega configurações, incluindo a BASE_URL
$config = require ROOT_PATH . 'config/app.php';
define('BASE_URL', $config['BASE_URL']);


// ------------------------------------------------
// 4. Lógica de Roteamento (Router Central)
// ------------------------------------------------

// Obtém o URI da requisição
$request_uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$uri_parts = explode('/', $request_uri);

// Calcula o segmento de caminho, ignorando a BASE_URL (se existir)
$base_parts_count = count(explode('/', trim(BASE_URL, '/')));
$path = array_slice($uri_parts, $base_parts_count);

// Define o Controller e o Método
$controller_segment = !empty($path[0]) ? $path[0] : 'Search'; 
$method_segment = !empty($path[1]) ? $path[1] : 'index';

// ------------------------------------------------
// 🚀 ALTERAÇÃO CHAVE: ROTEAMENTO DE API
// ------------------------------------------------

if (strtolower($controller_segment) === 'api') {
    // Se a requisição começa com /api, vamos buscar o ficheiro PHP diretamente 
    // na pasta 'api/' em vez de um Controller.

    // Ex: /babyhappy_v1/api/auth/make_booking.php
    // O $path restante é [api, auth, make_booking.php]
    
    // Removemos o 'api' para formar o caminho relativo
    $api_path_segments = array_slice($path, 1);
    $api_file_name = implode('/', $api_path_segments);
    
    // Caminho completo para o ficheiro PHP da API (ex: ROOT_PATH/api/auth/make_booking.php)
    $api_file = ROOT_PATH . 'api/' . $api_file_name;

    if (file_exists($api_file)) {
        // Inclui o ficheiro da API, que tratará da requisição (POST, GET, etc.)
        require $api_file;
        exit; // Termina o script aqui, pois a API já respondeu
    } else {
        // 404 para ficheiros de API inexistentes
        http_response_code(404);
        die("<h1>Erro 404: API Endpoint não encontrado.</h1><p>Ficheiro API '{$api_file_name}' inexistente.</p>");
    }
}

// ------------------------------------------------
// 5. Execução do Controller Padrão (Se não for API)
// ------------------------------------------------

// Formata nomes das classes e métodos
$controller_name = ucfirst(strtolower($controller_segment)) . 'Controller'; 
$method_name = strtolower($method_segment);

// Define o namespace completo e o caminho do ficheiro
$controller_class = 'BabbyHappy\\Controllers\\' . $controller_name;
$controller_file = ROOT_PATH . 'src/Controllers/' . $controller_name . '.php';


if (!file_exists($controller_file)) {
    // 404: O Ficheiro Controller não existe
    http_response_code(404);
    die("<h1>Erro 404: Página não encontrada.</h1><p>Controller '{$controller_name}' inexistente.</p>"); 
}

require_once $controller_file;

if (class_exists($controller_class)) {
    $controller = new $controller_class();
    
    if (method_exists($controller, $method_name)) {
        // Executa o método no Controller
        $controller->$method_name(); 
    } else {
        // 404: O método dentro do Controller não existe
        http_response_code(404);
        die("<h1>Erro 404: Método não encontrado.</h1><p>O método '{$method_name}' não existe no Controller '{$controller_name}'.</p>");
    }
} else {
    // 500: Ficheiro existe, mas a classe não (Erro de código/Namespace)
    http_response_code(500);
    die("Erro 500: Classe Controller não encontrada no ficheiro.");
}