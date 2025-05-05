<?php

/**
 * API endpoint for adding a new merchant
 */
require_once __DIR__ . '../..//config/config.php';
require_once __DIR__ . '../../services/ValidationService.php';
require_once __DIR__ . '../../services/MerchantService.php';

try {
    // Get parameters
    $key = $_GET['key'] ?? null;
    $apikey = $_GET['apikey'] ?? null;
    $qris = $_GET['qris'] ?? null;
    $memberid = $_GET['memberid'] ?? null;
    $apiid = $_GET['apiid'] ?? strval($_GET['apiid'] ?? '');

    // Validate access key
    if (!ValidationService::validateMerchantAccessKey($key)) {
        echo json_encode([
            "status" => "error",
            "message" => "APIKey is missing or invalid"
        ]);
        exit;
    }

    // Validate required parameters
    list($isValid, $errorMessage) = ValidationService::validateRequired([
        'apikey' => $apikey,
        'qris' => $qris,
        'memberid' => $memberid,
        'apiid' => $apiid
    ], ['apikey', 'qris', 'memberid', 'apiid']);

    if (!$isValid) {
        echo json_encode([
            "status" => "error",
            "message" => $errorMessage
        ]);
        exit;
    }

    // Check if merchant already exists
    if (MerchantService::merchantExists($apikey)) {
        echo json_encode([
            "status" => "error",
            "message" => "APIKey Already Registered"
        ]);
        exit;
    }

    // Add merchant
    if (MerchantService::addMerchant($apikey, $qris, $memberid, $apiid)) {
        echo json_encode([
            "status" => "success",
            "apikey" => $apikey,
            "message" => "Success add merchant"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save data to database"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
