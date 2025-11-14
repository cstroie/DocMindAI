<?php
/**
 * Optical Character Recognition (OCR) Tool
 * 
 * Handle hupl configuration request
 */
if (isset($_GET['hupl'])) {
    header('Content-Type: text/plain');
    echo "endpoint: " . ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "\n";
    exit;
}

/**
 * Optical Character Recognition (OCR) Tool
 * 
 * A PHP web application that uses AI to perform OCR on uploaded images and extract
 * text in Markdown format.
 * 
 * Features:
 * - AI-powered OCR of images
 * - Multiple lightweight AI models support (filtered for free models)
 * - Multilingual output (6 languages)
 * - Web interface with real-time results
 * - REST API support
 * - Configurable API endpoint via external config.php
 * 
 * Requirements:
 * - PHP 7.0+
 * - cURL extension
 * - JSON extension
 * - Access to compatible AI API (e.g., Ollama)
 * 
 * Usage:
 * - Web interface: Access via browser
 * - API endpoint: POST /ocr.php with image data
 * 
 * API Usage:
 * POST /ocr.php
 * Parameters:
 * - image (required): Image file to process
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: ro)
 * 
 * Response:
 * {
 *   "text": "extracted text in markdown format"
 * }
 * 
 * Configuration:
 * Create a config.php file with:
 * - $LLM_API_ENDPOINT: AI API endpoint URL
 * - $LLM_API_KEY: API key (if required)
 * 
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */
// Include common functions
include 'common.php';

// Configuration - Load from config.php if available, otherwise use defaults
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Safe defaults
    $LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
    $LLM_API_KEY = '';
    $DEFAULT_VISION_MODEL = 'gemma3:4b';
    $LLM_API_FILTER = '/free/';
}

// Create chat endpoint URL
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Fetch available models from API, filtering with configured filter
$AVAILABLE_MODELS = getAvailableModels($LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER);

// If API call fails, use default models
if (empty($AVAILABLE_MODELS)) {
    $AVAILABLE_MODELS = [
        'gemma3:4b' => 'Gemma 3 (4B)',
        'moondream:1.8b' => 'Moondream (1.8B)'
    ];
}

// Set default model if not defined in config
if (!isset($DEFAULT_VISION_MODEL)) {
    $DEFAULT_VISION_MODEL = !empty($AVAILABLE_MODELS) ? array_keys($AVAILABLE_MODELS)[0] : 'gemma3:4b';
}

/**
 * Get selected model and language from POST data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_COOKIE['ocr-model']) ? $_COOKIE['ocr-model'] : $DEFAULT_VISION_MODEL);
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_COOKIE['ocr-language']) ? $_COOKIE['ocr-language'] : 'ro');

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'gemma3:4b'; // Default to a valid model
}

/**
 * Validate language selection
 * Falls back to Romanian if invalid language is selected
 */
if (!array_key_exists($LANGUAGE, $AVAILABLE_LANGUAGES)) {
    $LANGUAGE = 'ro'; // Default to Romanian
}

/**
 * System prompt for the AI model
 * Contains instructions for performing OCR on images
 */
$SYSTEM_PROMPT = "Perform Optical Character Recognition (OCR) on the following image data. Extract and return ONLY the text you see in the image, formatted appropriately in Markdown. Do not add any explanations or introductions. Return only the Markdown formatted text content.

" . getLanguageInstruction($LANGUAGE);

/**
 * System prompt for the summary AI model
 * Contains instructions for summarizing text content
 */
$SUMMARY_SYSTEM_PROMPT = "You are a helpful assistant that creates concise summaries of text content.

TASK: Create a brief summary of the provided text content. Focus on the main points and key information. Keep the summary under 100 words.
" . getLanguageInstruction($LANGUAGE) . "

OUTPUT FORMAT (JSON):
{
  \"summary\": \"summary text\"
}

RULES:
- Focus on main points and key information
- Keep summary under 100 words
- Respond ONLY with the JSON, without additional text";

/**
 * Application state variables
 * @var string|null $result Extracted text result
 * @var string|null $summary Generated summary of the text
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 * @var bool $is_hupl_request Whether request is hupl-compatible call
 */
$result = null;
$summary = null;
$error = null;
$processing = false;
$is_api_request = false;
$is_hupl_request = false;

