<?php
/**
 * DocMind AI - Unified Gateway
 *
 * Central endpoint for all AI tool operations, serving both as API and web interface
 *
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.1
 * @license GPL 3
 */

// =========================================================================
// Initialization & Configuration
// =========================================================================

// Load configuration if available
if (file_exists('config.php')) {
    include 'config.php';
}

// -------------------------------------------------------------------------
// Provider resolution
//
// New style: $LLM_PROVIDERS array + $LLM_ACTIVE_PROVIDER key.
// Legacy style: flat $LLM_API_ENDPOINT / $LLM_API_KEY / $LLM_API_FILTER
//               variables — still supported when the new array is absent.
//
// getActiveProvider() is the single source of truth for the rest of the
// backend; all other code reads from it instead of touching globals directly.
// -------------------------------------------------------------------------

/**
 * Return the configuration for the currently active LLM provider.
 *
 * Prefers the new $LLM_PROVIDERS / $LLM_ACTIVE_PROVIDER style. Falls back
 * to the legacy flat variables so existing single-provider installs continue
 * to work without any changes to config.php.
 *
 * Returned array always contains:
 *   name          string  Human-readable provider label
 *   endpoint      string  Base URL (no trailing slash)
 *   key           string  Bearer token (may be empty)
 *   filter        string  PCRE regex for model filtering (may be empty)
 *   default_model string  Model used when none is submitted / no /models API
 *   models_api    bool    Whether the provider exposes GET /models
 *   chat_endpoint string  Full chat-completions URL (appended automatically)
 *
 * @return array Provider config with all keys guaranteed to be present.
 */
function getActiveProvider(): array {
    static $resolved = null;
    if ($resolved !== null) return $resolved;

    $providers      = $GLOBALS['LLM_PROVIDERS']       ?? null;
    $active_key     = $GLOBALS['LLM_ACTIVE_PROVIDER'] ?? null;

    if (is_array($providers) && !empty($providers)) {
        // Validate the active key; fall back to first provider if invalid
        if ($active_key === null || !isset($providers[$active_key])) {
            $active_key = array_key_first($providers);
        }

        $p = $providers[$active_key];

        $resolved = [
            'id'            => $active_key,
            'name'          => $p['name']          ?? $active_key,
            'endpoint'      => rtrim($p['endpoint'] ?? '', '/'),
            'key'           => $p['key']            ?? '',
            'filter'        => $p['filter']         ?? '',
            'default_model' => $p['default_model']  ?? '',
            'models_api'    => $p['models_api']     ?? true,
            'driver'        => $p['driver']         ?? 'openai',
        ];
    } else {
        // Legacy flat-variable fallback
        $endpoint = rtrim($GLOBALS['LLM_API_ENDPOINT'] ?? '', '/');
        $resolved = [
            'id'            => 'default',
            'name'          => 'Default',
            'endpoint'      => $endpoint,
            'key'           => $GLOBALS['LLM_API_KEY']          ?? '',
            'filter'        => $GLOBALS['LLM_API_FILTER']       ?? '',
            'default_model' => $GLOBALS['DEFAULT_TEXT_MODEL']   ?? '',
            'models_api'    => true,
            'driver'        => 'openai',
        ];
    }

    // chat_endpoint is only used by the 'openai' driver; DDG uses its own URL
    $resolved['chat_endpoint'] = $resolved['endpoint'] . '/chat/completions';

    return $resolved;
}

// Guard application-level globals that are not provider-specific
$CHAT_HISTORY_LENGTH = $CHAT_HISTORY_LENGTH ?? 10;
$DEBUG_MODE          = $DEBUG_MODE          ?? false;
$ALLOWED_ORIGINS     = $ALLOWED_ORIGINS     ?? ['*'];

/**
 * Maximum file upload size (10MB)
 */
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// =========================================================================
// Image Helper Functions
// =========================================================================

/**
 * Resize an image while maintaining aspect ratio.
 *
 * Allocates a new canvas of the correct size and copies the source image
 * into it with proper resampling. Transparency is preserved for sources
 * that support an alpha channel; a white background is used otherwise.
 *
 * @param GdImage $image    GD image resource to resize.
 * @param int     $max_size Maximum dimension (width or height) in pixels.
 * @return array{image: GdImage, width: int, height: int}
 */
function resizeImage($image, int $max_size = 1000): array {
    $src_w = imagesx($image);
    $src_h = imagesy($image);

    // Calculate destination dimensions, clamping to max_size
    if ($src_w > $max_size || $src_h > $max_size) {
        $ratio   = min($max_size / $src_w, $max_size / $src_h);
        $new_w   = max(1, (int)($src_w * $ratio));
        $new_h   = max(1, (int)($src_h * $ratio));
    } else {
        $new_w = $src_w;
        $new_h = $src_h;
    }

    $dst = imagecreatetruecolor($new_w, $new_h);

    // Preserve alpha channel when the source has one
    if (imageistruecolor($image)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
        imagealphablending($dst, true);
    } else {
        // Palette image — fill with white
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $white);
    }

    // FIX #11: resize + copy are now done together inside this function,
    // removing the split-responsibility that caused bug #1.
    imagecopyresampled($dst, $image, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);

    return ['image' => $dst, 'width' => $new_w, 'height' => $new_h];
}

/**
 * Process an uploaded image file.
 *
 * Validates the upload, detects the real MIME type from file bytes,
 * optionally resizes the image, and returns binary image data with its
 * MIME type ready for base64-encoding and API transmission.
 *
 * @param array  $file     Entry from $_FILES.
 * @param string $max_size 'original' to skip resizing, or a pixel count string.
 * @return array{image_data: string, mime_type: string}|array{error: string}
 */
