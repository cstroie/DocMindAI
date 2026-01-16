<?php
/**
 * Structured Data Extractor
 * 
 * A PHP web application that uses AI to extract structured data from unstructured text.
 * 
 * Features:
 * - AI-powered data extraction
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
 * - API endpoint: POST /sde.php with data
 * 
 * API Usage:
 * POST /sde.php
 * Parameters:
 * - data (required): Text data to extract from
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   // Extracted data in JSON format (schema determined by AI)
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
    $DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
    $LLM_API_FILTER = '/free/';
}

// Create chat endpoint URL
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Fetch available models from API, filtering with configured filter
$AVAILABLE_MODELS = getAvailableModels($LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER);

// If API call fails, use default models
if (empty($AVAILABLE_MODELS)) {
    $AVAILABLE_MODELS = [
        'gemma3:1b' => 'Gemma 3 (1B)',
        'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
    ];
}

// Set default model if not defined in config
if (!isset($DEFAULT_TEXT_MODEL)) {
    $DEFAULT_TEXT_MODEL = !empty($AVAILABLE_MODELS) ? array_keys($AVAILABLE_MODELS)[0] : 'qwen2.5:1.5b';
}

/**
 * Get selected model, language, and output format from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['sde-model']) ? $_COOKIE['sde-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['sde-language']) ? $_COOKIE['sde-language'] : 'en'));
$OUTPUT_FORMAT = isset($_POST['output_format']) ? $_POST['output_format'] : (isset($_GET['output_format']) ? $_GET['output_format'] : (isset($_COOKIE['sde-output-format']) ? $_COOKIE['sde-output-format'] : 'json'));

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'qwen2.5:1.5b'; // Default to a valid model
}

/**
 * Validate language selection
 * Falls back to English if invalid language is selected
 */
if (!array_key_exists($LANGUAGE, $AVAILABLE_LANGUAGES)) {
    $LANGUAGE = 'en'; // Default to English
}

/**
 * System prompt for the AI model
 * Contains instructions for extracting structured data
 */
$SYSTEM_PROMPT = "You are a data extraction API. Respond ONLY with " . strtoupper($OUTPUT_FORMAT) . ".";

/**
 * Application state variables
 * @var string|null $result Extracted data result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for data extraction
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['data'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['data']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $data = trim(isset($_POST['data']) ? $_POST['data'] : $_GET['data']);
    
    // Validate data length (prevent extremely large inputs)
    if (strlen($data) > 10000) {
        $error = 'The data is too long. Maximum 10000 characters allowed.';
        $processing = false;
    } 
    // Validate data is not empty after trimming
    elseif (empty($data)) {
        $error = 'The data cannot be empty.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
        // Prepare API request
        $api_data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                ['role' => 'user', 'content' => "Extract structured data from: " . $data]
            ]
        ];
        
        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY);
        
        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $content = trim($response_data['choices'][0]['message']['content']);
            
            // Process response based on output format
            if ($OUTPUT_FORMAT === 'yaml') {
                // For YAML, we'll use the raw content and try to convert it
                $result = $content;
            } else {
                // For JSON, extract JSON from response
                // First try direct JSON parsing
                $json_str = trim($content);

                // If direct parsing fails, try to extract from code blocks or other patterns
                if (json_decode($json_str) === null) {
                    $json_patterns = [
                        '/```(?:json)?\s*({.*?})\s*```/s',  // JSON in code blocks
                        '/\{.*\}/s',                        // Any JSON object
                        '/({.*?})/s'                        // Capture any braces
                    ];

                    foreach ($json_patterns as $pattern) {
                        if (preg_match($pattern, $content, $matches)) {
                            $json_str = $matches[1];
                            break;
                        }
                    }
                }

                if ($json_str) {
                    $json_result = json_decode($json_str, true);
                    if ($json_result) {
                        $result = $json_result;
                    } else {
                        $result = $content;
                    }
                } else {
                    $result = $content;
                }
            }
        } else {
            $error = 'Invalid API response format';
        }
        
        // Set cookies with the selected model, language, and output format only for web requests
        if (!$is_api_request) {
            setcookie('sde-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('sde-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('sde-output-format', $OUTPUT_FORMAT, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode($result);
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Structured Data Extractor</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js" type="text/javascript"></script>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E📊%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📊 Structured Data Extractor</h1>
            <p>AI-powered extraction of structured data from unstructured text</p>
        </hgroup>

        <main>
            <?php if ($error): ?>
                <section role="alert" class="error">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </section>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <article>
                    <header>
                        <h2>Extracted Data</h2>
                    </header>
                    
                    <section>
                        <?php
                        // Check if result is an array (parsed JSON) or string
                        if (is_array($result)) {
                            if ($OUTPUT_FORMAT === 'yaml') {
                                // Convert array to YAML and display
                                $response_output = yaml_encode($result);
                                $highlight_class = 'highlight-yaml';
                            } else {
                                // Display as formatted JSON
                                $response_output = json_encode($result, JSON_PRETTY_PRINT);
                                $highlight_class = 'highlight-json';
                            }
                            echo '<pre class="' . $highlight_class . '">' . htmlspecialchars($response_output) . '</pre>';
                        } else {
                            // Display as plain text
                            $fence_info = extractCodeFenceInfo($result);
                            $highlight_class = !empty($fence_info['type']) ? 'highlight-' . $fence_info['type'] : '';
                            $text = $fence_info['text'];
                            echo '<pre class="' . $highlight_class . '">' . htmlspecialchars($text) . '</pre>';
                        }
                        ?>
                    </section>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="extractionForm">
                <fieldset>
                    <label for="data">Text data:</label>
                    <textarea 
                        id="data" 
                        name="data" 
                        rows="8" 
                        required
                        placeholder="Enter the text data you want to extract structured data from...&#10;&#10;Example: Patient: John Doe, Age: 45, Diagnosis: Type 2 Diabetes, Treatment: Metformin 500mg twice daily"
                    ><?php echo isset($_POST['data']) ? htmlspecialchars($_POST['data']) : (isset($_GET['data']) ? htmlspecialchars($_GET['data']) : ''); ?></textarea>
                    <small>
                        Enter the text data you want to extract structured data from.
                    </small>

                    <label for="output_format">Output format:</label>
                    <select id="output_format" name="output_format">
                        <option value="json" <?php echo ($OUTPUT_FORMAT === 'json') ? 'selected' : ''; ?>>JSON</option>
                        <option value="yaml" <?php echo ($OUTPUT_FORMAT === 'yaml') ? 'selected' : ''; ?>>YAML</option>
                    </select>
                    <small>
                        Select the output format for the extracted data.
                    </small>

                    <label for="model">AI model:</label>
                    <select id="model" name="model">
                        <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($MODEL === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the AI model to use for data extraction.
                    </small>
                
                    <label for="language">Response language:</label>
                    <select id="language" name="language">
                        <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($LANGUAGE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the language for the extracted data output.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📊 Extract Data
                </button>
                
                <div class="button-grid">
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        🔄 New Extraction
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                        🏠 Back to Main Menu
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('data').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }

        // Apply syntax highlighting to all pre elements
        document.addEventListener('DOMContentLoaded', function() {
            applySyntaxHighlighting();
        });
    </script>
</body>
</html>
