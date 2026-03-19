<?php
/**
 * DocMind AI - Unified Gateway
 *
 * Central endpoint for all AI tool operations, serving both as API and web interface
 *
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */

// =========================================================================
// Initialization & Configuration
// =========================================================================

// Load configuration if available
$CONFIG = parse_ini_file('config.ini', true);
if ($CONFIG === false) {
    die('Configuration file config.ini not found or invalid.');
}
$CONFIG = $CONFIG['general'] ?? [];
// Ensure all expected keys have sensible defaults
$CONFIG['provider']            = $CONFIG['provider']            ?? 'ollama';
$CONFIG['default_text_model']  = $CONFIG['default_text_model']  ?? 'qwen2.5:1.5b';
$CONFIG['default_vision_model']= $CONFIG['default_vision_model']?? 'gemma3:4b';
$CONFIG['filter']              = $CONFIG['filter']              ?? '/./';
$CONFIG['chat_history_length'] = $CONFIG['chat_history_length'] ?? 10;
$CONFIG['debug_mode']          = $CONFIG['debug_mode']          ?? false;
$CONFIG['allowed_origins']     = $CONFIG['allowed_origins']     ?? ['*'];
$CONFIG['max_file_size']       = $CONFIG['max_file_size']       ?? 10 * 1024 * 1024;
$CONFIG['allowed_file_types']  = $CONFIG['allowed_file_types']  ?? 'image/jpeg, image/png, image/gif, image/webp, application/pdf, text/plain, text/markdown, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/vnd.oasis.opendocument.text';
// Derive runtime variables from the consolidated config
$provider            = $CONFIG['provider'];
$providerConfig      = getLlmProviderConfig($provider);
$LLM_API_ENDPOINT_CHAT = $providerConfig['endpoint'] . '/chat/completions';
$LLM_API_KEY         = $providerConfig['key'] ?? '';
$DEFAULT_TEXT_MODEL  = $CONFIG['default_text_model'];
$DEFAULT_VISION_MODEL= $CONFIG['default_vision_model'];
$LLM_API_FILTER      = $CONFIG['filter'];
$CHAT_HISTORY_LENGTH = $CONFIG['chat_history_length'];
$DEBUG_MODE          = $CONFIG['debug_mode'];
$ALLOWED_ORIGINS     = $CONFIG['allowed_origins'];
$MAX_FILE_SIZE       = $CONFIG['max_file_size'];
$ALLOWED_FILE_TYPES  = $CONFIG['allowed_file_types'];

/**
 * Fetches the X-Vqd-4 token from DuckDuckGo status endpoint.
 * 
 * @return array Array with 'token' and 'token_hash' keys, or false on failure
 */
function fetchDuckDuckGoToken() {
    $status_url = "https://duckduckgo.com/duckchat/v1/status";
    $headers = [
        "Accept" => "*/*",
        "Referer" => "https://duckduckgo.com/",
        "Origin" => "https://duckduckgo.com",
        "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
        "Dnt" => "1",
        "Sec-Gpc" => "1",
        "Sec-Fetch-Site" => "same-origin",
        "Sec-Fetch-Mode" => "cors",
        "Sec-Fetch-Dest" => "empty",
        "Priority" => "u=1, i",
        "X-Vqd-Accept" => "1",
    ];
    
    $ch = curl_init($status_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        return false;
    }
    
    // Parse headers to get tokens
    $x_vqd_4 = null;
    $x_vqd_hash_1 = null;
    
    // Get the response headers
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($response, 0, $header_size);
    
    // Parse headers manually
    foreach (explode("\r\n", $header_text) as $header) {
        if (strpos($header, 'X-Vqd-4:') === 0) {
            $x_vqd_4 = trim(substr($header, 10));
        } elseif (strpos($header, 'x-vqd-hash-1:') === 0) {
            $x_vqd_hash_1 = trim(substr($header, 15));
        }
    }
    
    return [
        'token' => $x_vqd_4,
        'token_hash' => $x_vqd_hash_1
    ];
}

/**
 * Call DuckDuckGo chat API
 * 
 * @param string $query The query to send
 * @param string $model The model to use
 * @param string $token Optional X-Vqd-4 token
 * @param string $token_hash Optional x-vqd-hash-1 token
 * @return array API response or error array
 */
function callDuckDuckGoChat($query, $model = 'gpt-4o-mini', $token = null, $token_hash = null) {
    // Get tokens if not provided
    if (!$token || !$token_hash) {
        $token_data = fetchDuckDuckGoToken();
        if (!$token_data) {
            return ['error' => 'Failed to fetch token'];
        }
        $token = $token_data['token'];
        $token_hash = $token_data['token_hash'];
    }
    
    $chat_url = "https://duckduckgo.com/duckchat/v1/chat";
    $headers = [
        "Accept" => "text/event-stream",
        "Content-Type" => "application/json",
        "Referer" => "https://duckduckgo.com/",
        "Origin" => "https://duckduckgo.com",
        "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
        "X-Vqd-4" => $token,
        "x-vqd-hash-1" => $token_hash,
        "Dnt" => "1",
        "Sec-Gpc" => "1",
        "Sec-Fetch-Site" => "same-origin",
        "Sec-Fetch-Mode" => "cors",
        "Sec-Fetch-Dest" => "empty",
        "Priority" => "u=1, i",
    ];
    
    $payload = [
        "model" => $model,
        "messages" => [["role" => "user", "content" => $query]]
    ];
    
    $ch = curl_init($chat_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 401) {
        // Try to get new token and retry
        $token_data = fetchDuckDuckGoToken();
        if ($token_data) {
            return callDuckDuckGoChat($query, $model, $token_data['token'], $token_data['token_hash']);
        } else {
            return ['error' => 'Failed to fetch new token after 401 error'];
        }
    }
    
    if ($http_code != 200) {
        return ['error' => 'API error: HTTP ' . $http_code];
    }
    
    // Parse SSE response
    $full_message = "";
    $lines = explode("\n", $response);
    
    foreach ($lines as $line) {
        if (strpos($line, "data: ") === 0) {
            $data = substr($line, 6);
            try {
                $json_data = json_decode($data, true);
                if (isset($json_data['message'])) {
                    $full_message .= $json_data['message'];
                }
            } catch (Exception $e) {
                // Ignore JSON decode errors
            }
        }
    }
    
    return ['content' => $full_message];
}

/**
 * Get LLM API configuration based on provider
 * 
 * This function returns the appropriate API endpoint and key for different LLM providers.
 * It supports various providers with their specific endpoint configurations.
 * 
 * @param string $provider The provider name (ollama, openrouter, openai, together, cerebras, local, duck)
 * @return array Configuration array with 'endpoint' and 'key' keys
 */
