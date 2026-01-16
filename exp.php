<?php
/**
 * Experiment Tool
 * 
 * A PHP web application that allows users to select predefined prompts and run them through AI models.
 * 
 * Features:
 * - Predefined prompt selection via dropdown
 * - Custom prompt editing
 * - Multiple AI models support
 * - Multilingual output
 * - Web interface with real-time results
 * - REST API support
 * 
 * Requirements:
 * - PHP 7.0+
 * - cURL extension
 * - JSON extension
 * - Access to compatible AI API (e.g., Ollama)
 * 
 * Usage:
 * - Web interface: Access via browser
 * - API endpoint: POST /exp.php with data
 * 
 * API Usage:
 * POST /exp.php
 * Parameters:
 * - prompt (required): Prompt text to run
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "result": "AI response content"
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
 * Load prompts from files in the prompts directory
 */
function loadPromptsFromDirectory() {
    $prompts = [];

    // Check if prompts directory exists
    if (is_dir('prompts')) {
        // Get all files in the prompts directory
        $files = scandir('prompts');

        foreach ($files as $file) {
            // Check for .txt, .md, or .xml extensions
            if (preg_match('/\.(txt|md|xml)$/i', $file)) {
                $file_path = 'prompts/' . $file;

                // Use filename (without extension) as the key
                $key = pathinfo($file, PATHINFO_FILENAME);

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
    // Return the loaded prompts
    return $prompts;
}

// Load all prompts from files
$PREDEFINED_PROMPTS = loadPromptsFromDirectory();

/**
 * Get selected model, language, and prompt from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['exp-model']) ? $_COOKIE['exp-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['exp-language']) ? $_COOKIE['exp-language'] : 'en'));

// Set default prompt type - use first available prompt or empty string
$default_prompt_type = !empty($PREDEFINED_PROMPTS) ? array_keys($PREDEFINED_PROMPTS)[0] : '';
$PROMPT_TYPE = isset($_POST['prompt_type']) ? $_POST['prompt_type'] : (isset($_GET['prompt_type']) ? $_GET['prompt_type'] : (isset($_COOKIE['exp-prompt-type']) ? $_COOKIE['exp-prompt-type'] : $default_prompt_type));

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
 * Application state variables
 * @var string|null $result AI response result
 * @var string|null $error Error message if any
 * @var bool $processing Whether processing is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for prompt execution
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['prompt']) || (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK))) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['prompt']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request

    // Sanitize and validate input
    $prompt = trim(isset($_POST['prompt']) ? $_POST['prompt'] : (isset($_GET['prompt']) ? $_GET['prompt'] : ''));

    // Handle file upload if present
    $file_content = '';
    $is_image = false;
    $image_data = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];

        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $error = 'The file is too large. Maximum 10MB allowed.';
            $processing = false;
        } else {
            // Check if it's an image
            $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $image_types)) {
                $is_image = true;
                // Read image data
                $image_data = file_get_contents($file['tmp_name']);
                if ($image_data === false) {
                    $error = 'Failed to read the uploaded image.';
                    $processing = false;
                }
            } else {
                // Text document - try to extract text
                $file_content = extractTextFromDocument($file['tmp_name'], $file['type']);
                if ($file_content === false) {
                    $error = 'Failed to extract text from the uploaded document. Please ensure you have the required tools installed (antiword, catdoc, pdftotext, odt2txt, or pandoc).';
                    $processing = false;
                } else {
                    // Clean up the text content
                    $file_content = trim($file_content);
                    // Remove BOM if present
                    $file_content = preg_replace('/^\xEF\xBB\xBF/', '', $file_content);
                    // Normalize line endings
                    $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
                }
            }
        }
    }

    // Validate prompt length (including file content if it's text)
    $total_length = strlen($prompt) + strlen($file_content);
    if ($total_length > 10000) {
        $error = 'The prompt (including file content) is too long. Maximum 10000 characters allowed.';
        $processing = false;
    }
    // Validate prompt is not empty after trimming (unless we have an image)
    elseif (empty($prompt) && !$is_image) {
        $error = 'The prompt cannot be empty unless you upload an image.';
        $processing = false;
    }

    // Only proceed with API call if validation passed
    if ($processing) {
        // Prepare API request
        $api_data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => getLanguageInstruction($LANGUAGE)]
            ]
        ];

        // Add user message with prompt and file content
        $user_content = $prompt;
        if (!empty($file_content)) {
            $user_content .= $file_content;
        }

        $api_data['messages'][] = ['role' => 'user', 'content' => $user_content];

        // If it's an image, add it as a separate message with image data
        if ($is_image && $image_data !== null) {
            // Convert image to base64
            $base64_image = base64_encode($image_data);
            $mime_type = $file['type'];
            $api_data['messages'][] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64_image"]]
                ]
            ];
        }

        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY);

        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $content = $response_data['choices'][0]['message']['content'];
            // Handle both string and array responses
            if (is_array($content)) {
                $result = $content;
            } else {
                $result = trim($content);
            }
        } else {
            $error = 'Invalid API response format';
        }

        // Set cookies with the selected model, language, and prompt type only for web requests
        if (!$is_api_request) {
            setcookie('exp-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('exp-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('exp-prompt-type', $PROMPT_TYPE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }

        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['result' => $result]);
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
    <title>Experiment Tool</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🧪%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>🧪 Experiment Tool</h1>
            <p>Run predefined prompts through AI models</p>
        </hgroup>

        <main>
            <?php if ($error): ?>
                <section role="alert" class="error">
                    <strong>⚠️ Error:</strong>
                    <?php if (is_array($error)): ?>
                        <pre><?php echo jsonSyntaxHighlight(json_encode($error, JSON_PRETTY_PRINT)); ?></pre>
                    <?php else: ?>
                        <?php echo htmlspecialchars($error); ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($result): ?>
                <article>
                    <header>
                        <h2>AI Response</h2>
                    </header>

                    <section>
                        <?php if (is_array($result)): ?>
                            <?php if (isset($result['content'])): ?>
                                <?php if (is_array($result['content'])): ?>
                                    <?php foreach ($result['content'] as $content_item): ?>
                                        <?php if (isset($content_item['type']) && $content_item['type'] === 'image_url'): ?>
                                            <img src="<?php echo htmlspecialchars($content_item['image_url']['url']); ?>" alt="Uploaded image" style="max-width: 100%; height: auto; margin: 10px 0;">
                                        <?php else: ?>
                                            <pre><?php echo htmlspecialchars($content_item['text'] ?? ''); ?></pre>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <pre><?php echo htmlspecialchars($result['content']); ?></pre>
                                <?php endif; ?>
                            <?php else: ?>
                                <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
                            <?php endif; ?>
                        <?php else: ?>
                            <pre><?php echo htmlspecialchars($result); ?></pre>
                        <?php endif; ?>
                    </section>
                </article>
            <?php endif; ?>

            <?php if (empty($PREDEFINED_PROMPTS)): ?>
                <section role="alert" class="error">
                    <strong>ℹ️ Information:</strong> No prompt files found in the 'prompts' directory. Please create .txt, .md, or .xml files in the prompts directory to use predefined prompts.
                </section>
            <?php endif; ?>

            <form method="POST" action="" id="experimentForm" enctype="multipart/form-data">
                <fieldset>
                    <?php if (!empty($PREDEFINED_PROMPTS)): ?>
                        <label for="prompt_type">Predefined Prompt:</label>
                        <select id="prompt_type" name="prompt_type" onchange="updatePrompt()">
                            <?php foreach ($PREDEFINED_PROMPTS as $key => $prompt_data): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($PROMPT_TYPE === $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prompt_data['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>
                            Select a predefined prompt type or choose "Custom" to enter your own.
                        </small>
                    <?php endif; ?>

                    <label for="prompt">Prompt Text:</label>
                    <textarea
                        id="prompt"
                        name="prompt"
                        rows="20"
                        required
                        placeholder="Enter your prompt text here..."
                    ><?php
                        // If we have a selected prompt type, use its predefined text
                        if (isset($PREDEFINED_PROMPTS[$PROMPT_TYPE]) && is_string($PREDEFINED_PROMPTS[$PROMPT_TYPE]['prompt'])) {
                            echo htmlspecialchars($PREDEFINED_PROMPTS[$PROMPT_TYPE]['prompt']);
                        } elseif (isset($_POST['prompt']) && is_string($_POST['prompt'])) {
                            echo htmlspecialchars($_POST['prompt']);
                        } elseif (isset($_GET['prompt']) && is_string($_GET['prompt'])) {
                            echo htmlspecialchars($_GET['prompt']);
                        }
                    ?></textarea>
                    <small>
                        Edit the prompt text as needed. The selected predefined prompt will be loaded here.
                    </small>

                    <label for="file">Optional file upload:</label>
                    <input
                        type="file"
                        id="file"
                        name="file"
                        accept=".txt,.md,.doc,.docx,.pdf,.odt,.jpg,.jpeg,.png,.gif,.webp,text/plain,text/markdown,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf,application/vnd.oasis.opendocument.text,image/jpeg,image/png,image/gif,image/webp">
                    <small>
                        Upload a text document (content will be appended to prompt) or an image (will be sent with prompt).
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
                        Select the AI model to use for processing the prompt.
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
                        Select the language for the AI response.
                    </small>
                </fieldset>

                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    🧪 Run Experiment
                </button>

                <div class="button-grid">
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        🔄 New Experiment
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                        🏠 Back to Main Menu
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        function updatePrompt() {
            const promptType = document.getElementById('prompt_type').value;
            const promptTextarea = document.getElementById('prompt');

            // Get predefined prompts from PHP (we'll embed them in JavaScript)
            const predefinedPrompts = <?php echo json_encode($PREDEFINED_PROMPTS); ?>;

            if (predefinedPrompts[promptType]) {
                promptTextarea.value = predefinedPrompts[promptType].prompt;
            } else {
                promptTextarea.value = '';
            }
        }

        function clearForm() {
            document.getElementById('prompt_type').selectedIndex = 0;
            document.getElementById('prompt').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }

        // Initialize prompt on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePrompt();
        });
    </script>
</body>
</html>