function processUploadedImage(array $file, string $max_size = '500'): array {
    // Validate upload integrity
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'Invalid file upload'];
    }

    // Validate file size
    if ($file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'The file is too large. Maximum ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB allowed.'];
    }

    // FIX #3: Use only the finfo-based check (reads actual file bytes).
    // The client-supplied $file['type'] is untrusted and is not checked.
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo         = new finfo(FILEINFO_MIME_TYPE);
    $detected_type = $finfo->file($file['tmp_name']);

    if (!in_array($detected_type, $allowed_types, true)) {
        return ['error' => 'Unsupported file type: ' . htmlspecialchars($detected_type)];
    }

    // Create GD resource based on the verified MIME type
    $image = null;
    switch ($detected_type) {
        case 'image/jpeg': $image = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png':  $image = imagecreatefrompng($file['tmp_name']);  break;
        case 'image/gif':  $image = imagecreatefromgif($file['tmp_name']);  break;
        case 'image/webp': $image = imagecreatefromwebp($file['tmp_name']); break;
    }

    if ($image === false || $image === null) {
        return ['error' => 'Failed to decode the uploaded image.'];
    }

    // Return original bytes without touching GD
    if ($max_size === 'original') {
        $image_data = file_get_contents($file['tmp_name']);
        // FIX #4: free the GD resource on the 'original' path too
        imagedestroy($image);
        if ($image_data === false) {
            return ['error' => 'Failed to read the uploaded image.'];
        }
        return ['image_data' => $image_data, 'mime_type' => $detected_type];
    }

    // Resize path — resizeImage() now performs the copy internally (fix #11)
    $resized      = resizeImage($image, (int)$max_size);
    $resized_img  = $resized['image'];

    $temp_path = tempnam(sys_get_temp_dir(), 'DocMindAI_') . '.jpg';
    $ok        = imagejpeg($resized_img, $temp_path, 85);

    imagedestroy($image);
    imagedestroy($resized_img);

    if (!$ok) {
        return ['error' => 'Failed to encode the resized image.'];
    }

    $image_data = file_get_contents($temp_path);
    unlink($temp_path);

    if ($image_data === false) {
        return ['error' => 'Failed to read the processed image.'];
    }

    return ['image_data' => $image_data, 'mime_type' => 'image/jpeg'];
}

/**
 * Preprocess an image file for better OCR accuracy.
 *
 * Resizes, converts to grayscale, and optionally applies Otsu binarisation
 * and morphological dilation. Saves the result as a PNG and returns its path.
 * The caller is responsible for deleting the temporary file.
 *
 * @param string $image_path      Path to the source image.
 * @param bool   $apply_threshold Apply Otsu binarisation.
 * @param bool   $apply_dilation  Apply 3×3 morphological dilation.
 * @return string|false Path to the preprocessed PNG, or false on error.
 */
function preprocessImageForOCR(string $image_path, bool $apply_threshold = false, bool $apply_dilation = false) {
    $image_info = @getimagesize($image_path);
    if ($image_info === false) {
        return false;
    }

    $image = null;
    switch ($image_info[2]) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($image_path); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($image_path);  break;
        case IMAGETYPE_GIF:  $image = imagecreatefromgif($image_path);  break;
        case IMAGETYPE_WEBP: $image = imagecreatefromwebp($image_path); break;
        default: return false;
    }

    if ($image === false) {
        return false;
    }

    // FIX #1 + #11: resizeImage() now copies pixel data internally — no
    // undefined $width/$height variables, no second imagecopyresampled call.
    // FIX #2: the canvas is created inside resizeImage() with correct
    // alpha handling; we no longer overwrite it here before data is copied.
    $resized     = resizeImage($image);
    $resized_img = $resized['image'];
    $new_w       = $resized['width'];
    $new_h       = $resized['height'];
    imagedestroy($image);

    // Convert to greyscale in-place
    imagefilter($resized_img, IMG_FILTER_GRAYSCALE);

    // Optional: Otsu binarisation
    if ($apply_threshold) {
        // Build greyscale histogram
        $histogram = array_fill(0, 256, 0);
        for ($y = 0; $y < $new_h; $y++) {
            for ($x = 0; $x < $new_w; $x++) {
                $r = ($rgb = imagecolorat($resized_img, $x, $y)) >> 16 & 0xFF;
                $histogram[$r]++;
            }
        }

        // Otsu's method
        $total = $new_w * $new_h;
        $sum   = 0;
        for ($i = 0; $i < 256; $i++) { $sum += $i * $histogram[$i]; }

        $sumB    = 0;
        $wB      = 0;
        $varMax  = 0;
        $threshold = 0;

        for ($i = 0; $i < 256; $i++) {
            $wB += $histogram[$i];
            if ($wB === 0) continue;
            $wF = $total - $wB;
            if ($wF === 0) break;
            $sumB += $i * $histogram[$i];
            $mB   = $sumB / $wB;
            $mF   = ($sum - $sumB) / $wF;
            $var  = $wB * $wF * ($mB - $mF) ** 2;
            if ($var > $varMax) { $varMax = $var; $threshold = $i; }
        }

        for ($y = 0; $y < $new_h; $y++) {
            for ($x = 0; $x < $new_w; $x++) {
                $r     = imagecolorat($resized_img, $x, $y) >> 16 & 0xFF;
                $c     = ($r >= $threshold) ? 255 : 0;
                imagesetpixel($resized_img, $x, $y, imagecolorallocate($resized_img, $c, $c, $c));
            }
        }
    }

    // Optional: 3×3 morphological dilation
    if ($apply_dilation) {
        $dilated = imagecreatetruecolor($new_w, $new_h);
        imagecopy($dilated, $resized_img, 0, 0, 0, 0, $new_w, $new_h);
        $black = imagecolorallocate($dilated, 0, 0, 0);

        for ($y = 1; $y < $new_h - 1; $y++) {
            for ($x = 1; $x < $new_w - 1; $x++) {
                $found = false;
                for ($ky = -1; $ky <= 1 && !$found; $ky++) {
                    for ($kx = -1; $kx <= 1 && !$found; $kx++) {
                        if ((imagecolorat($resized_img, $x + $kx, $y + $ky) >> 16 & 0xFF) === 0) {
                            $found = true;
                        }
                    }
                }
                if ($found) {
                    imagesetpixel($dilated, $x, $y, $black);
                }
            }
        }

        imagedestroy($resized_img);
        $resized_img = $dilated;
    }

    $temp_path = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
    $ok        = imagepng($resized_img, $temp_path, 9);
    imagedestroy($resized_img);

    return $ok ? $temp_path : false;
}

// =========================================================================
// Document & URL Helpers
// =========================================================================

/**
 * Extract plain text from an uploaded document.
 *
 * Tries well-known command-line tools in /usr/bin and /usr/local/bin.
 * stderr is redirected to /dev/null so tool warnings never contaminate the
 * extracted content that will be sent to the LLM.
 *
 * @param string $file_path Absolute path to the uploaded temporary file.
 * @param string $mime_type Verified MIME type of the document.
 * @return string|false Extracted text, or false if extraction failed.
 */
