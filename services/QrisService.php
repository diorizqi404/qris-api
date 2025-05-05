<?php
/**
 * QRIS Service
 * Handles QRIS code generation and conversion
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/MerchantService.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

// Require QR library if using it
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class QrisService {
    /**
     * Convert CRC16 for QRIS code
     * 
     * @param string $str String to convert
     * @return string Converted CRC16 string
     */
    public static function convertCRC16($str) {
        $crc = 0xFFFF;
        $strlen = strlen($str);
        
        for($c = 0; $c < $strlen; $c++) {
            $crc ^= ord(substr($str, $c, 1)) << 8;
            for($i = 0; $i < 8; $i++) {
                if($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
            }
        }
        
        $hex = $crc & 0xFFFF;
        $hex = strtoupper(dechex($hex));
        
        if (strlen($hex) == 3) {
            $hex = "0" . $hex;
        }
        
        return $hex;
    }
    
    /**
     * Handle QRIS conversion with an amount
     * 
     * @param string $qris Original QRIS code
     * @param string $amount Amount to add to QRIS
     * @return string Modified QRIS code
     */
    public static function handleQrisConversion($qris, $amount) {
        $qris = substr($qris, 0, -4);
        $step1 = str_replace("010211", "010212", $qris);
        $step2 = explode("5802ID", $step1);

        $uang = "54" . sprintf("%02d", strlen($amount)) . $amount;
        $uang .= "5802ID";

        $fix = trim($step2[0]) . $uang . trim($step2[1]);
        $fix .= self::convertCRC16($fix);

        return $fix;
    }
    
    /**
     * Generate a QRIS code with amount for a merchant
     * 
     * @param string $apikey Merchant API key
     * @param string $amount Amount to add to QRIS
     * @return string|null Modified QRIS code or null on failure
     */
    public static function generateQrisWithAmount($apikey, $amount) {
        // Get merchant's original QRIS code
        $merchant = MerchantService::getMerchantFields($apikey, ['qris']);
        
        if (!$merchant || !isset($merchant['qris'])) {
            return null;
        }
        
        return self::handleQrisConversion($merchant['qris'], $amount);
    }
    
    /**
     * Generate QR code image and upload to ImgBB
     * 
     * @param string $qrisCode QRIS code text
     * @return string|bool Image URL or false on failure
     */
    public static function generateQrisImage($qrisCode) {
        if (!class_exists('\chillerlan\QRCode\QRCode')) {
            throw new Exception("QR Code library not available");
        }
        
        $options = new \chillerlan\QRCode\QROptions([
            'version' => 10,
            'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L,
            'imageBase64' => false,
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'scale' => 10,
            'imageTransparent' => false,
        ]);

        $qrcode = new \chillerlan\QRCode\QRCode($options);
        $qrcodeImage = $qrcode->render($qrisCode);

        return self::uploadToCloudinary($qrcodeImage);
    }

    /**
     * Initialize Cloudinary configuration
     */
    private static function initCloudinary() {
        $cloudName = getenv_custom('CLOUDINARY_CLOUD_NAME', 'your_cloud_name');
        $apiKey = getenv_custom('CLOUDINARY_API_KEY', 'your_api_key');
        $apiSecret = getenv_custom('CLOUDINARY_API_SECRET', 'your_api_secret');

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    private static function uploadToCloudinary($imageData) {
        try {
            self::initCloudinary();
            $tempFile = tempnam(sys_get_temp_dir(), 'qris_');
            file_put_contents($tempFile, $imageData);

            $uploadApi = new UploadApi();
            $result = $uploadApi->upload($tempFile, [
                'folder' => 'qris',
                'overwrite' => true,
                'resource_type' => 'image',
            ]);
            unlink($tempFile); // Clean up the temporary file
            return $result['secure_url'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    
    /**
     * Upload an image to ImgBB
     * 
     * @param string $imageData Raw image data
     * @return string|bool Image URL or false on failure
     */
    // private static function uploadToImgBB($imageData) {
    //     $apiKey = getenv_custom('IMGBB_API_KEY', '6d207e02198a847aa98d0a2a901485a5');
    //     $url = 'https://freeimage.host/api/1/upload';
    //     $base64Image = base64_encode($imageData);

    //     $postData = [
    //         'expiration' => '1800',
    //         'key' => $apiKey,
    //         'image' => $base64Image
    //     ];

    //     $ch = curl_init();
    //     curl_setopt($ch, CURLOPT_URL, $url);
    //     curl_setopt($ch, CURLOPT_POST, 1);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     $response = curl_exec($ch);
    //     curl_close($ch);

    //     $responseJson = json_decode($response, true);

    //     if (isset($responseJson['image']['url'])) {
    //         return $responseJson['image']['url'];
    //     } else {
    //         return false;
    //     }
    // }
}