function getLlmProviderConfig($provider = 'ollama') {
    $configs = [
        'ollama' => [
            'endpoint' => 'http://localhost:11434/v1',
            'key' => 'ollama' // Ollama doesn't require API key but some clients expect one
        ],
        'openrouter' => [
            'endpoint' => 'https://openrouter.ai/api/v1',
            'key' => ''
        ],
        'openai' => [
            'endpoint' => 'https://api.openai.com/v1',
            'key' => ''
        ],
        'together' => [
            'endpoint' => 'https://api.together.xyz/v1',
            'key' => ''
        ],
        'cerebras' => [
            'endpoint' => 'https://api.cerebras.ai/v1',
            'key' => ''
        ],
        'local' => [
            'endpoint' => 'http://localhost:8000/v1',
            'key' => ''
        ],
        'duck' => [
            'endpoint' => 'https://duckduckgo.com/duckchat/v1/chat',
            'key' => '' // DuckDuckGo doesn't require API key
        ]
    ];
    
    return $configs[$provider] ?? $configs['ollama'];
}

// =========================================================================
// UI Helper Functions
// =========================================================================

/**
 * Resize an image while maintaining aspect ratio
 * 
 * This function resizes an image to fit within a maximum dimension while
 * preserving the original aspect ratio. It handles transparency appropriately
 * for different image types and returns the resized image resource along with
 * its new dimensions.
 * 
 * @param resource $image GD image resource to resize
 * @param int $max_size Maximum dimension (width or height) in pixels
 * @return array|false Array with resized image resource and new dimensions, or false on error
 * 
 * @note If the image is smaller than max_size, it returns the original dimensions
 * @note Uses bicubic resampling for high-quality resizing
 * @note Preserves transparency for PNG/GIF, uses white background for JPEG
 * @note The returned array contains: ['image' => resource, 'width' => int, 'height' => int]
 * @see processUploadedImage() - Uses this for image processing
 * @see preprocessImageForOCR() - Uses this for OCR preprocessing
 */
function resizeImage($image, $max_size = 1000) {
    $width = imagesx($image);
    $height = imagesy($image);

    // Only scale if image is larger than max_size
    if ($width > $max_size || $height > $max_size) {
        // Calculate new dimensions (max 1000x1000)
        $ratio = min($max_size / $width, $max_size / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);

        // Create new image with new dimensions
        $resized_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and GIF, but use white background for JPEG
        if (imageistruecolor($image)) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            // Use white background instead of transparent for better compatibility
            $white = imagecolorallocate($resized_image, 255, 255, 255);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $white);
        }
    } else {
        // Keep original dimensions
        $new_width = $width;
        $new_height = $height;
        $resized_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG and GIF
        if (imageistruecolor($image)) {
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
            imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
        }
    }

    return [
        'image' => $resized_image,
        'width' => $new_width,
        'height' => $new_height
    ];
}

/**
 * Process uploaded image file
 * 
 * This function handles the complete image processing pipeline for uploaded
 * images. It validates the file, detects the actual image type, optionally
 * resizes the image, and returns the processed image data ready for API
 * transmission or storage.
 * 
 * @param array $file Uploaded file array from $_FILES
 * @param string $max_size Maximum dimension for resizing ('original' or numeric string)
 * @return array|false Array with image data and MIME type, or false on error
 * 
 * @note Validates file size against MAX_FILE_SIZE constant
 * @note Supports JPEG, PNG, GIF, and WebP formats
 * @note If max_size is 'original', returns unprocessed image data
 * @note Otherwise, resizes to fit within max_size and converts to JPEG
 * @note Returns array with keys: 'image_data' (binary), 'mime_type' (string)
 * @see resizeImage() - Used for resizing the image
 * @see MAX_FILE_SIZE - Maximum allowed file size constant
 * @note Used in handleProfileAction() for image uploads
 */
function processUploadedImage($file, $max_size = '500') {
    // Validate file parameters
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Invalid file upload'];
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE || $file['size'] <= 0) {
        return ['error' => 'The file is too large. Maximum ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB allowed.'];
    }
    
    // Additional MIME type validation
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($detected_type, $allowed_types)) {
        return ['error' => 'Unsupported file type detected: ' . $detected_type];
    }

    // Check if it's an image
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $image_types)) {
        return ['error' => 'Unsupported file type. Please upload an image file.'];
    }

    // Try to detect actual image type from file content
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return ['error' => "Failed to detect image type from the uploaded file."];
    }

    $detected_mime = $image_info['mime'];

    // Create image resource from uploaded file
    $image = null;
    switch ($detected_mime) {
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
            return ['error' => "Unsupported image type: " . htmlspecialchars($detected_mime)];
    }

    if ($image === false) {
        return ['error' => "Failed to read the uploaded " . $file['type'] . " image."];
    }

    // Process image based on max_size setting
    if ($max_size === 'original') {
        // Send original image without processing
        $image_data = file_get_contents($file['tmp_name']);
        if ($image_data === false) {
            return ['error' => 'Failed to read the uploaded image.'];
        }
        $mime_type = $detected_mime;
    } else {
        // Resize the image
        $resize_result = resizeImage($image, intval($max_size));
        $resized_image = $resize_result['image'];
        $new_width = $resize_result['width'];
        $new_height = $resize_result['height'];

        // Copy the original image to the resized image
        imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, imagesx($image), imagesy($image));

        // Save resized image to temporary file as JPEG
        $temp_image_path = tempnam(sys_get_temp_dir(), 'DocMindAI_') . '.jpg';
        $success = imagejpeg($resized_image, $temp_image_path, 85);

        if (!$success) {
            return ['error' => 'Failed to process the uploaded image.'];
        }

        // Read the resized image data
        $image_data = file_get_contents($temp_image_path);
        if ($image_data === false) {
            return ['error' => 'Failed to read the processed image.'];
        }

        // Clean up temporary file
        unlink($temp_image_path);
        $mime_type = 'image/jpeg';

        // Clean up image resources
        imagedestroy($image);
        imagedestroy($resized_image);
    }

    return [
        'image_data' => $image_data,
        'mime_type' => $mime_type
    ];
}

/**
 * Preprocess image for better OCR results
 * 
 * This function applies various image processing techniques to optimize an
 * image for Optical Character Recognition (OCR). It includes resizing,
 * grayscale conversion, thresholding, and dilation to improve text
 * recognition accuracy.
 * 
 * @param string $image_path Path to the original image
 * @param bool $apply_threshold Whether to apply Otsu's thresholding (default: false)
 * @param bool $apply_dilation Whether to apply morphological dilation (default: false)
 * @return string|false Path to preprocessed image or false on error
 * 
 * @note Uses Otsu's method for automatic threshold calculation
 * @note Dilation helps connect broken text characters
 * @note Output is always PNG format for lossless quality
 * @note Temporary file must be cleaned up by caller
 * @see resizeImage() - Used for initial image resizing
 * @note Used for OCR preprocessing of document images
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
    
    // Resize image
    $resize_result = resizeImage($image);
    $resized_image = $resize_result['image'];
    $new_width = $resize_result['width'];
    $new_height = $resize_result['height'];

    // Preserve transparency for PNG
    if ($image_info[2] === IMAGETYPE_PNG) {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
        $transparent = imagecolorallocatealpha($resized_image, 255, 255, 255, 127);
        imagefilledrectangle($resized_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // Resize image with proper color copying
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
 * Extract text from various document formats
 * 
 * This function extracts text content from various document formats using
 * appropriate external tools. It supports Microsoft Word documents (DOC/DOCX),
 * PDF files, OpenDocument Text files (ODT), and plain text files.
 * 
 * @param string $file_path Path to the document file
 * @param string $mime_type MIME type of the file
 * @return string|false Extracted text or false on error
 * 
 * @note Uses external tools: antiword, catdoc, docx2txt, pdftotext, odt2txt, pandoc
 * @note Falls back to pandoc if specific tools are not available or fail
 * @note Cleans up error messages and stderr output from tool execution
 * @note Requires appropriate tools to be installed on the system
 * @see handleProfileAction() - Uses this for document processing
 * @note Used for processing uploaded documents in profile actions
 */
