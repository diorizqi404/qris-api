<?php

/**
 * API endpoint for adding a new merchant
 */
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../services/ValidationService.php';
require_once __DIR__ . '../../services/PaymentService.php';

try {
    // Get parameters
    // $amount = $_GET['amount'] ?? null;
    $apikey = $_GET['apikey'] ?? null;
    $uniquecode = $_GET['uniquecode'] ?? 'null';
    // $validtime = $_GET['validtime'] ?? null;
    // $qris = $_GET['qris'] ?? null;
    // $memberid = $_GET['memberid'] ?? null;
    // $apiid = $_GET['apiid'] ?? strval($_GET['apiid'] ?? '');

    // Validate required parameters
    list($isValid, $errorMessage) = ValidationService::validateRequired([
        'apikey' => $apikey,
        "uniquecode" => $uniquecode
    ], ['apikey', 'uniquecode']);

    if (!$isValid) {
        echo json_encode([
            "status" => "error",
            "message" => $errorMessage
        ]);
        exit;
    }

    $checkPayment = PaymentService::checkPayment($apikey, $uniquecode);
    if ($checkPayment) {
        echo json_encode($checkPayment);
        exit;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to check transaction to database"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
