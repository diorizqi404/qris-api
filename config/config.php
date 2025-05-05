<?php
/**
 * Configuration loader
 * Loads environment variables from .env file
 */

if (!function_exists('getenv_custom')) {
    function getenv_custom($key, $default = null) {
        static $env = null;
        
        if ($env === null) {
            $env = [];
            $envFile = dirname(__DIR__) . '/.env';
            
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                        list($envKey, $envValue) = explode('=', $line, 2);
                        $env[trim($envKey)] = trim($envValue);
                    }
                }
            }
        }
        
        return isset($env[$key]) ? $env[$key] : $default;
    }
}

// Set error reporting based on environment
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set common headers for API responses
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('Asia/Jakarta');