function extractTextFromDocument(string $file_path, string $mime_type) {
    $bin_paths = ['/usr/bin/', '/usr/local/bin/'];
    $text      = false;

    switch ($mime_type) {
        case 'application/msword':
            foreach ($bin_paths as $bp) {
                if (file_exists($bp . 'antiword')) {
                    // FIX #10: redirect stderr to /dev/null on all shell_exec calls
                    $text = shell_exec($bp . 'antiword -f -w 0 ' . escapeshellarg($file_path) . ' 2>/dev/null');
                    break;
                }
                if (file_exists($bp . 'catdoc')) {
                    $text = shell_exec($bp . 'catdoc -a -dutf-8 -w ' . escapeshellarg($file_path) . ' 2>/dev/null');
                    break;
                }
            }
            break;

        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            foreach ($bin_paths as $bp) {
                if (file_exists($bp . 'docx2txt')) {
                    $text = shell_exec($bp . 'docx2txt < ' . escapeshellarg($file_path) . ' 2>/dev/null');
                    break;
                }
            }
            break;

        case 'application/pdf':
            foreach ($bin_paths as $bp) {
                if (file_exists($bp . 'pdftotext')) {
                    $text = shell_exec($bp . 'pdftotext -layout ' . escapeshellarg($file_path) . ' - 2>/dev/null');
                    break;
                }
            }
            break;

        case 'application/vnd.oasis.opendocument.text':
            foreach ($bin_paths as $bp) {
                if (file_exists($bp . 'odt2txt')) {
                    $text = shell_exec($bp . 'odt2txt --encoding=UTF-8 ' . escapeshellarg($file_path) . ' 2>/dev/null');
                    break;
                }
            }
            break;

        case 'text/plain':
        case 'text/markdown':
            $text = file_get_contents($file_path);
            break;
    }

    return $text;
}

/**
 * Validate and normalise a URL string.
 *
 * @param string $url Raw URL from user input.
 * @return array{valid: bool, error: string|null, data: string|null}
 */
function processUrl(string $url): array {
    $data = trim($url);

    if (!filter_var($data, FILTER_VALIDATE_URL)) {
        return ['valid' => false, 'error' => 'Invalid URL format. Please include http:// or https://', 'data' => null];
    }

    $scheme = parse_url($data, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return ['valid' => false, 'error' => 'Only http:// and https:// URLs are supported.', 'data' => null];
    }

    return ['valid' => true, 'error' => null, 'data' => $data];
}

/**
 * Fetch a web page simulating a Chrome browser request.
 *
 * @param string $url A pre-validated URL.
 * @return string|false Page HTML, or false on failure.
 */
function scrapeUrl(string $url) {
    $cookie_file = tempnam(sys_get_temp_dir(), 'scp_cookies');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_COOKIEJAR      => $cookie_file,
        CURLOPT_COOKIEFILE     => $cookie_file,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ],
    ]);

    $content = curl_exec($ch);
    $err     = curl_errno($ch);
    curl_close($ch);
    @unlink($cookie_file);

    if ($err || $content === false) {
        return false;
    }

    // Decode gzip if needed
    if (is_string($content) && str_starts_with($content, "\x1f\x8b")) {
        $content = gzdecode($content);
    }

    return $content;
}

/**
 * Extract clean text from a URL using lynx.
 *
 * @param string $url Raw URL from user input (validated internally).
 * @return string|false Extracted text, or false on failure.
 */
function runLynxCommand(string $url) {
    $processed = processUrl($url);
    if (!$processed['valid']) {
        return false;
    }

    $safe_url = $processed['data'];
    $ua       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
    $args     = '-dump -force_html -width=80 -nolist -nobold -nocolor -useragent=' . escapeshellarg($ua) . ' ' . escapeshellarg($safe_url) . ' 2>/dev/null';

    foreach (['/usr/bin/lynx', '/usr/local/bin/lynx'] as $bin) {
        if (file_exists($bin)) {
            $out = shell_exec($bin . ' ' . $args);
            return (is_string($out) && $out !== '') ? $out : false;
        }
    }

    return false;
}

// =========================================================================
// PDF / Imaging
// =========================================================================

/**
 * Rasterise the first page of a PDF to PNG using Imagick or Gmagick.
 *
 * @param string $pdf_path Absolute path to the PDF.
 * @return array<string>|false Array of binary PNG blobs (currently one page), or false.
 */
function extractImagesFromPDF(string $pdf_path) {
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($pdf_path . '[0]'); // first page only
            $imagick->setImageFormat('png');
            $imagick->stripImage();
            $blob = $imagick->getImageBlob();
            $imagick->destroy();
            return [$blob];
        } catch (Exception $e) {
            // fall through
        }
    }

    if (extension_loaded('gmagick')) {
        try {
            $gmagick = new Gmagick();
            $gmagick->setresolution(200, 200);
            $gmagick->readImage($pdf_path . '[0]');
            $gmagick->setimageformat('png');
            $blob = $gmagick->getimageblob();
            $gmagick->clear();
            return [$blob];
        } catch (Exception $e) {
            return false;
        }
    }

    return false;
}

// =========================================================================
// HTTP / API Utilities
// =========================================================================

/**
 * Return a human-readable description for an HTTP status code.
 *
 * @param int $http_code HTTP status code.
 * @return string Description string.
 */
function getHttpErrorExplanation(int $http_code): string {
    $explanations = [
        400 => 'Bad Request — the request was malformed.',
        401 => 'Unauthorized — authentication required or failed.',
        403 => 'Forbidden — the server refused to fulfil the request.',
        404 => 'Not Found — the requested resource does not exist.',
        408 => 'Request Timeout — the server timed out.',
        429 => 'Too Many Requests — rate limit exceeded.',
        500 => 'Internal Server Error — unexpected server condition.',
        502 => 'Bad Gateway — invalid response from upstream server.',
        503 => 'Service Unavailable — server not ready.',
        504 => 'Gateway Timeout — no timely upstream response.',
    ];

    return $explanations[$http_code] ?? "HTTP error $http_code";
}

/**
 * Query the LLM server for the list of available models.
 *
 * @param string $api_endpoint Base API endpoint (without /models).
 * @param string $api_key      Bearer token (may be empty).
 * @param string $filter_regex Optional regex to include only matching model IDs.
 * @return array<string,string>|array{error: string} Map of id => label, or error.
 */
