<?php
// database.php - CÓDIGO TEMPORÁRIO DE DEPURACÃO
function get_db_connection() {
    $servername = "localhost";
    $username = "carlos";
    $password = "admintato#10";
    $dbname = "babyhappy";
    $port = 3306; 
    
    // Tentativa de conexão
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    // VERIFICA SE FALHOU E DEVOLVE O ERRO DE LIGAÇÃO
    if ($conn->connect_error) {
        // Envia o erro de ligação diretamente para o log de erros do Apache/PHP
        error_log("❌ ERRO FATAL DE CONEXÃO BABYHAPPY: " . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4"); 
    return $conn;
}