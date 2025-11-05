<?php
/**
 * Simple Content Parser
 * 
 * A PHP web application that scrapes web pages and converts them to clean Markdown format
 * using AI processing.
 * 
 * Features:
 * - Web scraping with Chrome browser simulation
 * - Cookie handling for session management
 * - AI-powered content detection and Markdown conversion
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
 * - API endpoint: POST /scp.php with URL
 * 
 * API Usage:
 * POST /scp.php
 * Parameters:
 * - url (required): URL to scrape and convert
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "markdown": "content in markdown format"
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
    $DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
}

// Create chat endpoint URL
$API_ENDPOINT_CHAT = $API_ENDPOINT . '/chat/completions';

// Fetch available models from API, filtering for free models
$AVAILABLE_MODELS = getAvailableModels($API_ENDPOINT, $API_KEY, '/free/');

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
 * Available output languages for content parsing
 */
$AVAILABLE_LANGUAGES = [
    'en' => 'English',
    'ro' => 'Română',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
];

/**
 * Get selected model and language from POST data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_COOKIE['scp-model']) ? $_COOKIE['scp-model'] : $DEFAULT_TEXT_MODEL);
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_COOKIE['scp-language']) ? $_COOKIE['scp-language'] : 'en');

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
 * Contains instructions for extracting main content and converting to Markdown
 */
$SYSTEM_PROMPT = "You are a content extraction assistant. Your task is to identify the main content of a web page and convert it to clean, well-formatted Markdown.

Instructions:
" . getLanguageInstruction($LANGUAGE) . "

1. Extract ONLY the primary content of the page (article text, main information)
2. Remove navigation menus, footers, sidebars, ads, and other non-essential elements
3. Convert the main content to clean Markdown format with proper headings, lists, etc.
4. Preserve important formatting like bold, italics, links
5. Do not include any explanations or extra text
6. Return ONLY the Markdown content

Example:
Input HTML might contain a full web page with headers, nav bars, etc.
Output should be just the main article content in clean Markdown format.";

/**
 * Application state variables
 * @var string|null $result Extracted markdown result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST request for URL scraping
 * Processes both web form submissions and API requests
 * Validates input, scrapes URL, calls AI API, and processes response
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $processing = true;
    $is_api_request = !isset($_POST['submit']); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $url = trim($_POST['url']);
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'Invalid URL format. Please enter a valid URL including http:// or https://';
        $processing = false;
    }
    
    // Only proceed with scraping if validation passed
    if ($processing) {
        // Scrape the URL content
        $scraped_content = scrapeUrl($url);
        
        if ($scraped_content === false) {
            $error = 'Failed to retrieve content from the URL. Please check the URL and try again.';
            $processing = false;
        } else {
            // Prepare API request
            $data = [
                'model' => $MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => "URL: " . $url . "\n\nCONTENT TO PROCESS:\n" . $scraped_content]
                ],
                'temperature' => 0.1,
                'max_tokens' => 4000
            ];
            
            // Make API request using common function
            $response_data = callLLMApi($API_ENDPOINT_CHAT, $data, $API_KEY);
            
            if (isset($response_data['error'])) {
                $error = $response_data['error'];
            } elseif (isset($response_data['choices'][0]['message']['content'])) {
                $result = trim($response_data['choices'][0]['message']['content']);
                // Remove markdown code fences if present
                $result = preg_replace('/^```(?:markdown)?\s*(.*?)\s*```$/s', '$1', $result);
            } else {
                $error = 'Invalid API response format';
            }
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('scp-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('scp-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['markdown' => $result]);
            }
            exit;
        }
    }
}

/**
 * Scrape URL content with Chrome browser simulation
 * 
 * @param string $url URL to scrape
 * @return string|false Page content or false on error
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Content Parser</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Simple Content Parser</h1>
            <p>AI-powered web page content extractor</p>
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
                        <h2 style="color: #111827; font-size: 20px;">Parsed Content</h2>
                    </div>
                    
                    <textarea class="markdown-result" readonly><?php echo htmlspecialchars($result); ?></textarea>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="scrapingForm">
                <div class="form-group">
                    <label for="url">Web page URL:</label>
                    <input 
                        type="url" 
                        id="url" 
                        name="url" 
                        value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>"
                        placeholder="https://example.com/article"
                        required
                    >
                    <div class="file-info">
                        Enter the full URL of the web page you want to parse.
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
                    📄 Parse Content
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Parse
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('url').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