function getAvailableModels(string $api_endpoint, string $api_key = '', string $filter_regex = ''): array {
    $ch = curl_init($api_endpoint . '/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['error' => 'Connection error: ' . curl_strerror($curl_err)];
    }
    if ($http_code !== 200) {
        return ['error' => 'API error: ' . getHttpErrorExplanation($http_code)];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['data'])) {
        return ['error' => 'Invalid API response: ' . json_last_error_msg()];
    }

    $models = [];
    foreach ($data['data'] as $model) {
        if (!isset($model['id'])) continue;

        // Gemini's OpenAI-compat layer may prefix IDs with 'models/' — strip it
        // so the bare model name (e.g. 'gemini-2.5-flash') is used in API calls.
        $id = $model['id'];
        if (str_starts_with($id, 'models/')) {
            $id = substr($id, strlen('models/'));
        }

        if ($filter_regex !== '' && !preg_match($filter_regex, $id)) continue;

        // Build a readable label from the model ID:
        //   'llama-3.3-70b-versatile'       → 'Llama 3.3 70b Versatile'
        //   'gemini-2.5-flash'               → 'Gemini 2.5 Flash'
        //   'mistral-small-latest'           → 'Mistral Small Latest'
        //   'meta-llama/Llama-3.3-70B-...'  → 'Llama 3.3 70b ...' (basename only)
        $label_base = strpos($id, '/') !== false ? substr($id, strrpos($id, '/') + 1) : $id;
        $label      = ucwords(str_replace(['-', '_', ':'], ' ', strtolower($label_base)));

        if (stripos($id, 'vision') !== false || stripos($id, 'vl') !== false) {
            $label .= ' (Vision)';
        }

        $models[$id] = $label;
    }

    ksort($models);
    return $models;
}

/**
 * Send a chat-completion request to the LLM API.
 *
 * @param string $endpoint Full chat completions URL.
 * @param array  $data     Request payload (model, messages, …).
 * @param string $api_key  Bearer token (may be empty).
 * @return array API response decoded, or array{error: string} on failure.
 */
function callLLMApi(string $endpoint, array $data, string $api_key = ''): array {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key],
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['error' => 'Connection error: ' . curl_strerror($curl_err)];
    }
    if ($http_code !== 200) {
        return ['error' => 'API error: ' . getHttpErrorExplanation($http_code)];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid API response: ' . json_last_error_msg()];
    }

    return $decoded;
}

/**
 * Send a JSON response and terminate.
 *
 * Sets appropriate HTTP headers including a validated CORS origin,
 * cache-control directives, and security headers.
 *
 * @param array $data           Response payload.
 * @param bool  $is_api_request Only sends if true (prevents accidental output).
 */
function sendJsonResponse(array $data, bool $is_api_request = false): void {
    if (!$is_api_request) {
        return;
    }

    // FIX #7: Validate Origin against the configured allowlist instead of
    // reflecting it verbatim, which would bypass CORS entirely.
    global $ALLOWED_ORIGINS;
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $ALLOWED_ORIGINS ?? ['*'];

    if (in_array('*', $allowed, true)) {
        $cors_header = '*';
    } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
        $cors_header = $origin;
        header('Vary: Origin');
    } else {
        $cors_header = $allowed[0] ?? '';
    }

    header('Access-Control-Allow-Origin: ' . $cors_header);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    // FIX #15: additional security headers
    header('Referrer-Policy: no-referrer');
    header('Content-Security-Policy: default-src \'none\'');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    global $DEBUG_MODE;
    if ($DEBUG_MODE && !empty($_POST)) {
        $data['debug']['form_data'] = $_POST;
    }

    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================================
// PubMed Helpers
// =========================================================================

/**
 * Search PubMed for articles matching a query string.
 *
 * @param string $query       Search terms.
 * @param int    $max_results Maximum articles to return.
 * @return array<array>|false Article list, empty array if none found, or false on error.
 */
function searchPubMed(string $query, int $max_results = 5) {
    $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query([
        'db'      => 'pubmed',
        'term'    => $query,
        'retmax'  => $max_results,
        'retmode' => 'json',
        'sort'    => 'relevance',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)',
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) return false;

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['esearchresult']['idlist'])) return false;

    $ids = $data['esearchresult']['idlist'];
    if (empty($ids)) return [];

    return fetchArticleDetails($ids);
}

/**
 * Fetch detailed metadata for PubMed article IDs.
 *
 * @param array $ids PubMed IDs.
 * @return array<array>|false Article detail list, or false on error.
 */
function fetchArticleDetails(array $ids) {
    $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query([
        'db'      => 'pubmed',
        'id'      => implode(',', $ids),
        'retmode' => 'xml',
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)',
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200) return false;

    $xml = @simplexml_load_string($response);
    if ($xml === false) return false;

    $articles = [];
    foreach ($xml->PubmedArticle as $article) {
        $mc   = $article->MedlineCitation;
        $art  = $mc->Article;

        $authors = [];
        foreach ($art->AuthorList->Author as $author) {
            $name = (string)$author->LastName;
            if (!empty($author->Initials)) $name .= ' ' . (string)$author->Initials;
            $authors[] = $name;
        }
        if (count($authors) > 5) {
            $authors = array_slice($authors, 0, 5);
            $authors[] = 'et al.';
        }

        $year = (string)$art->Journal->JournalIssue->PubDate->Year;
        if ($year === '') $year = (string)$art->Journal->JournalIssue->PubDate->MedlineDate;

        $articles[] = [
            'pmid'     => (string)$mc->PMID,
            'title'    => (string)$art->ArticleTitle,
            'authors'  => $authors,
            'journal'  => (string)$art->Journal->Title,
            'year'     => $year,
            'abstract' => (string)$art->Abstract->AbstractText,
        ];
    }

    return $articles;
}

// =========================================================================
// JSON Extraction
// =========================================================================

/**
 * Extract the first valid JSON object from an LLM response string.
 *
 * Tries in order:
 *   1. JSON inside a ```json … ``` fence
 *   2. First balanced {} block in the text
 *   3. Light cleanup (trailing commas, unquoted keys) and retry
 *
 * FIX #14: the fallback regex is now a depth-aware scan that finds the first
 * complete, balanced JSON object rather than using a greedy /\{.*\}/s match.
 *
 * @param string $content LLM response text.
 * @return array|null Decoded data, or null if no valid JSON was found.
 */
