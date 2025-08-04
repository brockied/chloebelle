<?php
/**
 * Common functions for Chloe Belle Website
 * Updated with media upload functionality
 */

/**
 * Generate secure filename for uploads
 */
function generateSecureFilename($originalName) {
    $pathInfo = pathinfo($originalName);
    $extension = strtolower($pathInfo['extension']);
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '', $pathInfo['filename']);
    
    // Generate unique filename
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    
    return $baseName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Create blurred version of image for premium content
 */
function createBlurredImage($sourcePath, $intensity = 15) {
    if (!file_exists($sourcePath)) {
        return false;
    }
    
    $pathInfo = pathinfo($sourcePath);
    $sourceMime = mime_content_type($sourcePath);
    
    // Create image resource based on mime type
    switch ($sourceMime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Apply blur filter
    for ($i = 0; $i < $intensity; $i++) {
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
    }
    
    // Save blurred version
    $blurredPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_blurred.' . $pathInfo['extension'];
    
    $result = false;
    switch ($sourceMime) {
        case 'image/jpeg':
            $result = imagejpeg($image, $blurredPath, 90);
            break;
        case 'image/png':
            $result = imagepng($image, $blurredPath);
            break;
        case 'image/gif':
            $result = imagegif($image, $blurredPath);
            break;
        case 'image/webp':
            $result = imagewebp($image, $blurredPath);
            break;
    }
    
    imagedestroy($image);
    return $result ? $blurredPath : false;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $maxSize = 52428800) {
    $errors = [];
    
    if (!isset($file['error']) || is_array($file['error'])) {
        $errors[] = 'Invalid file upload';
        return $errors;
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            $errors[] = 'No file was uploaded';
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File size exceeds limit';
            break;
        default:
            $errors[] = 'Unknown upload error';
            break;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds ' . formatFileSize($maxSize);
    }
    
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv'
    ];
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedTypes)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
    }
    
    if (!isset($allowedMimes[$extension]) || $mimeType !== $allowedMimes[$extension]) {
        $errors[] = 'Invalid file type';
    }
    
    return $errors;
}

/**
 * Upload file securely
 */
function uploadFile($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'], $maxSize = 52428800) {
    $errors = validateFileUpload($file, $allowedTypes, $maxSize);
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = generateSecureFilename($file['name']);
    $uploadPath = $uploadDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Set proper permissions
        chmod($uploadPath, 0644);
        
        return [
            'success' => true,
            'path' => $uploadPath,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'mime_type' => mime_content_type($uploadPath)
        ];
    } else {
        return ['success' => false, 'errors' => ['Failed to move uploaded file']];
    }
}

/**
 * Resize image to specified dimensions
 */
function resizeImage($sourcePath, $maxWidth = 1200, $maxHeight = 800, $quality = 90) {
    if (!file_exists($sourcePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    list($originalWidth, $originalHeight, $imageType) = $imageInfo;
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = intval($originalWidth * $ratio);
    $newHeight = intval($originalHeight * $ratio);
    
    // Create image resource
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
        imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
    }
    
    // Resize image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
    
    // Save resized image
    $pathInfo = pathinfo($sourcePath);
    $resizedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_resized.' . $pathInfo['extension'];
    
    $result = false;
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $resizedPath, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $resizedPath);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $resizedPath);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($newImage, $resizedPath, $quality);
            break;
    }
    
    // Clean up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result ? $resizedPath : false;
}

/**
 * Create thumbnail from image
 */
function createThumbnail($sourcePath, $thumbWidth = 300, $thumbHeight = 300) {
    return resizeImage($sourcePath, $thumbWidth, $thumbHeight, 85);
}

/**
 * Get image dimensions
 */
function getImageDimensions($imagePath) {
    if (!file_exists($imagePath)) {
        return false;
    }
    
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return false;
    }
    
    return [
        'width' => $imageInfo[0],
        'height' => $imageInfo[1],
        'type' => $imageInfo[2],
        'mime' => $imageInfo['mime']
    ];
}

/**
 * Sanitize filename for web use
 */
function sanitizeFilename($filename) {
    // Remove any character that isn't alphanumeric, underscore, dash, or dot
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Remove multiple consecutive dots
    $filename = preg_replace('/\.+/', '.', $filename);
    
    // Trim dots from beginning and end
    $filename = trim($filename, '.');
    
    return $filename;
}

/**
 * Check if file is an image
 */
function isImage($filePath) {
    $imageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    $imageInfo = getimagesize($filePath);
    
    return $imageInfo && in_array($imageInfo[2], $imageTypes);
}

/**
 * Check if file is a video
 */
function isVideo($filePath) {
    $mimeType = mime_content_type($filePath);
    $videoMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'];
    
    return in_array($mimeType, $videoMimes);
}

/**
 * Get video duration (requires ffmpeg)
 */
function getVideoDuration($videoPath) {
    if (!file_exists($videoPath)) {
        return false;
    }
    
    // This requires ffmpeg to be installed on the server
    $command = "ffprobe -v quiet -show_entries format=duration -of csv=\"p=0\" " . escapeshellarg($videoPath);
    $duration = shell_exec($command);
    
    return $duration ? floatval(trim($duration)) : false;
}

