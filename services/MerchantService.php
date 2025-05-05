<?php
/**
 * Merchant Service
 * Handles merchant management operations
 */
require_once __DIR__ . '/../config/database.php';

class MerchantService
{
    /**
     * Check if a merchant exists by API key
     * 
     * @param string $apikey The API key to check
     * @return bool Whether the merchant exists
     */
    public static function merchantExists($apikey)
    {
        $conn = getDbConnection();

        $sql = "SELECT apikey FROM merchant WHERE apikey = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $apikey);
        $stmt->execute();
        $stmt->store_result();

        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Add a new merchant
     * 
     * @param string $apikey Merchant API key
     * @param string $qris Merchant QRIS code
     * @param string $memberid Merchant member ID
     * @param string $apiid Merchant API ID
     * @return bool Whether the operation was successful
     */
    public static function addMerchant($apikey, $qris, $memberid, $apiid)
    {
        $conn = getDbConnection();

        $sql = "INSERT INTO merchant (apikey, qris, memberid, apiid) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $apikey, $qris, $memberid, $apiid);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Delete a merchant by API key
     * 
     * @param string $apikey The API key of the merchant to delete
     * @return bool Whether the operation was successful
     */
    public static function deleteMerchant($apikey)
    {
        $conn = getDbConnection();

        $sql = "DELETE FROM merchant WHERE apikey = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $apikey);

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Get merchant details by API key
     * 
     * @param string $apikey The API key to look up
     * @return array|null Merchant details or null if not found
     */
    public static function getMerchantDetails($apikey)
    {
        $conn = getDbConnection();

        $sql = "SELECT * FROM merchant WHERE apikey = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $apikey);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $merchant = $result->fetch_assoc();
        $stmt->close();

        return $merchant;
    }

    /**
     * Get merchant specific fields by API key
     * 
     * @param string $apikey The API key to look up
     * @param array $fields Array of field names to retrieve
     * @return array|null Selected merchant fields or null if not found
     */
    public static function getMerchantFields($apikey, $fields)
    {
        $conn = getDbConnection();

        $sql = "SELECT " . implode(", ", $fields) . " FROM merchant WHERE apikey = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $apikey);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $merchant = $result->fetch_assoc();
        $stmt->close();

        return $merchant;
    }
}