function extractJsonFromResponse(string $content): ?array {
    // 1. Prefer an explicit ```json … ``` fence
    if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
        $json_str = $m[1];
    } else {
        // 2. Find the first balanced { … } block — depth-aware, not greedy
        $json_str = extractFirstJsonObject($content);
        if ($json_str === null) return null;
    }

    $json_str = trim($json_str);
    $result   = json_decode($json_str, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Light cleanup: trailing commas, unquoted keys, single-quoted values
        $json_str = preg_replace('/,\s*([\]}])/m', '$1', $json_str);
        $json_str = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_str);
        $json_str = preg_replace('/:\s*\'([^\']*)\'/', ':"$1"', $json_str);
        $json_str = preg_replace('/\s+/', ' ', $json_str);
        $result   = json_decode($json_str, true);
    }

    return (json_last_error() === JSON_ERROR_NONE) ? $result : null;
}

/**
 * Return the first balanced { … } substring from $text, or null.
 *
 * Uses a character-by-character depth counter so it handles nested objects
 * correctly without the over-matching caused by /\{.*\}/s.
 *
 * @param string $text Source text.
 * @return string|null
 */
function extractFirstJsonObject(string $text): ?string {
    $len   = strlen($text);
    $start = null;
    $depth = 0;

    for ($i = 0; $i < $len; $i++) {
        $c = $text[$i];
        if ($c === '{') {
            if ($start === null) $start = $i;
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0 && $start !== null) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }

    return null;
}

// =========================================================================
// Configuration & Resource Loading
// =========================================================================

/**
 * Load and decode a JSON file, returning an error array on any failure.
 *
 * @param string $filename Path to the JSON file.
 * @return array Decoded data, or ['error' => message].
 */
function loadResourceFromJson(string $filename): array {
    if (!file_exists($filename)) {
        return ['error' => ucfirst(str_replace('.json', '', $filename)) . ' configuration file not found'];
    }

    $json = file_get_contents($filename);
    if ($json === false) {
        return ['error' => 'Failed to read ' . $filename];
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON in ' . $filename . ': ' . json_last_error_msg()];
    }

    return $data;
}

/**
 * Return the parsed config.json, loading it only once per request.
 *
 * @return array Config data, or ['error' => message] on failure.
 */
function getConfigData(): array {
    static $cached = null;

    if ($cached !== null) return $cached;

    $cached = loadResourceFromJson('config.json');
    if (!is_array($cached)) {
        $cached = ['error' => 'Failed to load config.json'];
    }

    return $cached;
}

/**
 * Load and return the JSON configuration for a specific tool.
 *
 * Looks up the tool's category in config.json, then reads the file at
 * tools/<category>/<tool_id>.json.
 *
 * @param string $tool_id Tool identifier (e.g. 'soap', 'rdd').
 * @return array Tool config, or ['error' => message] on failure.
 */
function getToolConfig(string $tool_id): array {
    $config = getConfigData();
    if (isset($config['error'])) {
        return ['error' => 'Failed to load configuration: ' . $config['error']];
    }
    if (!isset($config['tools'][$tool_id])) {
        return ['error' => "Unknown tool: '$tool_id'"];
    }

    $category  = $config['tools'][$tool_id];
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'tools' .
                 DIRECTORY_SEPARATOR . $category .
                 DIRECTORY_SEPARATOR . $tool_id . '.json';

    if (!is_file($file_path) || !is_readable($file_path)) {
        return ['error' => "Tool configuration file not found: $category/$tool_id.json"];
    }

    $json = file_get_contents($file_path);
    if ($json === false) {
        return ['error' => "Failed to read tool configuration: $category/$tool_id.json"];
    }

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON in tool configuration: ' . json_last_error_msg()];
    }

    $data['id']       = $data['id']       ?? $tool_id;
    $data['category'] = $data['category'] ?? $category;

    return $data;
}

// =========================================================================
// Prompt Building
// =========================================================================

/**
 * Build the final prompt string for a tool submission.
 *
 * Selects the correct prompt (single or keyed multi-prompt), serialises any
 * array structure to labelled text, injects the language instruction, then
 * replaces all {field} placeholders with submitted form values.
 *
 * FIX #13: When a tool's JSON contains an `output_format` array as part of
 * its prompt structure, that array is serialised before placeholder
 * substitution occurs, so a user-submitted `output_format` form value
 * (e.g. "markdown") only replaces the {output_format} placeholder if it
 * actually appears as a bare placeholder in the prompt — it never clobbers
 * a prompt key named `output_format`.
 *
 * @param array $tool      Tool definition array.
 * @param array $form_data User-submitted form values.
 * @return string Fully-rendered prompt.
 */
function buildToolPrompt(array $tool, array $form_data): string {
    // Resolve language instruction from config
    $config = getConfigData();
    if (isset($config['error'])) {
        $lang_instruction = 'Respond in English.';
    } else {
        $lang_key         = $form_data['language'] ?? 'en';
        $lang_instruction = $config['languages'][$lang_key]['instruction'] ?? 'Respond in English.';
    }

    // Select the prompt source
    if (isset($tool['prompts']) && is_array($tool['prompts'])) {
        $key    = $form_data['prompt'] ?? '';
        $prompt = (!empty($key) && isset($tool['prompts'][$key]))
                ? $tool['prompts'][$key]
                : reset($tool['prompts']);
    } elseif (!empty($tool['prompt'])) {
        $prompt = $tool['prompt'];
    } elseif (!empty($form_data['prompt'])) {
        $prompt = $form_data['prompt'];
    } else {
        return 'Analyze the following input: ' . json_encode($form_data);
    }

    // Serialise array prompts to labelled text BEFORE doing any substitution.
    // This means embedded arrays like `output_format` are rendered as text
    // and cannot be accidentally overwritten by a same-named form field.
    if (is_array($prompt)) {
        $prompt = convertPromptArrayToText($prompt);
    }

    // Inject language instruction
    $prompt = str_replace('{language_instruction}', $lang_instruction, $prompt);

    // Replace {field} placeholders with submitted values
    foreach ($form_data as $key => $value) {
        if (is_string($value)) {
            $prompt = str_replace('{' . $key . '}', $value, $prompt);
        }
    }

    return $prompt;
}