/**
 * Create video thumbnail (requires ffmpeg)
 */
function createVideoThumbnail($videoPath, $outputPath = null, $timeOffset = '00:00:01') {
    if (!file_exists($videoPath)) {
        return false;
    }
    
    if (!$outputPath) {
        $pathInfo = pathinfo($videoPath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_thumb.jpg';
    }
    
    // This requires ffmpeg to be installed on the server
    $command = "ffmpeg -i " . escapeshellarg($videoPath) . 
               " -ss $timeOffset -vframes 1 -y " . 
               escapeshellarg($outputPath) . " 2>&1";
    
    $output = shell_exec($command);
    
    return file_exists($outputPath) ? $outputPath : false;
}

/**
 * Delete file securely
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

/**
 * Clean old uploaded files (cleanup function)
 */
function cleanupOldFiles($directory, $maxAge = 86400) {
    if (!is_dir($directory)) {
        return false;
    }
    
    $files = glob($directory . '/*');
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Validate image dimensions
 */
function validateImageDimensions($imagePath, $minWidth = 0, $minHeight = 0, $maxWidth = 5000, $maxHeight = 5000) {
    $dimensions = getImageDimensions($imagePath);
    
    if (!$dimensions) {
        return ['valid' => false, 'error' => 'Unable to read image dimensions'];
    }
    
    if ($dimensions['width'] < $minWidth || $dimensions['height'] < $minHeight) {
        return ['valid' => false, 'error' => "Image too small. Minimum size: {$minWidth}x{$minHeight}px"];
    }
    
    if ($dimensions['width'] > $maxWidth || $dimensions['height'] > $maxHeight) {
        return ['valid' => false, 'error' => "Image too large. Maximum size: {$maxWidth}x{$maxHeight}px"];
    }
    
    return ['valid' => true, 'dimensions' => $dimensions];
}

/**
 * Add watermark to image
 */
function addWatermark($imagePath, $watermarkPath, $position = 'bottom-right', $opacity = 50) {
    if (!file_exists($imagePath) || !file_exists($watermarkPath)) {
        return false;
    }
    
    // Get image info
    $imageInfo = getimagesize($imagePath);
    $watermarkInfo = getimagesize($watermarkPath);
    
    if (!$imageInfo || !$watermarkInfo) {
        return false;
    }
    
    // Create image resources
    $image = imagecreatefromjpeg($imagePath); // Assuming JPEG for simplicity
    $watermark = imagecreatefrompng($watermarkPath); // Assuming PNG watermark
    
    if (!$image || !$watermark) {
        return false;
    }
    
    // Calculate position
    $imageWidth = $imageInfo[0];
    $imageHeight = $imageInfo[1];
    $watermarkWidth = $watermarkInfo[0];
    $watermarkHeight = $watermarkInfo[1];
    
    $margin = 20;
    
    switch ($position) {
        case 'top-left':
            $x = $margin;
            $y = $margin;
            break;
        case 'top-right':
            $x = $imageWidth - $watermarkWidth - $margin;
            $y = $margin;
            break;
        case 'bottom-left':
            $x = $margin;
            $y = $imageHeight - $watermarkHeight - $margin;
            break;
        case 'bottom-right':
        default:
            $x = $imageWidth - $watermarkWidth - $margin;
            $y = $imageHeight - $watermarkHeight - $margin;
            break;
        case 'center':
            $x = ($imageWidth - $watermarkWidth) / 2;
            $y = ($imageHeight - $watermarkHeight) / 2;
            break;
    }
    
    // Apply watermark
    imagecopymerge($image, $watermark, $x, $y, 0, 0, $watermarkWidth, $watermarkHeight, $opacity);
    
    // Save watermarked image
    $pathInfo = pathinfo($imagePath);
    $watermarkedPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_watermarked.' . $pathInfo['extension'];
    
    $result = imagejpeg($image, $watermarkedPath, 90);
    
    // Clean up memory
    imagedestroy($image);
    imagedestroy($watermark);
    
    return $result ? $watermarkedPath : false;
}

/**
 * Log upload activity
 */
function logUploadActivity($userId, $filename, $fileSize, $uploadType = 'general') {
    // This would typically log to a database or file
    $logEntry = date('Y-m-d H:i:s') . " - User $userId uploaded $filename ($fileSize bytes) - Type: $uploadType" . PHP_EOL;
    error_log($logEntry, 3, '../logs/uploads.log');
}

/**
 * Check server upload limits
 */
function getServerUploadLimits() {
    return [
        'max_file_size' => ini_get('upload_max_filesize'),
        'max_post_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ];
}

/**
 * Convert bytes to human readable format
 */
function bytesToHuman($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Check available disk space
 */
function checkDiskSpace($directory = '../uploads/') {
    $freeBytes = disk_free_space($directory);
    $totalBytes = disk_total_space($directory);
    
    return [
        'free' => $freeBytes,
        'total' => $totalBytes,
        'used' => $totalBytes - $freeBytes,
        'free_percent' => ($freeBytes / $totalBytes) * 100
    ];
}
?>