<?php
/**
 * Patient Education Content Generator
 * 
 * A PHP web application that uses AI to convert complex medical information
 * into patient-friendly educational content.
 * 
 * Features:
 * - AI-powered simplification of medical content
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
 * - API endpoint: POST /pec.php with medical content
 * 
 * API Usage:
 * POST /pec.php
 * Parameters:
 * - content (required): Medical content to simplify
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "education": "simplified patient-friendly content"
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
 * Get selected model and language from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['pec-model']) ? $_COOKIE['pec-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['pec-language']) ? $_COOKIE['pec-language'] : 'en'));

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
 * Contains instructions for converting medical content to patient education format
 */
$SYSTEM_PROMPT = "You are a medical education specialist. Your task is to convert complex medical information into patient-friendly educational content.

" . getLanguageInstruction($LANGUAGE) . "

TASK:
- Take the provided medical content and rewrite it in simple, easy-to-understand language
- Use everyday terms instead of medical jargon
- Explain medical terms when they must be used
- Keep explanations clear and concise
- Maintain accuracy while improving accessibility
- Format the output with clear headings and bullet points where appropriate

OUTPUT FORMAT:
Plain text with clear formatting. Use headings, bullet points, and short paragraphs to improve readability.

RULES:
- Focus on what the patient needs to know
- Avoid technical terms when simpler alternatives exist
- Explain any technical terms that cannot be avoided
- Use active voice and present tense
- Include practical information when relevant (e.g., what to expect, when to seek help)
- Do not add information not present in the original content
- Do not make medical recommendations or diagnoses
- Respond only with the patient-friendly content, without additional text

EXAMPLE:

Input: \"The patient presents with acute myocardial infarction secondary to coronary artery occlusion. Percutaneous coronary intervention was performed with stent placement.\"

Output: 
\"## Heart Attack Treatment

You had a heart attack, which happens when blood flow to part of your heart muscle is blocked. 

To treat this, we performed a procedure called angioplasty where we:
* Inserted a thin tube into the blocked heart artery
* Opened the blockage using a small balloon
* Placed a metal stent (tube) to keep the artery open

This procedure helps restore blood flow to your heart and prevents further damage.\"";

/**
 * Application state variables
 * @var string|null $result Simplified content result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for content simplification
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['content'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['content']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $content = trim(isset($_POST['content']) ? $_POST['content'] : $_GET['content']);
    
    // Validate content length (prevent extremely large inputs)
    if (strlen($content) > 10000) {
        $error = 'The content is too long. Maximum 10000 characters allowed.';
        $processing = false;
    } 
    // Validate content is not empty after trimming
    elseif (empty($content)) {
        $error = 'The content cannot be empty.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
        // Prepare API request
        $data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                ['role' => 'user', 'content' => "MEDICAL CONTENT TO SIMPLIFY:\n" . $content]
            ]
        ];
        
        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
        
        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $result = trim($response_data['choices'][0]['message']['content']);
        } else {
            $error = 'Invalid API response format';
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('pec-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('pec-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['education' => $result]);
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
    <title>Patient Education Content Generator</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%9A%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📚 Patient Education Content Generator</h1>
            <p>AI-powered simplification of medical information for patients</p>
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
                        <h2>Patient-Friendly Content</h2>
                    </header>
                    
                    <div class="markdown-result" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">
                        <?php echo markdownToHtml(htmlspecialchars($result)); ?>
                    </div>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="educationForm">
                <fieldset>
                    <label for="content">Medical content:</label>
                    <textarea 
                        id="content" 
                        name="content" 
                        rows="8" 
                        required
                        placeholder="Enter complex medical content to simplify for patients...&#10;&#10;Example: The patient presents with acute myocardial infarction secondary to coronary artery occlusion. Percutaneous coronary intervention was performed with stent placement."
                    ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : (isset($_GET['content']) ? htmlspecialchars($_GET['content']) : ''); ?></textarea>
                    <small>
                        Enter the medical content you want to convert to patient-friendly language.
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
                        Select the AI model to use for content simplification.
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
                        Select the language for the patient education content.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📚 Generate Education Content
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Content
                </button>
            </form>
        </main>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('content').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