/**
 * Recursively serialise a prompt array (object or list) to labelled text.
 *
 * Associative arrays become UPPERCASE headers (level 0) or Title Case headers
 * (nested), each followed by their value. Sequential arrays become newline-
 * separated lines.
 *
 * @param array $prompt_array The array to serialise.
 * @param int   $indent_level Current nesting depth.
 * @return string
 */
function convertPromptArrayToText(array $prompt_array, int $indent_level = 0): string {
    $indent   = str_repeat('  ', $indent_level);
    $is_assoc = array_keys($prompt_array) !== range(0, count($prompt_array) - 1);

    if ($is_assoc) {
        $parts = [];
        foreach ($prompt_array as $key => $value) {
            $label = str_replace('_', ' ', $key);
            $label = ($indent_level === 0) ? strtoupper($label) : ucwords($label);

            if (is_array($value)) {
                $value = convertPromptArrayToText($value, $indent_level + 1);
            }

            $indented = $indent . str_replace("\n", "\n" . $indent, (string)$value);
            $parts[]  = $label . "\n" . $indented;
        }
        return implode("\n\n", $parts);
    }

    // Sequential array — one line per item
    $lines = [];
    foreach ($prompt_array as $item) {
        $lines[] = is_array($item)
            ? convertPromptArrayToText($item, $indent_level)
            : $indent . $item;
    }
    return implode("\n", $lines);
}

// =========================================================================
// DuckDuckGo AI Chat Driver
//
// Provides free access to GPT-4o-mini, Claude 3 Haiku, Llama 3.1 70B and
// Mixtral 8x7B through DuckDuckGo's unofficial duck.ai API.
//
// The API uses a rotating token-pair scheme (X-Vqd-4 + x-vqd-hash-1).
// Tokens are obtained from a status endpoint and must be forwarded with
// every chat request. DDG rotates them per turn; the functions below cache
// the current pair in static variables for the lifetime of a PHP request.
//
// No API key is required. Rate limits are undocumented and subject to change.
// Not suitable for high-volume or production workloads.
// =========================================================================

/**
 * Known DuckDuckGo AI Chat models.
 * DDG has no /models endpoint; this list is the source of truth.
 */
const DDG_MODELS = [
    'gpt-4o-mini'                                        => 'GPT-4o Mini (OpenAI)',
    'claude-3-haiku-20240307'                            => 'Claude 3 Haiku (Anthropic)',
    'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo'      => 'Llama 3.1 70B (Meta)',
    'mistralai/Mixtral-8x7B-Instruct-v0.1'              => 'Mixtral 8x7B (Mistral)',
];

const DDG_STATUS_URL = 'https://duckduckgo.com/duckchat/v1/status';
const DDG_CHAT_URL   = 'https://duckduckgo.com/duckchat/v1/chat';
const DDG_UA         = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

/**
 * Fetch a fresh DDG token pair from the status endpoint.
 *
 * Returns ['vqd' => string, 'hash' => string] on success, or
 * ['error' => string] on failure.
 *
 * @return array
 */
function fetchDDGToken(): array {
    $ch = curl_init(DDG_STATUS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,       // we need response headers
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Accept: */*',
            'Referer: https://duckduckgo.com/',
            'Origin: https://duckduckgo.com',
            'User-Agent: ' . DDG_UA,
            'X-Vqd-Accept: 1',               // signals "give me a token"
            'Dnt: 1',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Dest: empty',
        ],
    ]);

    $raw       = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    curl_close($ch);

    if ($curl_err || $http_code !== 200 || $raw === false) {
        return ['error' => 'DDG token fetch failed (HTTP ' . $http_code . ')'];
    }

    // Parse headers from the raw response (header + body)
    $header_size = strpos($raw, "\r\n\r\n");
    $header_text = substr($raw, 0, $header_size);

    $vqd  = null;
    $hash = null;

    foreach (explode("\r\n", $header_text) as $line) {
        $lower = strtolower($line);
        if (str_starts_with($lower, 'x-vqd-4:')) {
            $vqd  = trim(substr($line, strlen('x-vqd-4:')));
        } elseif (str_starts_with($lower, 'x-vqd-hash-1:')) {
            $hash = trim(substr($line, strlen('x-vqd-hash-1:')));
        }
    }

    if (empty($vqd) || empty($hash)) {
        return ['error' => 'DDG token not found in status response headers'];
    }

    return ['vqd' => $vqd, 'hash' => $hash];
}

/**
 * Return the cached DDG token pair, fetching it on first call.
 *
 * Stores the pair in static variables so a single PHP request performs
 * at most one token fetch, even across multiple tool calls.
 * Pass $force = true to discard the cache and fetch a new pair (used
 * after a 401 response).
 *
 * @param bool $force Bypass the static cache.
 * @return array ['vqd' => string, 'hash' => string] or ['error' => string].
 */
function getDDGToken(bool $force = false): array {
    static $vqd  = null;
    static $hash = null;

    if ($force || $vqd === null || $hash === null) {
        $pair = fetchDDGToken();
        if (isset($pair['error'])) return $pair;
        $vqd  = $pair['vqd'];
        $hash = $pair['hash'];
    }

    return ['vqd' => $vqd, 'hash' => $hash];
}

/**
 * Send a chat request to DuckDuckGo AI Chat and return a response array
 * in the same shape as callLLMApi() so the rest of the backend is unaware
 * of the difference.
 *
 * Handles:
 *   - Token fetch / cache on first call
 *   - 401 retry with a fresh token (loop-based, max 2 attempts — no recursion)
 *   - SSE stream parsing and full-message accumulation
 *   - [DONE] sentinel detection
 *   - Normalised OpenAI-compatible response envelope
 *
 * @param array  $data  Request payload with 'model' and 'messages' keys.
 *                      Only the last user message is sent; DDG has no
 *                      multi-turn memory beyond its own session token.
 * @return array OpenAI-compatible response, or ['error' => string] on failure.
 */
function callDDGApi(array $data): array {
    // Extract model and the last user message content
    $model   = $data['model'] ?? array_key_first(DDG_MODELS);
    $messages = $data['messages'] ?? [];

    // Find the last user message to send
    $user_content = '';
    foreach (array_reverse($messages) as $msg) {
        if (($msg['role'] ?? '') === 'user') {
            $c = $msg['content'] ?? '';
            // Content can be a string or an array of blocks (vision)
            $user_content = is_string($c) ? $c : '';
            break;
        }
    }

    if (empty($user_content)) {
        return ['error' => 'DDG driver: no user message content to send'];
    }

    $payload = json_encode([
        'model'    => $model,
        'messages' => [['role' => 'user', 'content' => $user_content]],
    ]);

    $max_attempts = 2;

    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        $force_refresh = ($attempt > 1);   // fresh token on retry
        $tokens = getDDGToken($force_refresh);

        if (isset($tokens['error'])) {
            return ['error' => $tokens['error']];
        }

        $ch = curl_init(DDG_CHAT_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Accept: text/event-stream',
                'Content-Type: application/json',
                'Referer: https://duckduckgo.com/',
                'Origin: https://duckduckgo.com',
                'User-Agent: ' . DDG_UA,
                'X-Vqd-4: '      . $tokens['vqd'],
                'x-vqd-hash-1: ' . $tokens['hash'],
                'Dnt: 1',
                'Sec-Fetch-Site: same-origin',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Dest: empty',
            ],
        ]);

        $raw_response = curl_exec($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err     = curl_errno($ch);
        curl_close($ch);

        if ($curl_err) {
            return ['error' => 'DDG connection error: ' . curl_strerror($curl_err)];
        }

        if ($http_code === 401 && $attempt < $max_attempts) {
            // Token expired — loop back with force_refresh = true
            continue;
        }

        if ($http_code !== 200) {
            return ['error' => 'DDG API error (HTTP ' . $http_code . ')'];
        }

        // ── Parse SSE stream ─────────────────────────────────────────────
        $full_message = '';

        foreach (explode("\n", $raw_response) as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, 'data:')) continue;

            $data_str = trim(substr($line, strlen('data:')));

            // Explicit [DONE] sentinel — end of stream
            if ($data_str === '[DONE]') break;

            $chunk = @json_decode($data_str, true);
            if (!is_array($chunk)) continue;

            // DDG sends {"message": "..."} chunks (not OpenAI delta format)
            if (isset($chunk['message']) && is_string($chunk['message'])) {
                $full_message .= $chunk['message'];
            }
        }

        // ── Wrap in OpenAI-compatible envelope ───────────────────────────
        return [
            'id'      => 'ddg-' . uniqid(),
            'object'  => 'chat.completion',
            'model'   => $model,
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $full_message],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ];
    }

    return ['error' => 'DDG API: max retry attempts exceeded'];
}

