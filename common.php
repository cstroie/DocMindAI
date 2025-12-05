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
 * @param bool $apply_threshold Whether to apply threshold (default: false)
 * @param bool $apply_dilation Whether to apply dilation (default: false)
 * @return string|false Path to preprocessed image or false on error
 */
function preprocessImageForOCR($image_path, $apply_threshold = false, $apply_dilation = false) {
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
    
    // Only scale if image is larger than max_size
    $max_size = 1000;
    if ($width > $max_size || $height > $max_size) {
        // Calculate new dimensions (max 1000x1000)
        $ratio = min($max_size / $width, $max_size / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);
        
        // Create new image with new dimensions
        $resized_image = imagecreatetruecolor($new_width, $new_height);
    } else {
        // Keep original dimensions
        $new_width = $width;
        $new_height = $height;
        $resized_image = imagecreatetruecolor($new_width, $new_height);
    }
    
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
    
    // Apply threshold with Otsu's method approximation if enabled
    if ($apply_threshold) {
        // Calculate histogram
        $histogram = [];
        for ($i = 0; $i < 256; $i++) {
            $histogram[$i] = 0;
        }
        
        // Build histogram
        for ($y = 0; $y < $new_height; $y++) {
            for ($x = 0; $x < $new_width; $x++) {
                $rgb = imagecolorat($resized_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $histogram[$r]++;
            }
        }
        
        // Calculate Otsu threshold
        $total_pixels = $new_width * $new_height;
        $sum = 0;
        for ($i = 0; $i < 256; $i++) {
            $sum += $i * $histogram[$i];
        }
        
        $sumB = 0;
        $wB = 0;
        $wF = 0;
        $varMax = 0;
        $threshold = 0;
        
        for ($i = 0; $i < 256; $i++) {
            $wB += $histogram[$i];
            if ($wB == 0) continue;
            
            $wF = $total_pixels - $wB;
            if ($wF == 0) break;
            
            $sumB += $i * $histogram[$i];
            $mB = $sumB / $wB;
            $mF = ($sum - $sumB) / $wF;
            
            $varBetween = $wB * $wF * ($mB - $mF) * ($mB - $mF);
            
            if ($varBetween > $varMax) {
                $varMax = $varBetween;
                $threshold = $i;
            }
        }
        
        // Apply threshold
        for ($y = 0; $y < $new_height; $y++) {
            for ($x = 0; $x < $new_width; $x++) {
                $rgb = imagecolorat($resized_image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $color = ($r >= $threshold) ? 255 : 0;
                $new_color = imagecolorallocate($resized_image, $color, $color, $color);
                imagesetpixel($resized_image, $x, $y, $new_color);
            }
        }
    }
    
    // Apply dilation (1x1 kernel) if enabled
    if ($apply_dilation) {
        $dilated_image = imagecreatetruecolor($new_width, $new_height);
        imagecopy($dilated_image, $resized_image, 0, 0, 0, 0, $new_width, $new_height);
        
        for ($y = 1; $y < $new_height - 1; $y++) {
            for ($x = 1; $x < $new_width - 1; $x++) {
                $is_black = false;
                // Check 1x1 neighborhood
                for ($ky = -1; $ky <= 1; $ky++) {
                    for ($kx = -1; $kx <= 1; $kx++) {
                        $rgb = imagecolorat($resized_image, $x + $kx, $y + $ky);
                        $r = ($rgb >> 16) & 0xFF;
                        if ($r == 0) {
                            $is_black = true;
                            break 2;
                        }
                    }
                }
                if ($is_black) {
                    $black = imagecolorallocate($dilated_image, 0, 0, 0);
                    imagesetpixel($dilated_image, $x, $y, $black);
                }
            }
        }
    } else {
        // If dilation is not applied, use the resized image directly
        $dilated_image = $resized_image;
    }
    
    // Save as PNG
    $success = imagepng($dilated_image, $temp_path, 9); // Compression level 9
    
    // Clean up
    imagedestroy($image);
    imagedestroy($resized_image);
    if ($apply_dilation) {
        imagedestroy($dilated_image);
    }
    
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
 * @param string $filter_regex Regular expression to filter models (optional)
 * @return array List of available models
 */
function getAvailableModels($api_endpoint, $api_key = '', $filter_regex = '') {
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
            // Apply filter if provided
            if ($filter_regex !== '' && !preg_match($filter_regex, $model['id'])) {
                continue;
            }
            
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

/**
 * Convert basic markdown to HTML
 * 
 * @param string $markdown Markdown text to convert
 * @return string HTML output
 */
function markdownToHtml($markdown) {
    // Convert headers (# Header)
    $markdown = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $markdown);
    $markdown = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $markdown);
    $markdown = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $markdown);
    
    // Convert bold (**bold** or __bold__)
    $markdown = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $markdown);
    $markdown = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $markdown);
    
    // Convert italic (*italic* or _italic_)
    $markdown = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $markdown);
    $markdown = preg_replace('/_(.*?)_/', '<em>$1</em>', $markdown);
    
    // Convert inline code (`code`)
    $markdown = preg_replace('/`(.*?)`/', '<code>$1</code>', $markdown);
    
    // Convert code blocks (```code```)
    $markdown = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $markdown);
    
    // Convert links ([text](url))
    $markdown = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $markdown);
    
    // Convert unordered lists (* item or - item)
    $markdown = preg_replace('/^\* (.*$)/m', '<li>$1</li>', $markdown);
    $markdown = preg_replace('/^- (.*$)/m', '<li>$1</li>', $markdown);
    // Wrap consecutive <li> elements in <ul>
    $markdown = preg_replace('/(<li>.*<\/li>(\s*<li>.*<\/li>)*)/s', '<ul>$1</ul>', $markdown);
    
    // Convert paragraphs (empty line separated text)
    $markdown = preg_replace('/\n\s*\n/', '</p><p>', $markdown);
    $markdown = '<p>' . $markdown . '</p>';
    
    // Handle line breaks within paragraphs
    $markdown = str_replace("\n", '<br>', $markdown);
    
    // Remove empty paragraphs
    $markdown = preg_replace('/<p>\s*<\/p>/', '', $markdown);
    $markdown = preg_replace('/<p><br\s*\/?>\s*<\/p>/', '', $markdown);
    
    return $markdown;
}
?>