/**
 * Handle POST request for image OCR
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $processing = true;
    $is_api_request = !isset($_POST['submit']); // If no submit button, it's an API request
    $is_hupl_request = isset($_POST['file']); // Check for hupl-compatible request
    
    // Validate file upload
    $image_file = $_FILES['image'];
    
    // Check file size (max 10MB to accommodate PDFs)
    if ($image_file['size'] > 10 * 1024 * 1024) {
        $error = 'The file is too large. Maximum 10MB allowed.';
        $processing = false;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    if (!in_array($image_file['type'], $allowed_types)) {
        $error = 'Invalid file type. Only JPEG, PNG, GIF, WebP images and PDF documents are allowed.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
        // Handle PDF files
        if ($image_file['type'] === 'application/pdf') {
            // Check if Imagick or Gmagick extension is available
            if (!extension_loaded('imagick') && !extension_loaded('gmagick')) {
                $error = 'PDF processing requires either the ImageMagick or GraphicsMagick extension which is not installed or enabled.';
                $processing = false;
            } else {
                // Extract images from PDF
                $images = extractImagesFromPDF($image_file['tmp_name']);
                if ($images === false || empty($images)) {
                    $error = 'Failed to extract images from PDF or PDF contains no images.';
                    $processing = false;
                } else {
                    // Use the first image from PDF for OCR
                    $temp_image_path = tempnam(sys_get_temp_dir(), 'pdf_') . '.png';
                    if (file_put_contents($temp_image_path, $images[0]) === false) {
                        $error = 'Failed to save extracted PDF image.';
                        $processing = false;
                    } else {
                        // Preprocess the extracted image
                        $preprocessed_image_path = preprocessImageForOCR($temp_image_path);
                        unlink($temp_image_path); // Clean up temporary file
                    }
                }
            }
        } else {
            // Handle regular image files
            $preprocessed_image_path = preprocessImageForOCR($image_file['tmp_name']);
        }
        
        if ($processing && isset($preprocessed_image_path)) {
            if ($preprocessed_image_path === false) {
                $error = 'Failed to preprocess the image for OCR.';
                $processing = false;
            } else {
                // Read and encode preprocessed image
                $image_data = file_get_contents($preprocessed_image_path);
                if ($image_data === false) {
                    $error = 'Failed to read the preprocessed image file.';
                    $processing = false;
                } else {
                    $base64_image = base64_encode($image_data);
                    $image_url = 'data:image/png;base64,' . $base64_image;
                    // Store base64 for display
                    $preprocessed_image_base64 = $base64_image;
                }
                // Clean up temporary file
                unlink($preprocessed_image_path);
            }
        }
    }
    
    if ($processing) {
        // Prepare API request
        $data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                [
                    'role' => 'user', 
                    'content' => [
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $image_url
                            ]
                        ]
                    ]
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 2048
        ];
        
        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
        
        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $result = trim($response_data['choices'][0]['message']['content']);
            // Remove markdown code fences if present
            $result = preg_replace('/^```(?:markdown)?\s*(.*?)\s*```$/s', '$1', $result);
            
            // Generate summary of the extracted text
            if (!empty($result)) {
                // Prepare summary API request
                $summary_data = [
                    'model' => $DEFAULT_TEXT_MODEL ?? 'qwen2.5:1.5b',
                    'messages' => [
                        ['role' => 'system', 'content' => $SUMMARY_SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => "TEXT TO SUMMARIZE:\n" . $result]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 300
                ];
                
                // Make summary API request
                $summary_response = callLLMApi($LLM_API_ENDPOINT_CHAT, $summary_data, $LLM_API_KEY);
                
                if (isset($summary_response['choices'][0]['message']['content'])) {
                    $summary_content = trim($summary_response['choices'][0]['message']['content']);
                    
                    // Extract JSON from summary response
                    if (preg_match('/\{[^}]+\}/', $summary_content, $summary_matches)) {
                        $summary_json_str = $summary_matches[0];
                        $summary_result = json_decode($summary_json_str, true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && isset($summary_result['summary'])) {
                            $summary = $summary_result['summary'];
                        }
                    }
                }
            }
        } else {
            $error = 'Invalid API response format';
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('ocr-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('ocr-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            if ($is_hupl_request) {
                // For hupl-compatible requests, return only the text
                header('Content-Type: text/plain');
                if ($error) {
                    echo $error;
                } else {
                    echo $result;
                }
            } else {
                // Regular API request returns JSON
                header('Content-Type: application/json');
                if ($error) {
                    echo json_encode(['error' => $error]);
                } else {
                    echo json_encode(['text' => $result]);
                }
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image OCR Tool</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📷 Image OCR Tool</h1>
            <p>AI-powered optical character recognition</p>
        </div>

        <div class="content">
            <form method="POST" action="" id="ocrForm" enctype="multipart/form-data">
                <?php if ($error): ?>
                    <div class="error">
                        <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($result): ?>
                    <?php if ($summary): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">📄 Summary</h2>
                        </div>
                        
                        <div class="summary-box">
                            <div class="summary-text"><?php echo htmlspecialchars($summary); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">🔍 OCR Result</h2>
                        </div>
                        
                        <textarea class="markdown-result" readonly><?php echo htmlspecialchars($result); ?></textarea>
                    </div>
                    
                    <?php if (isset($preprocessed_image_base64)): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">Preprocessed Image</h2>
                        </div>
                        <div class="preprocessed-image-container">
                            <img src="data:image/png;base64,<?php echo $preprocessed_image_base64; ?>" 
                                 alt="Preprocessed image for OCR" 
                                 class="preprocessed-image">
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($is_api_request && $result): ?>
                <div class="result-card">
                    <div class="result-header">
                        <h2 style="color: #111827; font-size: 20px;">📄 Summary</h2>
                    </div>
                    
                    <div class="summary-box">
                        <div class="summary-text"><?php echo htmlspecialchars($summary ?? 'No summary available'); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="image">Image file:</label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                        required
                    >
                    <div class="file-info">
                        Supported formats: JPEG, PNG, GIF, WebP, PDF. Maximum size: 10MB.
                    </div>
                </div>

                <div class="form-group">
                    <label for="model">AI model:</label>
                    <select id="model" name="model">
                        <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($MODEL === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="language">Response language:</label>
                    <select id="language" name="language">
                        <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($LANGUAGE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📄 Extract text
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New OCR
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('image').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