/**
 * Return the DDG model list in the same format as getAvailableModels().
 *
 * @return array<string, string> Map of model ID => human label.
 */
function getAvailableModelsDDG(): array {
    return DDG_MODELS;
}

// =========================================================================
// Core API Functions
// =========================================================================

/**
 * Route an incoming API request to the appropriate handler.
 *
 * FIX #8: unknown tool IDs are rejected by getToolConfig() before any
 * processing begins, so no tool-specific work happens for invalid IDs.
 */
function handleApiRequest(): void {
    $action = isset($_REQUEST['action'])
        ? preg_replace('/[^a-zA-Z0-9_.]/', '', (string)$_REQUEST['action'])
        : null;

    if ($action === null || $action === '') {
        sendJsonResponse(['error' => 'No action specified'], true);
    }

    switch ($action) {
        case 'get_models': handleGetModels();  break;
        case 'get_prompts': handleGetPrompts(); break;
        default:           handleToolAction($action); break;
    }
}

/**
 * Return the list of available LLM models to the client.
 *
 * When the active provider exposes a /models endpoint (models_api = true),
 * the list is fetched live and filtered by the provider's regex. Otherwise,
 * or when the API call fails, only the provider's default_model is returned.
 * The response also includes the active provider name so the UI can label
 * the model selector appropriately.
 */
function handleGetModels(): void {
    $provider = getActiveProvider();

    if (empty($provider['endpoint'])) {
        sendJsonResponse(['error' => 'No LLM provider configured'], true);
    }

    $models = [];

    if ($provider['driver'] === 'ddg') {
        $models = getAvailableModelsDDG();
    } elseif ($provider['models_api']) {
        $models = getAvailableModels($provider['endpoint'], $provider['key'], $provider['filter']);
        if (isset($models['error'])) {
            $models = []; // fall through to default_model below
        }
    }

    // Always ensure the configured default model appears in the list
    if (!empty($provider['default_model']) && !isset($models[$provider['default_model']])) {
        $label = str_replace(['/', '-', ':'], [' / ', ' ', ' '], $provider['default_model']);
        $models[$provider['default_model']] = ucwords($label);
    }

    if (empty($models)) {
        sendJsonResponse(['error' => 'No models available from provider "' . $provider['name'] . '"'], true);
    }

    ksort($models);
    sendJsonResponse([
        'models'   => $models,
        'provider' => $provider['name'],
    ], true);
}

/**
 * Return available prompt templates from the prompts/ directory.
 */
function handleGetPrompts(): void {
    $prompts = [];

    if (is_dir('prompts')) {
        foreach (scandir('prompts') as $file) {
            if (!preg_match('/\.(txt|md|xml)$/i', $file)) continue;

            $path = 'prompts/' . basename($file);
            $key  = pathinfo($path, PATHINFO_FILENAME);
            $text = file_get_contents($path);
            if ($text !== false) {
                $prompts[$key] = [
                    'label'  => ucwords(str_replace(['_', '-'], ' ', $key)),
                    'prompt' => $text,
                ];
            }
        }
    }

    sendJsonResponse(['prompts' => $prompts], true);
}

/**
 * Main processing pipeline for a tool submission.
 *
 * Validates configuration, resolves the tool, handles file uploads, builds
 * the prompt, calls the LLM, processes the response, and sends JSON back.
 *
 * @param string $tool_id Tool identifier from the request action.
 */
