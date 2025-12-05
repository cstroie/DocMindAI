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
 * Handle common request processing for AI applications
 * 
 * @param string $input The input data (report, content, etc.)
 * @param int $max_length Maximum allowed length
 * @return array Processing result with validation status
 */
function processInput($input, $max_length = 10000) {
    $result = [
        'valid' => true,
        'error' => null,
        'data' => null
    ];
    
    // Sanitize and validate input
    $data = trim($input);
    
    // Validate length
    if (strlen($data) > $max_length) {
        $result['valid'] = false;
        $result['error'] = 'The input is too long. Maximum ' . $max_length . ' characters allowed.';
    } 
    // Validate is not empty after trimming
    elseif (empty($data)) {
        $result['valid'] = false;
        $result['error'] = 'The input cannot be empty.';
    } else {
        $result['data'] = $data;
    }
    
    return $result;
}

/**
 * Handle common URL validation and processing
 * 
 * @param string $url The URL to validate
 * @return array Processing result with validation status
 */
function processUrl($url) {
    $result = [
        'valid' => true,
        'error' => null,
        'data' => null
    ];
    
    // Sanitize and validate input
    $data = trim($url);
    
    // Validate URL format
    if (!filter_var($data, FILTER_VALIDATE_URL)) {
        $result['valid'] = false;
        $result['error'] = 'Invalid URL format. Please enter a valid URL including http:// or https://';
    } else {
        $result['data'] = $data;
    }
    
    return $result;
}

/**
 * Set common cookies for AI applications
 * 
 * @param array $cookies Cookie data to set
 * @param int $expire_time Cookie expiration time
 */
function setCommonCookies($cookies, $expire_time = 2592000) { // 30 days default
    foreach ($cookies as $name => $value) {
        setcookie($name, $value, time() + $expire_time, '/');
    }
}

/**
 * Send JSON response and exit
 * 
 * @param array $data Response data
 * @param bool $is_api_request Whether this is an API request
 */
function sendJsonResponse($data, $is_api_request = false) {
    if ($is_api_request) {
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/**
 * Extract JSON from AI response content
 * 
 * @param string $content AI response content
 * @return array|null Extracted JSON data or null if not found
 */
function extractJsonFromResponse($content) {
    // Try to find JSON between code fences
    if (preg_match('/```(?:json)?\s*({.*?})\s*```/s', $content, $matches)) {
        $json_str = $matches[1];
    } 
    // Then try to find any JSON object
    elseif (preg_match('/\{.*\}/s', $content, $matches)) {
        $json_str = $matches[0];
    } else {
        return null;
    }
    
    // Clean up the JSON string
    $json_str = trim($json_str);
    
    // Try to decode JSON
    $result = json_decode($json_str, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to fix common JSON issues
        $json_str = preg_replace('/,\s*([\]}])/m', '$1', $json_str); // Remove trailing commas
        $json_str = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_str); // Add quotes to keys
        $json_str = preg_replace('/:\s*\'([^\']*)\'/', ':"$1"', $json_str); // Replace single quotes with double quotes
        $json_str = preg_replace('/\s+/', ' ', $json_str); // Normalize whitespace
        
        $result = json_decode($json_str, true);
    }
    
    return $result;
}

/**
 * Convert basic markdown to HTML
 * 
 * @param string $markdown Markdown text to convert
 * @return string HTML output
 */
function markdownToHtml($markdown) {
    // Remove markdown code fences if present
    $markdown = preg_replace('/^```(?:markdown)?\s*(.*?)\s*```$/s', '$1', $markdown);
    
    // Normalize line endings
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    
    // Escape HTML entities first
    $markdown = htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8');
    
    // Split into lines for processing
    $lines = explode("\n", $markdown);
    $html = [];
    $inCodeBlock = false;
    $inList = false;
    $listType = '';
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trimmed = trim($line);
        
        // Code blocks (```)
        if (preg_match('/^```/', $trimmed)) {
            if ($inCodeBlock) {
                $html[] = '</code></pre>';
                $inCodeBlock = false;
            } else {
                if ($inList) {
                    $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                    $inList = false;
                }
                $html[] = '<pre><code>';
                $inCodeBlock = true;
            }
            continue;
        }
        
        if ($inCodeBlock) {
            $html[] = $line;
            continue;
        }
        
        // Empty lines
        if ($trimmed === '') {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            continue;
        }
        
        // Headers
        if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $level = strlen($matches[1]);
            $text = processInlineMarkdown($matches[2]);
            $html[] = "<h$level>$text</h$level>";
            continue;
        }
        
        // Unordered lists
        if (preg_match('/^[\*\-]\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList || $listType !== 'ul') {
                if ($inList && $listType === 'ol') {
                    $html[] = '</ol>';
                }
                $html[] = '<ul>';
                $inList = true;
                $listType = 'ul';
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<li>$text</li>";
            continue;
        }
        
        // Ordered lists
        if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
            if (!$inList || $listType !== 'ol') {
                if ($inList && $listType === 'ul') {
                    $html[] = '</ul>';
                }
                $html[] = '<ol>';
                $inList = true;
                $listType = 'ol';
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<li>$text</li>";
            continue;
        }
        
        // Blockquotes
        if (preg_match('/^>\s+(.+)$/', $trimmed, $matches)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $text = processInlineMarkdown($matches[1]);
            $html[] = "<blockquote>$text</blockquote>";
            continue;
        }
        
        // Horizontal rule
        if (preg_match('/^(\*{3,}|-{3,}|_{3,})$/', $trimmed)) {
            if ($inList) {
                $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
                $inList = false;
            }
            $html[] = '<hr>';
            continue;
        }
        
        // Regular paragraph
        if ($inList) {
            $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
            $inList = false;
        }
        $text = processInlineMarkdown($trimmed);
        $html[] = "<p>$text</p>";
    }
    
    // Close any open lists
    if ($inList) {
        $html[] = $listType === 'ul' ? '</ul>' : '</ol>';
    }
    
    // Close any open code blocks
    if ($inCodeBlock) {
        $html[] = '</code></pre>';
    }
    
    return implode("\n", $html);
}

function processInlineMarkdown($text) {
    // Bold (**text** or __text__)
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
    
    // Italic (*text* or _text_)
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
    
    // Inline code (`code`)
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    
    // Links [text](url)
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);
    
    // Images ![alt](url)
    $text = preg_replace('/!\[(.+?)\]\((.+?)\)/', '<img src="$2" alt="$1">', $text);
    
    return $text;
}

/**
 * Get the color associated with a probability level
 * 
 * @param int $probability Probability percentage (0-100)
 * @return string Hex color code
 */
function getProbabilityColor($probability) {
    if ($probability >= 80) return '#ef4444'; // red
    if ($probability >= 60) return '#f59e0b'; // orange
    if ($probability >= 40) return '#3b82f6'; // blue
    return '#10b981'; // green
}
?>
