<?php
/**
 * Common functions for medical AI applications
 * 
 * Contains shared functionality used across different medical analysis tools
 * 
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */

/**
 * Available output languages
 */
$AVAILABLE_LANGUAGES = [
    'ro' => 'Română',
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
];

/**
 * Get language instruction for the AI model
 * 
 * @param string $language Language code
 * @return string Language instruction
 */
function getLanguageInstruction($language) {
    $language_instructions = [
        'ro' => 'Respond in Romanian.',
        'en' => 'Respond in English.',
        'es' => 'Responde en español.',
        'fr' => 'Répondez en français.',
        'de' => 'Antworte auf Deutsch.',
        'it' => 'Rispondi in italiano.'
    ];
    
    return isset($language_instructions[$language]) ? $language_instructions[$language] : $language_instructions['ro'];
}

/**
 * Get the color associated with a severity level
 * 
 * @param int $severity Severity level (0-10)
 * @return string Hex color code
 */
function getSeverityColor($severity) {
    if ($severity == 0) return '#10b981'; // green
    if ($severity <= 3) return '#3b82f6'; // blue
    if ($severity <= 6) return '#f59e0b'; // orange
    return '#ef4444'; // red
}

/**
 * Get the label associated with a severity level
 * 
 * @param int $severity Severity level (0-10)
 * @return string Severity label
 */
function getSeverityLabel($severity) {
    if ($severity == 0) return 'Normal';
    if ($severity <= 3) return 'Minor';
    if ($severity <= 6) return 'Moderate';
    if ($severity <= 8) return 'Severe';
    return 'Critic';
}

/**
 * Format file size in human readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Human readable file size
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
 * Preprocess image for better OCR results
 * Enhances contrast, applies threshold, and resizes image
 * 
 * @param string $image_path Path to the original image
 * @return string|false Path to preprocessed image or false on error
 */
function preprocessImageForOCR($image_path) {
    // Create temporary file path
    $temp_path = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
    
    // Get image info
    $image_info = getimagesize($image_path);
    if ($image_info === false) {
        return false;
    }
    
    // Create image resource based on type
    $image = null;
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($image_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($image_path);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($image_path);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($image_path);
            break;
        default:
            return false;
    }
    
    if ($image === false) {
        return false;
    }
    
    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate new dimensions (max 1000x1000)
    $max_size = 1000;
    $ratio = min($max_size / $width, $max_size / $height);
    $new_width = intval($width * $ratio);
    $new_height = intval($height * $ratio);
    
    // Create new image with new dimensions
    $resized_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG
    if ($image_info[2] === IMAGETYPE_PNG) {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
        imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Convert to grayscale
    imagefilter($resized_image, IMG_FILTER_GRAYSCALE);
    
    // Enhance contrast
    imagefilter($resized_image, IMG_FILTER_CONTRAST, -20);
    
    // Apply threshold (convert to black and white)
    imagefilter($resized_image, IMG_FILTER_THRESHOLD, 127);
    
    // Save as PNG
    $success = imagepng($resized_image, $temp_path, 9); // Compression level 9
    
    // Clean up
    imagedestroy($image);
    imagedestroy($resized_image);
    
    return $success ? $temp_path : false;
}

/**
 * Extract images from PDF file
 * 
 * @param string $pdf_path Path to the PDF file
 * @return array|false Array of image data or false on error
 */
function extractImagesFromPDF($pdf_path) {
    // Try Imagick first
    if (extension_loaded('imagick')) {
        try {
            $images = [];
            $imagick = new Imagick();
            $imagick->readImage($pdf_path);
            
            // Set resolution for better quality
            $imagick->setResolution(200, 200);
            
            // Get number of pages
            $page_count = $imagick->getNumberImages();
            
            if ($page_count === 0) {
                return false;
            }
            
            // Process first page only for OCR
            $imagick->setIteratorIndex(0);
            $page = $imagick->getImage();
            
            // Convert to PNG format
            $page->setImageFormat('png');
            $page->stripImage(); // Remove metadata
            
            // Get image data
            $image_data = $page->getImageBlob();
            $images[] = $image_data;
            
            // Clean up
            $page->destroy();
            $imagick->destroy();
            
            return $images;
        } catch (Exception $e) {
            // Fall through to try Gmagick
        }
    }
    
    // Try Gmagick as fallback
    if (extension_loaded('gmagick')) {
        try {
            $images = [];
            $gmagick = new Gmagick();
            $gmagick->readImage($pdf_path);
            
            // Set resolution for better quality
            $gmagick->setresolution(200, 200);
            
            // Get number of pages
            $page_count = $gmagick->getnumberimages();
            
            if ($page_count === 0) {
                return false;
            }
            
            // Process first page only for OCR
            $gmagick->setimageindex(0);
            $page = clone $gmagick;
            
            // Convert to PNG format
            $page->setimageformat('png');
            
            // Get image data
            $image_data = $page->getimageblob();
            $images[] = $image_data;
            
            // Clean up
            $page->clear();
            $gmagick->clear();
            
            return $images;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // If neither extension is available
    return false;
}

/**
 * Fetch available models from the LLM server API
 * 
 * @param string $api_endpoint The API endpoint URL
 * @param string $api_key The API key (if required)
 * @return array List of available models
 */
function getAvailableModels($api_endpoint, $api_key = '') {
    $models_url = $api_endpoint . '/models';
    
    // Make API request
    $ch = curl_init($models_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        // Return default models if API call fails
        return [];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['data'])) {
        return [];
    }
    
    $models = [];
    foreach ($response_data['data'] as $model) {
        if (isset($model['id'])) {
            // For vision models, we'll use a more user-friendly name
            $name = $model['id'];
            if (strpos($name, 'vision') !== false || strpos($name, 'vl') !== false) {
                $models[$name] = ucfirst(str_replace(':', ' ', $name)) . ' (Vision)';
            } else {
                $models[$name] = ucfirst(str_replace(':', ' ', $name));
            }
        }
    }
    
    // Sort models alphabetically by key (model name)
    ksort($models);
    
    return $models;
}

/**
 * Make API call to LLM server
 * 
 * @param string $api_endpoint_chat The chat API endpoint URL
 * @param array $data The request data
 * @param string $api_key The API key (if required)
 * @return array|false API response data or false on error
 */
function callLLMApi($api_endpoint_chat, $data, $api_key = '') {
    // Make API request
    $ch = curl_init($api_endpoint_chat);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = 'Connection error: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    } elseif ($http_code !== 200) {
        $error = 'API error: HTTP ' . $http_code;
        curl_close($ch);
        return ['error' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid API response format: ' . json_last_error_msg()];
    }
    
    return $response_data;
}
?>
