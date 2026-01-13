<?php
// database.php
function get_db_connection() {
    $servername = "localhost";
    $username = "root";
    $password = "nova_senha";
    $dbname = "babyhappy";
    $port = 3306; 
    
    // Desativar reporte de erros do MySQLi para não "sujar" o JSON
    mysqli_report(MYSQLI_REPORT_OFF);
    
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    
    if ($conn->connect_error) {
        error_log("❌ ERRO FATAL DE CONEXÃO BABYHAPPY: " . $conn->connect_error);
        return null; 
    }
    
    $conn->set_charset("utf8mb4"); 
    return $conn;
}