function handleToolAction(string $tool_id): void {
    global $DEBUG_MODE;

    $provider = getActiveProvider();
    if (empty($provider['endpoint'])) {
        sendJsonResponse(['error' => 'No LLM provider configured'], true);
    }

    $config = getConfigData();
    if (isset($config['error'])) {
        sendJsonResponse(['error' => $config['error']], true);
    }

    $tool = getToolConfig($tool_id);
    if (isset($tool['error'])) {
        sendJsonResponse(['error' => $tool['error']], true);
    }

    $form_data    = $_POST;
    $file_content = '';
    $is_image     = false;
    $image_data   = null;
    $mime_type    = null;
    $file_info    = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $file_info = [
            'name'  => $file['name'],
            'type'  => $file['type'],
            'size'  => $file['size'],
            'error' => $file['error'],
        ];

        // Determine real MIME type from bytes for routing
        $finfo       = new finfo(FILEINFO_MIME_TYPE);
        $real_mime   = $finfo->file($file['tmp_name']);
        $image_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($real_mime, $image_mimes, true)) {
            $is_image   = true;
            $max_size   = $_POST['max_image_size'] ?? '500';
            $img_result = processUploadedImage($file, $max_size);

            if (isset($img_result['error'])) {
                sendJsonResponse(['error' => $img_result['error']], true);
            }

            $image_data = $img_result['image_data'];
            $mime_type  = $img_result['mime_type'];
            $file_info['processed_mime_type'] = $mime_type;
            $file_info['extracted_length']    = strlen($image_data);
        } else {
            $file_content = extractTextFromDocument($file['tmp_name'], $real_mime);
            if ($file_content === false) {
                sendJsonResponse([
                    'error' => 'Failed to extract text from the uploaded document. '
                             . 'Ensure antiword, catdoc, pdftotext, or odt2txt is installed.',
                ], true);
            }
            $file_content = trim($file_content);
            $file_content = preg_replace('/^\xEF\xBB\xBF/', '', $file_content); // strip BOM
            $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
            $file_info['extracted_length'] = strlen($file_content);
        }
    }

    // Validate required fields (only full field objects, not string shortcuts)
    $missing = [];
    foreach ($tool['form']['fields'] as $field) {
        if (is_array($field) && !empty($field['required']) && empty($form_data[$field['name']])) {
            $missing[] = $field['name'];
        }
    }
    if (!empty($missing)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $missing)], true);
    }

    // Run helper if the tool specifies one
    if (!empty($tool['helper'])) {
        $helper_out = executeHelper($tool['helper'], $form_data);
        if ($helper_out !== false) {
            $form_data['content'] = $helper_out;
        }
    }

    // Build prompt and compose API request
    $prompt = buildToolPrompt($tool, $form_data);
    if (!empty($file_content)) {
        $prompt .= "\n" . $file_content;
    }

    // Model priority: form submission > provider default
    $model = (!empty($form_data['model'])) ? $form_data['model'] : $provider['default_model'];

    $api_data = [
        'model'    => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'stream'   => false,
    ];

    // Append image as a separate user message
    if ($is_image && $image_data !== null) {
        $api_data['messages'][] = [
            'role'    => 'user',
            'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64," . base64_encode($image_data)]],
            ],
        ];
    }

    // Dispatch to the correct driver
    if ($provider['driver'] === 'ddg') {
        $response = callDDGApi($api_data);
    } else {
        $response = callLLMApi($provider['chat_endpoint'], $api_data, $provider['key']);
    }

    if (isset($response['error'])) {
        $result = ['error' => $response['error']];
        if ($DEBUG_MODE) {
            $result['debug']['response'] = $response;
        }
    } else {
        $result = processToolResponse($tool, $response);
    }

    // Always include the rendered prompt for transparency
    $result['debug']['prompt'] = $prompt;

    if ($DEBUG_MODE) {
        $result['debug']['api_data'] = $api_data;
        $result['debug']['provider'] = $provider['name'];
        if ($file_info !== null) {
            $result['debug']['file_info'] = $file_info;
        }
    }

    sendJsonResponse($result, true);
}

/**
 * Dispatch a helper by name and return its output string.
 *
 * @param string $helper_name Identifier ('lynx', 'web_scraper', 'medical_literature_search').
 * @param array  $form_data   Submitted form values.
 * @return string|false Helper output, or false if unavailable / validation failed.
 */
function executeHelper(string $helper_name, array $form_data) {
    switch ($helper_name) {
        case 'web_scraper':
            $url = $form_data['url'] ?? '';
            if (empty($url) || strlen($url) > 2048) return false;
            $v = processUrl($url);
            if (!$v['valid']) return false;
            return scrapeUrl($v['data']);

        case 'lynx':
            $url = $form_data['url'] ?? '';
            if (empty($url)) return false;
            $out = runLynxCommand($url);
            return (is_string($out) && $out !== '') ? $out : false;

        case 'medical_literature_search':
            $query = $form_data['query'] ?? '';
            if (empty($query) || strlen($query) > 500) return false;
            $articles = searchPubMed($query, 5);
            return (!empty($articles)) ? json_encode($articles, JSON_PRETTY_PRINT) : false;

        default:
            return false;
    }
}

/**
 * Process the raw LLM API response for a tool.
 *
 * FIX #12: JSON extraction is now triggered by `"display": "json"` OR
 * `"output": "json"` — covering the rdd tool and any future JSON-output tools.
 *
 * @param array $tool         Tool configuration.
 * @param array $api_response Raw decoded API response.
 * @return array Result array with 'tool', 'response', and optionally 'json'.
 */
function processToolResponse(array $tool, array $api_response): array {
    $result = [
        'tool'     => $tool['id'],
        'response' => $api_response,
    ];

    $wants_json = (isset($tool['output'])   && $tool['output']   === 'json')
               || (isset($tool['display'])  && $tool['display']  === 'json');

    if ($wants_json) {
        $content  = $api_response['choices'][0]['message']['content'] ?? '';
        $json_data = extractJsonFromResponse($content);
        if ($json_data !== null) {
            $result['json'] = $json_data;
        }
    }

    return $result;
}

// =========================================================================
// Web Interface
// =========================================================================

/**
 * Serve the main HTML interface with CSRF protection.
 *
 * FIX #9: session_regenerate_id() is called only when a new session is
 * created, not on every page load, which previously destroyed valid sessions
 * and invalidated CSRF tokens between requests.
 */
function displayWebInterface(): void {
    session_name('DOCMIND_SID');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        // FIX #9: only regenerate on fresh session creation
        if (empty($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    define('CSRF_TOKEN', $_SESSION['csrf_token']);

    include 'index.html';
}

// =========================================================================
// Main Request Router
// =========================================================================

$is_api_request = isset($_GET['action']) || isset($_POST['action'])
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($is_api_request) {
    handleApiRequest();
    exit;
}

displayWebInterface();
