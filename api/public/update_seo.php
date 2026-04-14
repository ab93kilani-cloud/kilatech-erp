<?php
/**
 * Standalone SEO Update API
 * Designed for n8n integration and Hostinger shared hosting.
 * Path: /api/public/update_seo.php
 */

// Headers for JSON and CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle Preflight OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method Not Allowed. This endpoint only accepts POST requests."
    ]);
    exit;
}

// Read raw input from php://input
$rawInput = file_get_contents("php://input");

// Handle empty input
if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Empty request body."
    ]);
    exit;
}

// Decode JSON into associative array
$inputData = json_decode($rawInput, true);

// Check for JSON decoding errors
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON payload: " . json_last_error_msg()
    ]);
    exit;
}

// Requirements: Validate required fields (id, seo_title, seo_description, h1, seo_content)
$requiredFields = ['id', 'seo_title', 'seo_description', 'h1', 'seo_content'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($inputData[$field])) {
        $missingFields[] = $field;
    }
}

// If validation fails
if (!empty($missingFields)) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields",
        "fields" => $missingFields
    ]);
    exit;
}

// If success: Return success status and the received data
http_response_code(200);
echo json_encode([
    "status" => "success",
    "received" => $inputData
]);