function extractTextFromDocument($file_path, $mime_type) {
    // Try specific tools based on file type
    $text = false;
    // Common binary paths to check
    $bin_paths = ['/usr/bin/', '/usr/local/bin/'];

    // Attempt to extract text based on MIME type
    switch ($mime_type) {
        case 'application/msword': // .doc
            // Check for antiword or catdoc in common locations
            for ($i = 0; $i < count($bin_paths); $i++) {
                $bin_path = $bin_paths[$i];
                if (file_exists($bin_path . 'antiword')) {
                    $text = shell_exec($bin_path . 'antiword -f -w 0 ' . escapeshellarg($file_path) . ' 2>&1');
                    break;
                } elseif (file_exists($bin_path . 'catdoc')) {
                    $text = shell_exec($bin_path . 'catdoc -a -dutf-8 -w ' . escapeshellarg($file_path) . ' 2>&1');
                    break;
                }
            }
            break;
        
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': // .docx
            for ($i = 0; $i < count($bin_paths); $i++) {
                $bin_path = $bin_paths[$i];
                if (file_exists($bin_path . 'docx2txt')) {
                    $text = shell_exec($bin_path . 'docx2txt < ' . escapeshellarg($file_path) . ' 2>&1');
                    break;
                }
            }
            break;

        case 'application/pdf': // .pdf
            for ($i = 0; $i < count($bin_paths); $i++) {
                $bin_path = $bin_paths[$i];
                if (file_exists($bin_path . 'pdftotext')) {
                    $text = shell_exec($bin_path . 'pdftotext -layout ' . escapeshellarg($file_path) . ' - 2>&1');
                    break;
                }
            }
            break;

        case 'application/vnd.oasis.opendocument.text': // .odt
            for ($i = 0; $i < count($bin_paths); $i++) {
                $bin_path = $bin_paths[$i];
                if (file_exists($bin_path . 'odt2txt')) {
                    $text = shell_exec($bin_path . 'odt2txt --encoding=UTF-8 ' . escapeshellarg($file_path) . ' 2>&1');
                    break;
                }
            }
            break;

        case 'text/plain': // .txt
        case 'text/markdown': // .md
            $text = file_get_contents($file_path);
            break;
    }

    // Return text if successfully extracted
    return $text;
}

/**
 * Scrape URL content with Chrome browser simulation
 * 
 * This function fetches web page content by simulating a Chrome browser
 * request. It handles cookies, redirects, gzip encoding, and includes
 * appropriate headers to bypass basic bot detection.
 * 
 * @param string $url URL to scrape
 * @return string|false Page content or false on error
 * 
 * @note Uses cURL with Chrome user agent and browser-like headers
 * @note Handles gzip compression automatically
 * @note Stores cookies in temporary file for session management
 * @note Follows up to 5 redirects with 30-second timeout
 * @note Cleans up temporary cookie file after request
 * @see executeHelper() - Uses this for web scraping helper
 * @note Used for the 'web_scraper' helper in profile actions
 */
function scrapeUrl($url) {
    // Create a temporary file to store cookies
    $cookie_file = tempnam(sys_get_temp_dir(), 'scp_cookies');

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);  // Store cookies
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file); // Send cookies
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Accept-Encoding: gzip, deflate',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    ]);

    // Execute request
    $content = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        curl_close($ch);
        unlink($cookie_file);
        return false;
    }

    // Close cURL session
    curl_close($ch);

    // Clean up cookie file
    unlink($cookie_file);

    // Handle gzip encoding
    if (strpos($content, "\x1f\x8b") === 0) {
        $content = gzdecode($content);
    }

    return $content;
}

/**
 * Run lynx command to get text content from URL
 * 
 * This function executes lynx with specific options to extract clean text
 * content from a web page URL
 * 
 * @param string $url URL to process with lynx
 * @return string|false Text content or false on error
 */
function runLynxCommand($url) {
    // Validate URL first
    $processed_url = processUrl($url);
    if (!$processed_url['valid']) {
        return false;
    }
    $url = $processed_url['data'];
    $chromeUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    if (file_exists('/usr/bin/lynx')) {
        return shell_exec('lynx -dump -force_html -width=80 -nolist -nobold -nocolor -useragent="' . $chromeUA . '" ' . escapeshellarg($url) . ' 2>&1');
    } elseif (file_exists('/usr/local/bin/lynx')) {
        return shell_exec('lynx -dump -force_html -width=80 -nolist -nobold -nocolor -useragent="' . $chromeUA . '" ' . escapeshellarg($url) . ' 2>&1');
    }
    
    return false;
}

/**
 * Extract images from PDF file
 * 
 * This function extracts images from a PDF file using either the Imagick or
 * Gmagick PHP extensions. It processes the first page of the PDF and returns
 * the image data in PNG format for further processing (e.g., OCR).
 * 
 * @param string $pdf_path Path to the PDF file
 * @return array|false Array of image data or false on error
 * 
 * @note Uses Imagick extension if available, falls back to Gmagick
 * @note Extracts only the first page for OCR processing
 * @note Sets resolution to 200 DPI for better quality
 * @note Returns image data in PNG format
 * @note Returns array of image data blobs (currently only first page)
 * @note Used for PDF document processing in profile actions
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
 * Get human-readable explanation for HTTP error codes
 * 
 * This function provides user-friendly explanations for common HTTP error
 * codes, making API error messages more understandable for end users.
 * 
 * @param int $http_code HTTP status code
 * @return string Explanation of the error
 * 
 * @note Covers common HTTP error codes from 400 to 504
 * @note Returns generic message for unknown error codes
 * @see getAvailableModels() - Uses this for API error reporting
 * @see callLLMApi() - Uses this for API error reporting
 */
