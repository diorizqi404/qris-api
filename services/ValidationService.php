<?php
/**
 * Validation Service
 * Handles input validation for all API endpoints
 */
require_once __DIR__ . '/../config/config.php';

class ValidationService {
    /**
     * Validate that required parameters are present
     * 
     * @param array $params Associative array of parameter names and values
     * @param array $required Array of required parameter names
     * @return array [isValid, errorMessage]
     */
    public static function validateRequired($params, $required) {
        $missing = [];
        
        foreach ($required as $param) {
            if (!isset($params[$param]) || $params[$param] === '') {
                $missing[] = $param;
            }
        }
        
        if (count($missing) > 0) {
            return [
                false, 
                "Parameter '" . implode("' '", $missing) . "' required"
            ];
        }
        
        return [true, null];
    }
    
    /**
     * Validate merchant access key
     * 
     * @param string $key The access key to validate
     * @return bool Whether the key is valid
     */
    public static function validateMerchantAccessKey($key) {
        $validKey = getenv_custom('ACCESS_KEY_MERCHANT', 'NoDev');
        return $key === $validKey;
    }

    /**
     * Validate merchant access key
     * 
     * @param string $key The access key to validate
     * @return bool Whether the key is valid
     */
    public static function validateApiKey($apikey) {
        $validKey = getenv_custom('ACCESS_KEY_MERCHANT', 'NoDev');
        return $apikey === $validKey;
    }
    
    /**
     * Validate delete merchant access key
     * 
     * @param string $key The access key to validate
     * @return bool Whether the key is valid
     */
    public static function validateDeleteAccessKey($key) {
        $validKey = getenv_custom('ACCESS_KEY_DELETE', 'NoDev');
        return $key === $validKey;
    }
    
    /**
     * Validate payment amount
     * 
     * @param int $amount The amount to validate
     * @return array [isValid, errorMessage]
     */
    public static function validateAmount($amount) {
        $amount = (int)$amount;
        
        if ($amount <= 0) {
            return [false, "Amount must be greater than 0 and a valid number"];
        }
        
        if ($amount < 100 || $amount > 10000000) {
            return [false, "Amount must be between 100 and 10,000,000"];
        }
        
        return [true, null];
    }
    
    /**
     * Validate valid time for payment
     * 
     * @param int $validtime The validtime to validate
     * @return array [isValid, errorMessage]
     */
    public static function validateValidTime($validtime) {
        if ($validtime <= 0) {
            return [false, "Validtime must be greater than 0"];
        }
        
        return [true, null];
    }
}