<?php
/**
 * Database connection handler
 */
require_once __DIR__ . '/config.php';

function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $servername = getenv_custom('DB_HOST', 'localhost');
        $username = getenv_custom('DB_USER', 'apihubco_payhub');
        $password = getenv_custom('DB_PASS', 'apihubco_payhub');
        $dbname = getenv_custom('DB_NAME', 'apihubco_payhub');
        
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }
    }
    
    return $conn;
}