function getHttpErrorExplanation($http_code) {
    $explanations = [
        400 => 'Bad Request - The request was invalid or cannot be served.',
        401 => 'Unauthorized - Authentication is required and has failed or not yet been provided.',
        403 => 'Forbidden - The server understood the request but refuses to authorize it.',
        404 => 'Not Found - The requested resource could not be found.',
        408 => 'Request Timeout - The server timed out waiting for the request.',
        429 => 'Too Many Requests - You have sent too many requests in a given amount of time.',
        500 => 'Internal Server Error - The server encountered an unexpected condition.',
        502 => 'Bad Gateway - The server received an invalid response from the upstream server.',
        503 => 'Service Unavailable - The server is not ready to handle the request.',
        504 => 'Gateway Timeout - The server did not receive a timely response from the upstream server.'
    ];
    
    return isset($explanations[$http_code]) ? $explanations[$http_code] : "HTTP error $http_code";
}

/**
 * Fetch available models from the LLM server API
 * 
 * This function queries the LLM API endpoint to retrieve a list of available
 * AI models. It handles authentication, error handling, and optional filtering
 * of model names. Vision models are automatically detected and labeled.
 * 
 * @param string $api_endpoint The API endpoint URL
 * @param string $api_key The API key (if required)
 * @param string $filter_regex Regular expression to filter models (optional)
 * @return array List of available models or error array
 * 
 * @note Makes GET request to /models endpoint
 * @note Uses Bearer token authentication
 * @note Filters models by regex pattern if provided
 * @note Automatically detects and labels vision models
 * @note Returns models sorted alphabetically by name
 * @note Returns ['error' => message] on failure
 * @see handleGetModels() - Uses this to fetch models
 * @see getHttpErrorExplanation() - Used for error messages
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
    
    if (curl_errno($ch)) {
        $error = 'Connection error: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    } elseif ($http_code !== 200) {
        $error = 'API error: ' . getHttpErrorExplanation($http_code);
        curl_close($ch);
        return ['error' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['data'])) {
        return ['error' => 'Invalid API response format: ' . json_last_error_msg()];
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
                $models[$name] = str_replace(':', ' ', $name) . ' (Vision)';
            } else {
                $models[$name] = str_replace(':', ' ', $name);
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
 * This function makes a POST request to the LLM chat completion API endpoint.
 * It handles authentication, request formatting, error handling, and response
 * parsing. The function supports long-running requests with a 5-minute timeout.
 * 
 * @param string $api_endpoint_chat The chat API endpoint URL
 * @param array $data The request data (messages, model, etc.)
 * @param string $api_key The API key (if required)
 * @param string $provider The LLM provider name
 * @return array|false API response data or error array
 * 
 * @note Uses POST method with JSON payload
 * @note Uses Bearer token authentication
 * @note Has 5-minute timeout for long-running requests
 * @note Follows up to 3 redirects
 * @note Returns ['error' => message] on failure
 * @see handleProfileAction() - Uses this for profile processing
 * @see getHttpErrorExplanation() - Used for error messages
 */
function callLLMApi($api_endpoint_chat, $data, $api_key = '', $provider = 'ollama') {
    // Special handling for DuckDuckGo provider
    if ($provider === 'duck') {
        $query = '';
        foreach ($data['messages'] as $message) {
            if ($message['role'] === 'user') {
                $query = $message['content'];
                break;
            }
        }
        return callDuckDuckGoChat($query, $data['model'] ?? 'gpt-4o-mini');
    }
    
    // Make API request for other providers
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
        $error = 'API error: ' . getHttpErrorExplanation($http_code);
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
 * Handle common URL validation and processing
 * 
 * This function validates and sanitizes URL input for web scraping or
 * other URL-based operations. It ensures the URL is properly formatted
 * and includes a protocol (http:// or https://).
 * 
 * @param string $url The URL to validate
 * @return array Processing result with validation status
 * 
 * @note Trims whitespace from URL
 * @note Validates URL format using filter_var()
 * @note Requires http:// or https:// protocol
 * @note Returns array with 'valid', 'error', and 'data' keys
 * @note Used for validating URLs before web scraping
 * @see scrapeUrl() - Uses validated URLs for web scraping
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
 * Send JSON response and exit
 * 
 * This function sends a JSON response with appropriate headers and
 * terminates script execution. It's designed for API endpoints that
 * need to return JSON data to clients.
 * 
 * @param array $data Response data to encode as JSON
 * @param bool $is_api_request Whether this is an API request (default: false)
 * 
 * @note Sets CORS header to allow cross-origin requests
 * @note Sets Content-Type to application/json
 * @note Uses json_encode() to convert data to JSON
 * @note Calls exit() to terminate script execution
 * @note Only sends response if $is_api_request is true
 * @see handleApiRequest() - Uses this for API responses
 * @see handleProfileAction() - Uses this for profile responses
 */
function sendJsonResponse($data, $is_api_request = false) {
    if ($is_api_request) {
        // Security headers
        header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Prevent caching of sensitive data
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        global $DEBUG_MODE;
        if ($DEBUG_MODE) {
            // Add form data to debug output if available
            if (!empty($_POST)) {
                $data['debug']['form_data'] = $_POST;
            }
        }

        echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        exit;
    }
}

/**
 * Search PubMed for articles matching the query
 * 
 * This function searches the PubMed database for medical literature
 * matching a given query. It uses the NCBI E-utilities API to perform
 * the search and retrieves article IDs, then fetches detailed information
 * for each article.
 * 
 * @param string $query Search query (e.g., "diabetes treatment")
 * @param int $max_results Maximum number of results to return (default: 5)
 * @return array|false Array of articles or false on error
 * 
 * @note Uses NCBI E-utilities API (esearch.fcgi)
 * @note Returns articles sorted by relevance
 * @note Fetches detailed information for each article
 * @note Returns false on API errors or no results
 * @see fetchArticleDetails() - Used to get article details
 * @see executeHelper() - Uses this for 'medical_literature_search' helper
 */
function searchPubMed($query, $max_results = 5) {
    // PubMed API endpoint
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi';

    // Prepare search parameters
    $params = [
        'db' => 'pubmed',
        'term' => $query,
        'retmax' => $max_results,
        'retmode' => 'json',
        'sort' => 'relevance'
    ];

    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);

    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['esearchresult']['idlist'])) {
        return false;
    }

    $ids = $response_data['esearchresult']['idlist'];

    if (empty($ids)) {
        return [];
    }

    // Fetch details for each article
    return fetchArticleDetails($ids);
}

/**
 * Fetch detailed information for PubMed articles
 * 
 * This function retrieves detailed metadata for PubMed articles using their
 * PubMed IDs. It queries the NCBI E-utilities API and parses the XML response
 * to extract article information including title, authors, journal, year,
 * and abstract.
 * 
 * @param array $ids Array of PubMed IDs
 * @return array|false Array of article details or false on error
 * 
 * @note Uses NCBI E-utilities API (efetch.fcgi)
 * @note Returns XML response and parses with SimpleXML
 * @note Limits authors to first 5 + "et al." if more
 * @note Extracts PMID, title, authors, journal, year, abstract
 * @note Returns false on API errors or parsing failures
 * @see searchPubMed() - Uses this to get article details
 * @note Used for the 'medical_literature_search' helper
 */
