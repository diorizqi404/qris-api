<?php

/**
 * Payment Service
 * Handles payment processing operations
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MerchantService.php';
require_once __DIR__ . '/QrisService.php';

class PaymentService
{
    /**
     * Create a new payment transaction
     * 
     * @param string $apikey Merchant API key
     * @param int $amount Payment amount
     * @param string $uniquecode Unique transaction code
     * @param int $validtime Time until expiration in seconds
     * @return array Transaction details or error
     */
    public static function createPayment($apikey, $amount, $uniquecode)
    {
        $conn = getDbConnection();

        // Check if merchant exists
        if (!MerchantService::merchantExists($apikey)) {
            return ['status' => 'error', 'message' => 'APIKey is missing or invalid'];
        }

        // Generate unique code if not provided
        if ($uniquecode == null) {
            $uniquecode = uniqid();
        }

        // Calculate expiration time
        $expiredTime = date('Y-m-d H:i:s', time() + 1440 * 60); // 24 hours

        // Set all transaction status to expired
        self::checkAllTransactionExpiry();

        // Check if amount is valid
        if (!is_numeric($amount) || $amount < 1000) {
            return ['status' => 'error', 'message' => 'Minimum amount is 1.000'];
        }

        // Check for recent transactions with same amount, within 5 minutes, and status 'pending'
        $stmt = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE invoice = ? AND created_at >= NOW() - INTERVAL 1440 MINUTE AND status = 'pending'");
        $stmt->bind_param("i", $amount);
        $stmt->execute();
        $stmt->bind_result($trxCount);
        $stmt->fetch();
        $stmt->close();

        // Adjust invoice amount if needed to avoid duplicates
        $fee = ($trxCount >= 1) ? $trxCount : 0;
        $fee += $amount * 0.007; // Combine rate into fee
        $invoice = $amount + $fee;

        // Generate QRIS code with amount
        $qrisCode = QrisService::generateQrisWithAmount($apikey, $invoice);

        if (!$qrisCode) {
            return ['status' => 'error', 'message' => 'Failed to generate QRIS code'];
        }

        // Generate QR code image
        try {
            $qrisImageUrl = QrisService::generateQrisImage($qrisCode);

            if (!$qrisImageUrl) {
                return ['status' => 'error', 'message' => 'Failed to generate QR code image'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Save transaction to database
        $stmt = $conn->prepare("INSERT INTO transactions (apikey, uniquecode, invoice, fee, expired) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return ['status' => 'error', 'message' => 'Database prepare failed: ' . $conn->error];
        }

        $stmt->bind_param("ssiis", $apikey, $uniquecode, $amount, $fee, $expiredTime);

        if (!$stmt->execute()) {
            $stmt->close();
            return ['status' => 'error', 'message' => 'Failed to save transaction to the database'];
        }

        $stmt->close();

        // Return success response
        return [
            'status' => 'success',
            'code' => 200,
            'data' => [
                'amount' => $amount,
                'fee' => $fee,
                'uniquecode' => $uniquecode,
                'invoice' => $invoice,
                'qris' => $qrisImageUrl,
                'expired' => $expiredTime
            ]
        ];
    }

    /**
     * Check all transaction expiry and update status to 'failed'
     */
    public static function checkAllTransactionExpiry()
    {
        $conn = getDbConnection();

        $stmt = $conn->prepare("SELECT id, expired FROM transactions WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $expiredTimestamp = strtotime($row['expired']);
            if ($expiredTimestamp < time()) {
                $updateStmt = $conn->prepare("UPDATE transactions SET status = 'failed' WHERE id = ?");
                $updateStmt->bind_param("i", $row['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        $stmt->close();
    }

    /**
     * Check transaction expiry and invoice
     * 
     * @param string $apikey Merchant API key
     * @param string $uniquecode Unique transaction code
     * @return array|null Transaction details or null if not found
     */
    public static function checkTransactionExpiryAndInvoice($apikey, $uniquecode)
    {
        $conn = getDbConnection();

        $stmt = $conn->prepare("SELECT expired, invoice, status FROM transactions WHERE apikey = ? AND uniquecode = ?");
        $stmt->bind_param("ss", $apikey, $uniquecode);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($expired, $invoice, $status);
            $stmt->fetch();
            $stmt->close();

            if ($status === 'success') {
                return [
                    'expired' => false,
                    'invoice' => $invoice
                ];
            } else {
                $expiredTimestamp = strtotime($expired);

                // Set all transaction status to expired
                self::checkAllTransactionExpiry();

                return [
                    'expired' => ($expiredTimestamp < time()),
                    'invoice' => $invoice
                ];
            }
        }

        $stmt->close();
        return null;
    }

    /**
     * Check payment status for a transaction
     * 
     * @param string $apikey Merchant API key
     * @param string $uniquecode Unique transaction code
     * @return array Payment status and details
     */
    public static function checkPayment($apikey, $uniquecode)
    {
        // Check if transaction exists and not expired
        $transaction = self::checkTransactionExpiryAndInvoice($apikey, $uniquecode);

        if ($transaction === null) {
            return [
                'status' => 'not_found',
                'message' => 'Transaction not found'
            ];
        }

        if ($transaction['expired']) {
            return [
                'status' => 'expired',
                'message' => 'Transaction has expired. Please repeat this transaction again'
            ];
        }

        // Get merchant QRIS payment info
        $merchant = MerchantService::getMerchantFields($apikey, ['memberid', 'apiid']);

        if (!$merchant) {
            return ['status' => 'error', 'message' => 'APIKey is missing or invalid'];
        }

        // Check payment using OkeConnect API
        $paymentData = self::checkOkeConnectPayment($merchant['memberid'], $merchant['apiid'], $uniquecode);

        // Update transaction status in the database if payment is successful
        if ($paymentData['status'] === 'success') {
            $conn = getDbConnection();
            $updateStmt = $conn->prepare("UPDATE transactions SET status = 'success' WHERE apikey = ? AND uniquecode = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ss", $apikey, $uniquecode);
                $updateStmt->execute();
                $updateStmt->close();
            }

            $paymentData['data']['status_updated'] = true;
        }

        return $paymentData;
    }

    /**
     * Check payment status with OkeConnect API
     * 
     * @param string $memberid Member ID for OkeConnect
     * @param string $apiid API ID for OkeConnect
     * @param int $invoice Invoice amount to check
     * @return array Payment status and details
     */
    private static function checkOkeConnectPayment($memberid, $apiid, $uniquecode)
    {
        $conn = getDbConnection();

        // Fetch transaction details from the database using uniquecode
        $stmt = $conn->prepare("SELECT invoice, fee, created_at, expired FROM transactions WHERE uniquecode = ?");
        $stmt->bind_param("s", $uniquecode);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            return [
                'status' => 'not_found',
                'code' => 404,
                'message' => 'Transaction not found'
            ];
        }

        $stmt->bind_result($invoice, $fee, $createdAt, $expired);
        $stmt->fetch();
        $stmt->close();

        $totalAmount = $invoice + $fee;
        $createdTimestamp = strtotime($createdAt);
        $expiredTimestamp = strtotime($expired);

        $requestTime = time();
        $requestTimeFormatted = date('Y-m-d H:i:s', $requestTime);

        // Make API request to OkeConnect
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://gateway.okeconnect.com/api/mutasi/qris/$memberid/$apiid",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            curl_close($curl);
            return [
                'status' => 'failed',
                'code' => 500,
                'message' => 'cURL Error: ' . curl_error($curl)
            ];
        }

        curl_close($curl);
        $decodedResponse = json_decode($response, true);

        if (
            is_array($decodedResponse) && isset($decodedResponse['status'])
            && $decodedResponse['status'] === 'success'
        ) {
            if (isset($decodedResponse['data']) && is_array($decodedResponse['data'])) {
                $filteredData = array_filter($decodedResponse['data'], function ($item) use ($totalAmount, $createdTimestamp, $expiredTimestamp) {
                    $transactionTime = strtotime($item['date']);
                    return isset($item['amount']) && $item['amount'] == $totalAmount
                        && $transactionTime >= $createdTimestamp
                        && $transactionTime <= $expiredTimestamp;
                });

                if (!empty($filteredData)) {
                    $closestTransaction = null;
                    $smallestTimeDifference = PHP_INT_MAX;

                    foreach ($filteredData as $transaction) {
                        $transactionTime = strtotime($transaction['date']);
                        $timeDifference = abs($requestTime - $transactionTime);

                        if ($timeDifference < $smallestTimeDifference) {
                            $smallestTimeDifference = $timeDifference;
                            $closestTransaction = $transaction;
                        }
                    }

                    if ($closestTransaction) {
                        return [
                            'status' => 'success',
                            'code' => 200,
                            'request_time' => $requestTimeFormatted,
                            'data' => [
                                'message' => 'Transaction Success',
                                'date' => $closestTransaction['date'] ?? null,
                                'amount' => $closestTransaction['amount'] ?? null,
                                'brand' => $closestTransaction['brand_name'] ?? null,
                                'name' => trim($closestTransaction['buyer_reff'] ?? 'N/A'),
                                'balance' => $closestTransaction['balance'] ?? null
                            ]
                        ];
                    }
                }

                return [
                    'status' => 'unpaid',
                    'code' => 404,
                    'request_time' => $requestTimeFormatted,
                    'message' => 'There is no data maybe the buyer hasnt transferred or hasnt paid'
                ];
            }
        } else if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'failed') {
            return [
                'status' => 'failed',
                'code' => 500,
                'message' => 'Invalid credential'
            ];
        }

        // biasanya ketika okeconnect tidak memiliki transaksi (kosong)
        return [
            'status' => 'unpaid',
            'code' => 404,
            'message' => 'There is no data maybe the buyer hasnt transferred or hasnt paid',
        ];
    }
}
