<?php

if (!function_exists('getProductImageSrc')) {

    function getProductImageSrc($image)
    {
        $fallback = '/assets/images/no-image.png';

        if (empty($image)) {
            return $fallback;
        }

        $image = trim($image);

        // Base64 image
        if (stripos($image, 'data:image/') === 0) {
            return $image;
        }

        // Google Drive URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {

            $host = strtolower(parse_url($image, PHP_URL_HOST) ?? '');

            if (
                $host === 'drive.google.com' ||
                $host === 'docs.google.com'
            ) {
                $query = [];
                parse_str(parse_url($image, PHP_URL_QUERY) ?? '', $query);

                $fileId = $query['id'] ?? null;

                if (!$fileId && preg_match(
                    '~/(?:file/d|document/d|uc)/([a-zA-Z0-9_-]+)~',
                    parse_url($image, PHP_URL_PATH) ?? '',
                    $matches
                )) {
                    $fileId = $matches[1];
                }

                if ($fileId && preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
                    return '/drive-image.php?id=' . rawurlencode($fileId);
                }

                return $fallback;
            }

            // Other external image URL
            return $image;
        }

        // Raw Google Drive file ID
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $image)) {
            return '/drive-image.php?id=' . rawurlencode($image);
        }

        return $fallback;
    }
}