<?php
/**
 * Supabase Storage Configuration
 * Keep your service role key private.
 */

define('SUPABASE_URL', rtrim(getenv('SUPABASE_URL') ?: '', '/'));
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
define('SUPABASE_BUCKET', 'product-images');

/**
 * Upload product image to Supabase Storage.
 * Returns the permanent public URL.
 */
function uploadProductImage($file)
{
    if (
        empty(SUPABASE_URL) ||
        empty(SUPABASE_SERVICE_KEY)
    ) {
        throw new Exception("Supabase Storage configuration is missing.");
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Please select a valid product image.");
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Image size must not exceed 5MB.");
    }

    $tmp = $file['tmp_name'];

    if (!is_uploaded_file($tmp)) {
        throw new Exception("Invalid uploaded file.");
    }

    $imageInfo = getimagesize($tmp);

    if ($imageInfo === false) {
        throw new Exception("Invalid image file.");
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception("Only JPG, JPEG, PNG, and WEBP images are allowed.");
    }

    $extension = $allowed[$mime];

    $fileName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $extension;

    $fileContent = file_get_contents($tmp);

    if ($fileContent === false) {
        throw new Exception("Unable to read uploaded image.");
    }

    $url = SUPABASE_URL
        . '/storage/v1/object/'
        . SUPABASE_BUCKET
        . '/'
        . rawurlencode($fileName);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: ' . $mime,
            'x-upsert: false'
        ],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log("Supabase upload error: " . $curlError . " " . $response);
        throw new Exception("Failed to upload image to Supabase Storage.");
    }

    return SUPABASE_URL
        . '/storage/v1/object/public/'
        . SUPABASE_BUCKET
        . '/'
        . rawurlencode($fileName);
}

/**
 * Delete an old image from Supabase Storage.
 * Only deletes images belonging to product-images bucket.
 */
function deleteProductImage($imageUrl)
{
    if (
        empty($imageUrl) ||
        empty(SUPABASE_URL) ||
        empty(SUPABASE_SERVICE_KEY)
    ) {
        return false;
    }

    $prefix = SUPABASE_URL
        . '/storage/v1/object/public/'
        . SUPABASE_BUCKET . '/';

    if (strpos($imageUrl, $prefix) !== 0) {
        return false;
    }

    $fileName = rawurldecode(substr($imageUrl, strlen($prefix)));

    if ($fileName === '' || strpos($fileName, '/') !== false) {
        return false;
    }

    $url = SUPABASE_URL
        . '/storage/v1/object/'
        . SUPABASE_BUCKET . '/'
        . rawurlencode($fileName);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return $response !== false && $httpCode >= 200 && $httpCode < 300;
}