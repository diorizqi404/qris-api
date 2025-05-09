<?php

/**
 * API endpoint for checking payment status pending
 */
require_once __DIR__ . '../../config/config.php';
require_once __DIR__ . '../../services/ValidationService.php';
require_once __DIR__ . '../../services/PaymentService.php';

try {
    // Get parameters
    $apikey = $_GET['apikey'] ?? null;

    // Validate required parameters
    list($isValid, $errorMessage) = ValidationService::validateRequired([
        'apikey' => $apikey,
    ], ['apikey']);

    if (!$isValid) {
        echo json_encode([
            "status" => "error",
            "message" => $errorMessage
        ]);
        exit;
    }

    $checkPaymentPending = PaymentService::checkPaymentPending($apikey);
    if ($checkPaymentPending) {
        echo json_encode($checkPaymentPending);
        exit;
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to check transaction pending to database"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