function fetchArticleDetails($ids) {
    // PubMed API endpoint for fetching details
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi';

    // Prepare fetch parameters
    $params = [
        'db' => 'pubmed',
        'id' => implode(',', $ids),
        'retmode' => 'xml'
    ];

    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);

    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // Parse XML response
    $xml = simplexml_load_string($response);

    if ($xml === false) {
        return false;
    }

    $articles = [];

    // Process each article
    foreach ($xml->PubmedArticle as $article) {
        $parsed_article = [];

        // Extract PMID
        $parsed_article['pmid'] = (string)$article->MedlineCitation->PMID;

        // Extract title
        $parsed_article['title'] = (string)$article->MedlineCitation->Article->ArticleTitle;

        // Extract authors
        $authors = [];
        foreach ($article->MedlineCitation->Article->AuthorList->Author as $author) {
            $author_name = (string)$author->LastName;
            if (!empty($author->Initials)) {
                $author_name .= ' ' . (string)$author->Initials;
            }
            $authors[] = $author_name;
        }
        $parsed_article['authors'] = array_slice($authors, 0, 5);
        if (count($authors) > 5) {
            $parsed_article['authors'][] = 'et al.';
        }

        // Extract journal
        $parsed_article['journal'] = (string)$article->MedlineCitation->Article->Journal->Title;

        // Extract year
        $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->Year;
        if (empty($parsed_article['year'])) {
            $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->MedlineDate;
        }

        // Extract abstract
        $parsed_article['abstract'] = (string)$article->MedlineCitation->Article->Abstract->AbstractText;

        $articles[] = $parsed_article;
    }

    return $articles;
}

/**
 * Extract JSON from AI response content
 * 
 * This function attempts to extract JSON data from AI response content.
 * It looks for JSON between code fences (```json ... ```) or any JSON
 * object in the content. If the JSON is malformed, it attempts to fix
 * common issues like trailing commas and unquoted keys.
 * 
 * @param string $content AI response content
 * @return array|null Extracted JSON data or null if not found/invalid
 * 
 * @note First tries to find JSON between code fences
 * @note Then tries to find any JSON object in the content
 * @note Attempts to fix common JSON formatting issues
 * @note Returns null if no valid JSON is found
 * @see processProfileResponse() - Uses this for JSON output profiles
 * @note Used for extracting structured data from AI responses
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
 * Load resource from JSON file
 * 
 * This function loads and parses a JSON configuration file. It handles
 * file existence checks, reading errors, and JSON parsing errors,
 * returning appropriate error messages for each case.
 * 
 * @param string $filename Path to the JSON file
 * @return array Decoded JSON data or error array
 * 
 * @note Checks if file exists before attempting to read
 * @note Handles file read errors gracefully
 * @note Validates JSON format and reports parsing errors
 * @note Returns ['error' => message] on failure
 * @note Used for loading profiles.json, languages.json, etc.
 * @see handleApiRequest() - Uses this for loading profiles
 * @see buildProfilePrompt() - Uses this for loading profiles and languages
 */
function loadResourceFromJson($filename) {
    // Check if resource file exists
    if (!file_exists($filename)) {
        $resource_name = ucfirst(str_replace('.json', '', $filename));
        return ['error' => $resource_name . ' configuration file not found'];
    }

    // Read and decode JSON file
    $json_content = file_get_contents($filename);
    if ($json_content === false) {
        return ['error' => 'Failed to read ' . $filename . ' configuration file'];
    }

    // Decode JSON content
    $resource_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON format in ' . $filename . ' configuration: ' . json_last_error_msg()];
    }

    // Return the decoded resource data
    return $resource_data;
}

// =========================================================================
// Core API Functions
// =========================================================================

/**
 * Handle incoming API requests by validating and routing to appropriate handlers
 *
 * This function acts as the main API router. It:
 * 1. Determines the requested action from GET/POST parameters
 * 2. Validates the action against a whitelist of available actions
 * 3. Dynamically loads valid actions from config.json
 * 4. Routes to the appropriate handler function based on the action
 *
 * @return void Terminates script execution after sending response
 *
 * @see handleGetModels() - Handles 'get_models' action
 * @see handleGetPrompts() - Handles 'get_prompts' action
 * @see handleToolAction() - Handles tool-specific actions
 * @see sendJsonResponse() - Sends JSON responses
 * @see loadResourceFromJson() - Loads tool configuration
 */
function handleApiRequest() {
    // Extract and sanitize action from request parameters
    $action = isset($_REQUEST['action']) ? preg_replace('/[^a-zA-Z0-9_.]/', '', $_REQUEST['action']) : null;
    $method = $_SERVER['REQUEST_METHOD'];

    // If action is not specified, return an error response
    if ($action === null) {
        sendJsonResponse(['error' => 'No action specified'], true);
    }

    // Route to appropriate handler based on action
    switch ($action) {
        case 'get_models':
            // Handle request for available AI models
            handleGetModels();
            break;
        case 'get_prompts':
            // Handle request for available prompts
            handleGetPrompts();
            break;
        default:
            // For other actions, treat them as tool-specific actions
            handleToolAction($action);
            break;
    }
}

/**
 * Handle the 'get_models' API action - retrieve available AI models
 * 
 * This function fetches the list of available AI models from the configured
 * LLM API endpoint. It uses server-side configuration values and provides
 * fallback defaults if the API call fails.
 * 
 * @global string $LLM_API_ENDPOINT_CHAT The chat API endpoint URL
 * @global string $LLM_API_KEY The API authentication key
 * @global string $LLM_API_FILTER Optional regex filter for model names
 * 
 * @return void Sends JSON response with models list or error
 * 
 * @see getAvailableModels() - Fetches models from API
 * @see sendJsonResponse() - Sends the final response
 * 
 * @note If API call fails, returns default models for fallback functionality
 */
