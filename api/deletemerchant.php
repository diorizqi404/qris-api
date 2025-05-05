<?php

/**
 * API endpoint for deleting a merchant
 */
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../services/ValidationService.php';
require_once __DIR__ . '../../services/MerchantService.php';

try {
    $key = $_GET['key'] ?? null;
    $apikey = $_GET['apikey'] ?? null;

    // Validate access key
    if (!ValidationService::validateDeleteAccessKey($key)) {
        echo json_encode([
            "status" => "error",
            "message" => "APIKey is missing or invalid"
        ]);
        exit;
    }

    // Validate required parameters
    list($isValid, $errorMessage) = ValidationService::validateRequired([
        'apikey' => $apikey
    ], ['apikey']);

    if (!$isValid) {
        echo json_encode([
            "status" => "error",
            "message" => $errorMessage
        ]);
        exit;
    }

    // Check if merchant exists
    if (!MerchantService::merchantExists($apikey)) {
        echo json_encode([
            "status" => "error",
            "message" => "APIKey not found"
        ]);
        exit;
    }

    // Delete merchant
    if (MerchantService::deleteMerchant($apikey)) {
        echo json_encode([
            "status" => "success",
            "message" => "Data with apikey '$apikey' has been successfully deleted"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to delete data from database"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
