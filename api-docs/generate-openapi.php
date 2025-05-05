<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$apiUrl = $_ENV['API_URL'] ?? 'http://localhost:8000/api';

$openapiPath = __DIR__ . '/../api-docs/openapi.json';

$openapiContent = file_get_contents($openapiPath);
$openApiSpec = json_decode($openapiContent, true);

$openApiSpec['servers'][0]['url'] = $apiUrl;

file_put_contents($openapiPath, json_encode($openApiSpec, JSON_PRETTY_PRINT));

echo "OpenAPI specification updated with API_URL: $apiUrl\n";