function handleGetModels() {
    global $LLM_API_ENDPOINT_CHAT, $LLM_API_KEY, $LLM_API_FILTER;

    // Extract base endpoint from chat endpoint
    $api_endpoint = preg_replace('/\/chat\/completions$/', '', $LLM_API_ENDPOINT_CHAT);
    $api_key = $LLM_API_KEY;
    $filter = $LLM_API_FILTER;

    // Validate required parameters
    if (empty($api_endpoint)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    // Fetch available models from the API
    $models = getAvailableModels($api_endpoint, $api_key, $filter);

    // Check if models contain an error
    if (isset($models['error'])) {
        // If API call fails, use provider-specific default models as fallback
        $provider = $LLM_PROVIDER ?? 'ollama';
        switch ($provider) {
            case 'openai':
                $models = [
                    'gpt-4' => 'GPT-4',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                    'gpt-4-turbo' => 'GPT-4 Turbo'
                ];
                break;
            case 'openrouter':
                $models = [
                    'openai/gpt-4' => 'GPT-4 (OpenRouter)',
                    'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo (OpenRouter)',
                    'anthropic/claude-3-opus' => 'Claude 3 Opus (OpenRouter)',
                    'meta-lllama/llama-3-70b-instruct' => 'Llama 3 70B (OpenRouter)'
                ];
                break;
            case 'together':
                $models = [
                    'togethercomputer/Llama-2-7b-chat' => 'Llama 2 7B (Together)',
                    'togethercomputer/Llama-2-13b-chat' => 'Llama 2 13B (Together)',
                    'togethercomputer/Llama-2-70b-chat' => 'Llama 2 70B (Together)'
                ];
                break;
            case 'cerebras':
                $models = [
                    'cerebras/Cerebras-3.1' => 'Cerebras 3.1',
                    'cerebras/Cerebras-2.0' => 'Cerebras 2.0'
                ];
                break;
            case 'duck':
                $models = [
                    'gpt-4o-mini' => 'GPT-4o Mini (DuckDuckGo)',
                    'gpt-4o' => 'GPT-4o (DuckDuckGo)'
                ];
                break;
            default:
                // Default fallback for ollama and local
                $models = [
                    'gemma3:1b' => 'Gemma 3 (1B)',
                    'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
                ];
        }
    }

    // Sort models alphabetically by key (model name) for consistent UI display
    ksort($models);

    // Send the models list as JSON response
    sendJsonResponse(['models' => $models], true);
}

/**
 * Handle the 'get_prompts' API action - retrieve available prompt templates
 * 
 * This function scans the 'prompts' directory for prompt template files and
 * returns them as a structured list. It supports multiple file formats and
 * provides clean labels for display.
 * 
 * @return void Sends JSON response with prompts list
 * 
 * @see sendJsonResponse() - Sends the final response
 * 
 * @note Supported file extensions: .txt, .md, .xml
 * @note Prompts are loaded from the 'prompts' subdirectory
 * @note File names are converted to readable labels (e.g., 'medical_report' -> 'Medical Report')
 */
function handleGetPrompts() {
    // Load prompts from files in the prompts directory
    $prompts = [];

    // Check if prompts directory exists
    if (is_dir('prompts')) {
        // Get all files in the prompts directory
        $files = scandir('prompts');

        foreach ($files as $file) {
            // Check for .txt, .md, or .xml extensions
            if (preg_match('/\.(txt|md|xml)$/i', $file)) {
                // Prevent path traversal
                $file_path = 'prompts/' . basename($file);

                // Use filename (without extension) as the key
                $key = pathinfo(basename($file), PATHINFO_FILENAME);

                // Use filename as label (clean up for display)
                $label = ucwords(str_replace(['_', '-'], ' ', $key));

                // Read file content as prompt
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    $prompts[$key] = [
                        'label' => $label,
                        'prompt' => $content
                    ];
                }
            }
        }
    }

    sendJsonResponse(['prompts' => $prompts], true);
}

/**
 * Load a tool definition JSON file.
 *
 * @param string $tool_id     The tool identifier (e.g., "soap")
 *
 * @return array Decoded JSON as an associative array, or an error array:
 *               ['error' => 'description']
 */
function getToolConfig(string $tool_id): array {
    // Use the cached config to find the category for this tool
    $config = getConfigData();
    if (isset($config['error'])) {
        return ['error' => 'Failed to load configuration: ' . $config['error']];
    }
    if (!isset($config['tools'][$tool_id])) {
        return ['error' => "Tool '$tool_id' not found in configuration"];
    }
    $category_id = $config['tools'][$tool_id] ?? null;
    if ($category_id === null) {
        return ['error' => "Category not found for tool '$tool_id' in configuration"];
    }
    // Build the expected file path
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'tools' .
                 DIRECTORY_SEPARATOR . $category_id .
                 DIRECTORY_SEPARATOR . $tool_id . '.json';

    // Verify the file exists and is readable
    if (!is_file($file_path) || !is_readable($file_path)) {
        return ['error' => "Tool configuration file not found: $category_id/$tool_id.json"];
    }

    // Read the file contents
    $json = file_get_contents($file_path);
    if ($json === false) {
        return ['error' => "Failed to read tool configuration file: $category_id/$tool_id.json"];
    }

    // Decode JSON
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON in tool configuration: ' . json_last_error_msg()];
    }

    // Ensure the tool ID and category are included in the returned data if not set
    if (!isset($data['id'])) {
        $data['id'] = $tool_id;
    }
    if (!isset($data['category'])) {
        $data['category'] = $category_id;
    }

    // Return the decoded tool configuration
    return $data;
}

/**
 * Handle tool-specific API actions - main processing pipeline
 * 
 * This function implements the complete processing pipeline for tool-specific actions:
 * 1. Validates API configuration and tool existence
 * 2. Processes form data and file uploads (images and documents)
 * 3. Builds prompts based on tool configuration
 * 4. Prepares and sends API request to LLM
 * 5. Processes and returns the response
 * 
 * @param string $tool_id The identifier of the tool to process
 * @global string $LLM_API_ENDPOINT_CHAT The chat completion API endpoint
 * @global string $LLM_API_KEY The API authentication key
 * 
 * @return void Sends JSON response with processed results or errors
 * 
 * @see loadResourceFromJson() - Loads tool configuration
 * @see processUploadedImage() - Handles image uploads
 * @see extractTextFromDocument() - Extracts text from documents
 * @see buildToolPrompt() - Constructs the prompt
 * @see callLLMApi() - Calls the LLM API
 * @see processToolResponse() - Processes the API response
 * @see sendJsonResponse() - Sends final response
 * 
 * @note Supports both text documents and images
 * @note Includes debug information in response
 * @note Sets CORS headers for cross-origin requests
 */
