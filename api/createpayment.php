<?php

/**
 * API endpoint for adding a new merchant
 */
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../services/ValidationService.php';
require_once __DIR__ . '../../services/PaymentService.php';

try {
    // Get parameters
    $amount = $_GET['amount'] ?? null;
    $apikey = $_GET['apikey'] ?? null;
    $validtime = $_GET['validtime'] ?? null;
    $uniquecode = $_GET['uniquecode'] ?? null;
    // $memberid = $_GET['memberid'] ?? null;
    // $apiid = $_GET['apiid'] ?? strval($_GET['apiid'] ?? '');

    // Validate required parameters
    list($isValid, $errorMessage) = ValidationService::validateRequired([
        'apikey' => $apikey,
        'amount' => $amount,
    ], ['apikey', 'amount']);

    if (!$isValid) {
        echo json_encode([
            "status" => "error",
            "message" => $errorMessage
        ]);
        exit;
    }

    $createPayment = PaymentService::createPayment($apikey, $amount, $uniquecode, $validtime);
    if ($createPayment) {
        echo json_encode($createPayment);
        exit;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to save transaction to database"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
