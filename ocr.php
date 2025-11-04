<?php
/**
 * Optical Character Recognition (OCR) Tool
 * 
 * A PHP web application that uses AI to perform OCR on uploaded images and extract
 * text in Markdown format.
 * 
 * Features:
 * - AI-powered OCR of images
 * - Multiple lightweight AI models support
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
 * - $API_ENDPOINT: AI API endpoint URL
 * - $API_KEY: API key (if required)
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
    $API_ENDPOINT = 'http://127.0.0.1:11434/v1';
    $API_KEY = '';
}

// Create chat endpoint URL
$API_ENDPOINT_CHAT = $API_ENDPOINT . '/chat/completions';

// Fetch available models from API
$AVAILABLE_MODELS = getAvailableModels($API_ENDPOINT, $API_KEY);

// If API call fails, use default models
if (empty($AVAILABLE_MODELS)) {
    $AVAILABLE_MODELS = [
        'llama3.2-vision' => 'Llama 3.2 Vision',
        'gemma3:4b' => 'Gemma 3 (4B)',
        'qwen2.5vl:3b' => 'Qwen 2.5 VL (3B)',
        'qwen3-vl:4b' => 'Qwen 3 VL (4B)',
        'qwen3-vl:2b' => 'Qwen 3 VL (2B)',
        'llava-phi3:3.8b' => 'LLA-Phi 3 (3.8B)',
        'moondream:1.8b' => 'Moondream (1.8B)'
    ];
}

// Available output languages
$AVAILABLE_LANGUAGES = [
    'ro' => 'Română',
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
];

/**
 * Get selected model and language from POST data or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : 'gemma3:4b';
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : 'ro';

/**
 * Language instructions for the AI model
 * Maps language codes to natural language instructions
 */
$language_instructions = [
    'ro' => 'Respond in Romanian.',
    'en' => 'Respond in English.',
    'es' => 'Responde en español.',
    'fr' => 'Répondez en français.',
    'de' => 'Antworte auf Deutsch.',
    'it' => 'Rispondi in italiano.'
];

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'llama3.2-vision'; // Default to a valid model
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

" . $language_instructions[$LANGUAGE];

/**
 * Application state variables
 * @var string|null $result Extracted text result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST request for image OCR
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $processing = true;
    $is_api_request = !isset($_POST['submit']); // If no submit button, it's an API request
    
    // Validate file upload
    $image_file = $_FILES['image'];
    
    // Check file size (max 5MB)
    if ($image_file['size'] > 5 * 1024 * 1024) {
        $error = 'The image file is too large. Maximum 5MB allowed.';
        $processing = false;
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($image_file['type'], $allowed_types)) {
        $error = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
        // Preprocess image for better OCR
        $preprocessed_image_path = preprocessImageForOCR($image_file['tmp_name']);
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
        
        // Make API request
        $ch = curl_init($API_ENDPOINT_CHAT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $API_KEY
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = 'Connection error: ' . curl_error($ch);
        } elseif ($http_code !== 200) {
            $error = 'API error: HTTP ' . $http_code;
        } else {
            $response_data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error = 'Invalid API response format: ' . json_last_error_msg();
            } elseif (isset($response_data['choices'][0]['message']['content'])) {
                $result = trim($response_data['choices'][0]['message']['content']);
                // Remove markdown code fences if present
                $result = preg_replace('/^```(?:markdown)?\s*(.*?)\s*```$/s', '$1', $result);
            } else {
                $error = 'Invalid API response format';
            }
        }
        
        curl_close($ch);
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['text' => $result]);
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
    <script defer src="scripts.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📷 Image OCR Tool</h1>
            <p>AI-powered optical character recognition</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="error">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <div class="result-card">
                    <div class="result-header">
                        <h2 style="color: #111827; font-size: 20px;">OCR Result</h2>
                    </div>
                    
                    <textarea class="markdown-result" readonly><?php echo htmlspecialchars($result); ?></textarea>
                </div>
                
                <?php if (isset($preprocessed_image_base64)): ?>
                <div class="result-card">
                    <div class="result-header">
                        <h2 style="color: #111827; font-size: 20px;">Preprocessed Image</h2>
                    </div>
                    <div style="text-align: center;">
                        <img src="data:image/png;base64,<?php echo $preprocessed_image_base64; ?>" 
                             alt="Preprocessed image for OCR" 
                             style="max-width: 100%; max-height: 400px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="" id="ocrForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="model">AI model:</label>
                    <select id="model" name="model">
                        <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                <?php echo ($MODEL === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="language">Response language:</label>
                    <select id="language" name="language">
                        <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                <?php echo ($LANGUAGE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="image">Image file:</label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        required
                    >
                    <div class="file-info">
                        Supported formats: JPEG, PNG, GIF, WebP. Maximum size: 5MB.
                    </div>
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