function handleToolAction($tool_id) {
    global $LLM_API_ENDPOINT_CHAT, $LLM_API_KEY;
    global $DEBUG_MODE;

    // Validate required parameters
    if (empty($LLM_API_ENDPOINT_CHAT)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    // Load configuration (cached)
    $config_data = getConfigData();
    if (isset($config_data['error'])) {
        sendJsonResponse(['error' => $config_data['error']], true);
    }

    // Load the tool configuration
    $tool = getToolConfig($tool_id);
    if (isset($tool['error'])) {
        sendJsonResponse(['error' => $tool['error']], true);
    }

    // Get form data
    $form_data = $_POST;

    // Handle file upload if present
    $file_content = '';
    $is_image = false;
    $image_data = null;
    $mime_type = null;
    $file_info = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        // Store file metadata for debug
        $file_info = [
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'error' => $file['error']
        ];

        // Check if it's an image
        $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $image_types)) {
            $is_image = true;
            // Get max image size from form or use default
            $max_size = isset($_POST['max_image_size']) ? $_POST['max_image_size'] : '500';

            // Process uploaded image
            $image_processing_result = processUploadedImage($file, $max_size);

            if (isset($image_processing_result['error'])) {
                sendJsonResponse(['error' => $image_processing_result['error']], true);
            } else {
                $image_data = $image_processing_result['image_data'];
                $mime_type = $image_processing_result['mime_type'];
                $file_info['processed_mime_type'] = $mime_type;
                $file_info['extracted_length'] = strlen($image_data);
            }
        } else {
            // Text document - try to extract text
            $file_content = extractTextFromDocument($file['tmp_name'], $file['type']);
            if ($file_content === false) {
                sendJsonResponse(['error' => 'Failed to extract text from the uploaded document. Please ensure you have the required tools installed (antiword, catdoc, pdftotext, odt2txt, or pandoc).'], true);
            } else {
                // Clean up the text content
                $file_content = trim($file_content);
                // Remove BOM if present
                $file_content = preg_replace('/^\xEF\xBB\xBF/', '', $file_content);
                // Normalize line endings
                $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
                $file_info['extracted_length'] = strlen($file_content);
            }
        }
    }

    // Validate required fields
    $required_fields = [];
    foreach ($tool['form']['fields'] as $field) {
        if (isset($field['required']) && $field['required'] && !isset($form_data[$field['name']])) {
            $required_fields[] = $field['name'];
        }
    }

    if (!empty($required_fields)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $required_fields)], true);
    }

    // Check if tool has a helper specification
    if (isset($tool['helper']) && !empty($tool['helper'])) {
        $helper_output = executeHelper($tool['helper'], $form_data);
        if ($helper_output !== false) {
            // Add helper output to form data as 'content' field
            $form_data['content'] = $helper_output;
        }
    }

    // Build the prompt based on tool type
    $prompt = buildToolPrompt($tool, $form_data);

    // Prepare API request data
    // TODO: use a default model if not specified in form_data
    $api_data = [
        'model' => $form_data['model'] ?? '',
        'messages' => [],
        'stream' => false
    ];

    // Add file content
    if (!empty($file_content)) {
        $prompt .= $file_content;
    }
    $api_data['messages'][] = [
        'role' => 'user',
        'content' => $prompt
        ];

    // If it's an image, add it as a separate message with image data
    if ($is_image && $image_data !== null) {
        // Convert image to base64
        $base64_image = base64_encode($image_data);
        $api_data['messages'][] = [
            'role' => 'user',
            'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64_image"]]
            ]
        ];
    }

    // Call LLM API
    $response = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY, $provider);

    if (!isset($response['error'])) {
        // Process and return the response
        $result = processToolResponse($tool, $response);
    } else {
        // If there was an error, include the prompt and raw response for debugging
        $result = [
            'error' => $response['error']
        ];
        if ($DEBUG_MODE) {
            $result['debug']['response'] = $response;
        }
    }

    if ($DEBUG_MODE) {
        // Add API request payload to debug output
        $result['debug']['api_data'] = $api_data;

        // Add uploaded file metadata to debug output (if a file was provided)
        if ($file_info !== null) {
            $result['debug']['file_info'] = $file_info;
        }
    }

    // Add the final prompt to debug output for reference
    $result['debug']['prompt'] = $prompt;


    // Set CORS headers
    //header('Access-Control-Allow-Origin: *');
    //header('Content-Type: application/json');

    // Send the final JSON response
    sendJsonResponse($result, true);
}

/**
 * Execute a helper based on tool configuration
 * 
 * This function acts as a helper dispatcher, executing different helpers based on the
 * tool's helper specification. It validates input parameters and returns helper
 * output or false on error.
 * 
 * @param string $helper_name Name of the helper to execute (e.g., 'web_scraper', 'medical_literature_search')
 * @param array $form_data Form data containing helper parameters (URL, query, etc.)
 * @return string|false Helper output as string, or false on error/invalid parameters
 * 
 * @see scrapeUrl() - Web scraping helper
 * @see searchPubMed() - Medical literature search helper
 * 
 * @note Currently supports:
 *       - 'web_scraper': Scrapes content from a URL
 *       - 'medical_literature_search': Searches PubMed for medical articles
 * @note All helpers validate their required parameters before execution
 */
function executeHelper($helper_name, $form_data) {
    switch ($helper_name) {
        case 'web_scraper':
            // Check if URL is provided
            // Validate URL format, length, and protocol
            if (empty($form_data['url']) || 
                strlen($form_data['url']) > 2048 || 
                !filter_var($form_data['url'], FILTER_VALIDATE_URL) || 
                !in_array(parse_url($form_data['url'], PHP_URL_SCHEME), ['http','https'])) {
                return false;
            }
            // Scrape the URL content
            $content = scrapeUrl($form_data['url']);
            // Return scraped content or false on failure
            return $content;

        case 'medical_literature_search':
            // Check if query is provided
            if (empty($form_data['query'])) {
                return false;
            }
            // Validate query length
            if (strlen($form_data['query']) > 500) {
                return false;
            }
            // Search PubMed for articles
            $articles = searchPubMed($form_data['query'], 5); // Get top 5 results
            // Return false if no articles found
            if ($articles === false) {
                return false;
            } elseif (empty($articles)) {
                return false;
            }
            // Convert articles to JSON format
            return json_encode($articles, JSON_PRETTY_PRINT);

        case 'lynx':
            // Check if URL is provided
            if (empty($form_data['url'])) {
                return false;
            }
            // Validate URL format
            if (!filter_var($form_data['url'], FILTER_VALIDATE_URL)) {
                return false;
            }
            // Run lynx command to extract clean text
            $content = runLynxCommand($form_data['url']);
            // Return content if any, otherwise false
            return empty($content) ? false : $content;
        
        default:
            return false;
    }
}

// ------------------------------------------------------------
// 1️⃣  Cached config getter – loads config.json only once per request
// ------------------------------------------------------------
/**
 * Retrieve the parsed config.json data.
 * The JSON file is read and decoded only on the first call;
 * subsequent calls return the cached array.
 *
 * @return array Config data (or ['error'=>...] on failure)
 */
function getConfigData(): array {
    static $cached_config = null;

    // If we already have it, return immediately
    if ($cached_config !== null) {
        return $cached_config;
    }

    // Load and decode the file (reuse existing helper)
    $cached_config = loadResourceFromJson('config.json');

    // Ensure we always return an array
    if (!is_array($cached_config)) {
        $cached_config = ['error' => 'Failed to load config.json'];
    }

    return $cached_config;
}


/**
 * Build the final prompt for a tool, now using the cached config for language instructions.
 *
 * @param array $tool      Tool definition (from tools/<cat>/<tool>.json)
 * @param array $form_data User‑submitted form data
 *
 * @return string Fully‑rendered prompt
 */
