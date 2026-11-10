<?php
header('Content-Type: application/json');

// 1. LIGHTWEIGHT .ENV LOADER (Upang mabasa ang GEMINI_API_KEY kahit sa Local/XAMPP)
function loadEnvFile($envPath) {
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), '"\'');
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Subukang i-load ang .env sa project root
loadEnvFile(__DIR__ . '/../.env');

// Siguraduhing may na-upload na file
if (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit();
}

// 2. Priority check: Server Environment (Render) -> .env File
$api_key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ($_SERVER['GEMINI_API_KEY'] ?? ''));

if (empty($api_key)) {
    echo json_encode(['success' => false, 'message' => 'Server Error: Gemini API key is missing in configuration.']);
    exit();
}

$file_tmp = $_FILES['proof_of_payment']['tmp_name'];
$mime_type = mime_content_type($file_tmp);

// I-convert ang image file sa Base64
$image_data = base64_encode(file_get_contents($file_tmp));

// Prompt para sa Gemini para sa structured JSON output
$prompt = "Analyze this payment receipt screenshot (GCash, Maya, or Philippine Bank transfer).
Extract the following details accurately and return ONLY a raw valid JSON object with NO markdown, NO code blocks, and NO extra text:
{
  \"is_legit_receipt\": boolean, // true if it is an official GCash/Maya/Bank transfer receipt, false if fake/random/unrelated image
  \"is_fake_generator\": boolean, // true if reference number has dummy patterns like 12345678, G1709123456, 00000000, etc.
  \"ref_no\": string or null, // The official transaction reference number (e.g. 0044323949002 or BN-20260930-04600502)
  \"amount\": float or 0, // Total amount sent / paid (e.g. 10000.00)
  \"sent_to\": string or null, // The recipient's name AND phone number if available (e.g. \"KR••H NH•L T. +63 951 813 1132\")
  \"receipt_date\": string or null // Date and time of transaction (e.g. \"Aug 25, 2026 11:35 AM\")
}";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt],
                [
                    "inline_data" => [
                        "mime_type" => $mime_type,
                        "data" => $image_data
                    ]
                ]
            ]
        ]
    ]
];

// 3. HYBRID AUTHENTICATION HANDLING (Gagana sa 'AQ...' at 'AIzaSy...' keys)
$api_key_clean = trim($api_key);

if (strpos($api_key_clean, 'AQ.') === 0) {
    // Kapag 'AQ...' token (Service Account / OAuth Token), ipasa sa Authorization Header
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent";
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key_clean
    ];
} else {
    // Kapag standard API key (AIzaSy...), ipasa sa URL parameter
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key_clean;
    $headers = [
        'Content-Type: application/json'
    ];
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    echo json_encode([
        'success' => false, 
        'message' => 'Gemini API Error or Connection Failed. (HTTP Code: ' . $http_code . ')'
    ]);
    exit();
}

$responseData = json_decode($response, true);
$rawText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Linisin ang response sakaling may markdown blocks (` ```json `)
$cleanJsonText = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText));
$extractedData = json_decode($cleanJsonText, true);

if (!$extractedData) {
    echo json_encode(['success' => false, 'message' => 'Could not parse receipt data from AI.']);
    exit();
}

echo json_encode([
    'success' => true,
    'data' => $extractedData
]);