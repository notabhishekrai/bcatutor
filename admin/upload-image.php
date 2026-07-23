<?php
require_once __DIR__ . '/../config.php';
requireLogin();
csrfCheck();

header('Content-Type: application/json');

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid file uploaded.']);
    exit;
}

$file = $_FILES['image'];

// Only allow specific image types — checked by actual file content, not just the filename
$allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$mimeType = mime_content_type($file['tmp_name']);

if (!isset($allowedTypes[$mimeType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG, PNG, GIF, or WEBP images are allowed.']);
    exit;
}

// 5MB limit
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Image must be under 5MB.']);
    exit;
}

// ---- Resize + compress before saving, to keep storage/bandwidth low ----
$maxWidth = 1200; // no blog post needs images wider than this

// Load the image into memory based on its actual type
switch ($mimeType) {
    case 'image/jpeg':
        $image = imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'image/png':
        $image = imagecreatefrompng($file['tmp_name']);
        break;
    case 'image/gif':
        $image = imagecreatefromgif($file['tmp_name']);
        break;
    case 'image/webp':
        $image = imagecreatefromwebp($file['tmp_name']);
        break;
    default:
        $image = false;
}

if ($image === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Could not process this image.']);
    exit;
}

$originalWidth = imagesx($image);
$originalHeight = imagesy($image);

// Only resize if it's actually bigger than needed — don't upscale small images
if ($originalWidth > $maxWidth) {
    $newWidth = $maxWidth;
    $newHeight = (int)($originalHeight * ($maxWidth / $originalWidth));

    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG/GIF sources
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    imagedestroy($image);
    $image = $resized;
}

// Always save as WebP regardless of the original format — much better compression
$extension = 'webp';
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$uploadDir = __DIR__ . '/../uploads/';
$destination = $uploadDir . $filename;

imagewebp($image, $destination, 78); // quality 78 — good balance of size vs. sharpness
imagedestroy($image);

echo json_encode(['url' => '/uploads/' . $filename]);
exit;