function buildToolPrompt($tool, $form_data) {
    // Load config (cached) to get language instructions, etc.
    $config_data = getConfigData();
    if (isset($config_data['error'])) {
        // Fallback to English if config cannot be read
        $language_instruction = 'Respond in English.';
    } else {
        $lang_key = $form_data['language'] ?? 'en';
        $language_instruction = $config_data['languages'][$lang_key]['instruction']
                               ?? 'Respond in English.';
    }

    // Determine which prompt to use
    if (isset($tool['prompts']) && is_array($tool['prompts'])) {
        $selected_key = $form_data['prompt'] ?? '';
        $prompt = (!empty($selected_key) && isset($tool['prompts'][$selected_key]))
                ? $tool['prompts'][$selected_key]
                : reset($tool['prompts']);
    } elseif (!empty($tool['prompt'])) {
        $prompt = $tool['prompt'];
    } elseif (isset($form_data['prompt']) && !empty($form_data['prompt'])) {
        $prompt = $form_data['prompt'];
    } else {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Convert array prompts to text if needed
    if (is_array($prompt)) {
        $prompt = convertPromptArrayToText($prompt);
    }

    // Replace language placeholder
    $prompt = str_replace('{language_instruction}', $language_instruction, $prompt);

    // Replace any other {field} placeholders with submitted values
    foreach ($form_data as $key => $value) {
        $placeholder = '{' . $key . '}';
        if (strpos($prompt, $placeholder) !== false) {
            $prompt = str_replace($placeholder, $value, $prompt);
        }
    }

    // Return the final prompt
    return $prompt;
}

/**
 * Convert prompt array to formatted text recursively
 * 
 * This function handles two types of array structures:
 * 1. Associative arrays (JSON objects): Converted to uppercase headers with content
 * 2. Sequential arrays: Converted to newline-separated text
 * 
 * @param array $prompt_array The prompt array to convert
 * @param int $indent_level Current indentation level (for nested arrays)
 * @return string Formatted text version of the prompt
 * 
 * @note Example associative array:
 *       ['role' => 'You are a helpful assistant', 'rules' => ['rule1', 'rule2']]
 *       becomes:
 *       ROLE
 *       You are a helpful assistant
 *       
 *       RULES
 *       rule1
 *       rule2
 * @note Example sequential array:
 *       ['line1', 'line2', 'line3'] becomes "line1\nline2\nline3"
 */
function convertPromptArrayToText($prompt_array, $indent_level = 0) {
    // Base indentation for nested structures
    $indent = str_repeat('  ', $indent_level);
    // Check if this is an associative array (JSON object)
    if (array_keys($prompt_array) !== range(0, count($prompt_array) - 1)) {
        // Convert JSON object to formatted text
        $prompt_text = '';
        // Process each key-value pair
        foreach ($prompt_array as $key => $value) {
            // Replace underscores with spaces in key names
            $formatted_key = str_replace('_', ' ', $key);
            // Use UPPERCASE for keys in level 0 and Title Case for nested keys
            if ($indent_level > 0) {
                $formatted_key = ucwords($formatted_key);
            } else {
                $formatted_key = strtoupper($formatted_key);
            }
            // Handle array values recursively
            if (is_array($value)) {
                $value = convertPromptArrayToText($value, $indent_level + 1);
            }
            // Indent value based on nesting level
            $value = $indent . str_replace("\n", "\n" . $indent, $value);
            // Append to prompt text with formatting
            $prompt_text .= $formatted_key . "\n" . $value . "\n\n";
        }
        // Trim trailing newlines and return
        return rtrim($prompt_text, "\n");
    } else {
        // If prompt is a sequential array, process each item recursively
        $result = [];
        foreach ($prompt_array as $item) {
            if (is_array($item)) {
                $result[] = convertPromptArrayToText($item, $indent_level);
            } else {
                $result[] = $indent . $item;
            }
        }
        // Join items with newlines and return
        return implode("\n", $result);
    }
}

/**
 * Process tool response and extract JSON if required
 * 
 * This function processes the LLM API response based on tool configuration.
 * It can optionally extract JSON data from the response content if the tool
 * specifies JSON output format.
 * 
 * @param array $tool The tool configuration array containing output specifications
 * @param array $api_response The raw API response from the LLM
 * @return array Processed result containing tool info, response, and optional JSON data
 * 
 * @see loadResourceFromJson() - Loads tool configuration
 * @see extractJsonFromResponse() - Extracts JSON from response content
 * 
 * @note If tool has 'output' => 'json', attempts to extract JSON from response
 * @note Returns array with keys: 'tool', 'response', and optionally 'json'
 */
function processToolResponse($tool, $api_response) {
    $result = [
        'tool' => $tool['id'],
        'response' => $api_response
    ];

    // Check if tool has 'output' key set to 'json'
    if (isset($tool['output']) && $tool['output'] === 'json') {
        // Try to extract JSON if present
        $content = $api_response['choices'][0]['message']['content'] ?? '';
        $json_data = extractJsonFromResponse($content);
        if ($json_data) {
            $result['json'] = $json_data;
        }
    }

    // Return the processed result
    return $result;
}

/**
 * Display web interface with CSRF protection
 * 
 * This function serves the main web interface with security measures including:
 * - Generating a CSRF token for form submissions
 * - Ensuring proper session handling
 * 
 * @return void Includes and executes index.html content with security token
 * 
 * @note The CSRF token helps prevent cross-site request forgery attacks
 */
function displayWebInterface() {
    // Secure session configuration
    $session_name = 'SECURE_SESSION_ID';
    session_name($session_name);
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        session_regenerate_id(true);
    }

    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Store token in constant for frontend
    define('CSRF_TOKEN', $_SESSION['csrf_token']);
    
    include 'index.html';
}



// Get provider configuration (default to ollama if not specified)
$provider = $LLM_PROVIDER ?? 'ollama';
$llm_config = getLlmProviderConfig($provider);

// Create chat endpoint URL by appending the chat completions path
$LLM_API_ENDPOINT_CHAT = $llm_config['endpoint'] . '/chat/completions';
$LLM_API_KEY = $llm_config['key'];


/**
 * Maximum file upload size (10MB)
 */
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Load default configuration from config.php.example if not already loaded
if (!isset($LLM_API_KEY)) {
    $LLM_API_KEY = '';
}
if (!isset($DEFAULT_TEXT_MODEL)) {
    $DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
}
if (!isset($DEFAULT_VISION_MODEL)) {
    $DEFAULT_VISION_MODEL = 'gemma3:4b';
}
if (!isset($LLM_API_FILTER)) {
    $LLM_API_FILTER = '/free/';
}
if (!isset($CHAT_HISTORY_LENGTH)) {
    $CHAT_HISTORY_LENGTH = 10;
}
if (!isset($DEBUG_MODE)) {
    $DEBUG_MODE = false;
}
if (!isset($ALLOWED_ORIGINS)) {
    $ALLOWED_ORIGINS = ['*'];
}
if (!isset($MAX_FILE_SIZE)) {
    $MAX_FILE_SIZE = 10 * 1024 * 1024;
}
if (!isset($ALLOWED_FILE_TYPES)) {
    $ALLOWED_FILE_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/markdown',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text'
    ];
}

// =========================================================================
// Main Request Router
// =========================================================================

// Determine if this is an API request
// Check for: 1) action parameter in GET/POST, 2) JSON Accept header
$is_api_request = isset($_GET['action']) || isset($_POST['action']) ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle API actions
if ($is_api_request) {
    handleApiRequest();
    exit;
}

// Default web interface
displayWebInterface();

?>
