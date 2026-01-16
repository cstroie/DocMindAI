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
 * Get selected model, language, and format from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['scp-model']) ? $_COOKIE['scp-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['scp-language']) ? $_COOKIE['scp-language'] : 'en'));
$FORMAT = isset($_POST['format']) ? $_POST['format'] : (isset($_GET['format']) ? $_GET['format'] : (isset($_COOKIE['scp-format']) ? $_COOKIE['scp-format'] : 'markdown'));

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
 * Validate format selection
 * Falls back to Markdown if invalid format is selected
 */
if (!in_array($FORMAT, ['markdown', 'dokuwiki'])) {
    $FORMAT = 'markdown'; // Default to Markdown
}

/**
 * Available output formats
 */
$AVAILABLE_FORMATS = [
    'markdown' => 'Markdown',
    'dokuwiki' => 'DokuWiki'
];

/**
 * System prompt for the AI model
 * Contains instructions for extracting main content and converting to the selected format
 */
$SYSTEM_PROMPT = "You are a content extraction assistant. Your task is to identify the main content of a web page and convert it to clean, well-formatted " . $AVAILABLE_FORMATS[$FORMAT] . " document.

Instructions:
CRITICAL INSTRUCTION: " . getLanguageInstruction($LANGUAGE) . "

1. Extract ONLY the primary article content (main text, key information)
2. IGNORE navigation menus, sidebars, footers, ads, related articles, and other secondary content
3. IGNORE links, comments, and social media elements
4. Convert the main content to clean " . $AVAILABLE_FORMATS[$FORMAT] . " format with proper headings, lists, etc.
5. Preserve important formatting like bold, italics, links
6. Do not include any explanations or extra text
7. Return ONLY the " . $AVAILABLE_FORMATS[$FORMAT] . " content

" . ($FORMAT === 'dokuwiki' ? "DOKUWIKI FORMATTING GUIDE:
- Headings: Use ====== for h1, ===== for h2, ==== for h3, etc.
- Bold: Use **bold text**
- Italic: Use //italic text//
- Links: Use [[url|link text]] or [[url]]
- Bullet lists: Use * for each item
- Numbered lists: Use # for each item
- Code blocks: Use <code>...</code> or <file>...</file>

" : "") . "Example:
Input HTML might contain a full web page with headers, nav bars, sidebars, etc.
Output should be just the main article content in clean " . $AVAILABLE_FORMATS[$FORMAT] . " format.";

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
 * Handle POST/GET requests for URL scraping
 * Processes both web form submissions and API requests
 * Validates input, scrapes URL, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['url']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $url = trim(isset($_POST['url']) ? $_POST['url'] : $_GET['url']);
    
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
                ]
            ];
            
            // Make API request using common function
            $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
            
            if (isset($response_data['error'])) {
                $error = $response_data['error'];
            } elseif (isset($response_data['choices'][0]['message']['content'])) {
                $result = trim($response_data['choices'][0]['message']['content']);
                // Remove markdown code fences if present
                $result = preg_replace('/^```(?:markdown)?(?:dokuwiki)?\s*(.*?)\s*```$/s', '$1', $result);
            } else {
                $error = 'Invalid API response format';
            }
        }
        
        // Set cookies with the selected model, language, and format only for web requests
        if (!$is_api_request) {
            setcookie('scp-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('scp-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('scp-format', $FORMAT, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
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
    <title>DocMind AI - Content Parser</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%94%97%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>🔗 DocMind AI - Content Parser</h1>
            <p>AI-powered web page content extractor</p>
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
                        <h2>Markdown / DokuWiki Content</h2>
                    </header>
                    
                    <pre class="markdown"><?php echo htmlspecialchars($result); ?></pre>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="scrapingForm">
                <fieldset>
                    <label for="url">Web page URL:</label>
                    <input 
                        type="url" 
                        id="url" 
                        name="url" 
                        value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : (isset($_GET['url']) ? htmlspecialchars($_GET['url']) : ''); ?>"
                        placeholder="https://example.com/article"
                        required
                    >
                    <small>
                        Enter the full URL of the web page you want to parse.
                    </small>
                
                    <label for="format">Output format:</label>
                    <select id="format" name="format">
                        <?php foreach ($AVAILABLE_FORMATS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($FORMAT === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the output format for the parsed content.
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
                        Select the AI model to use for content parsing.
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
                        Select the language for the parsed content output.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    🔍 Parse Content
                </button>
                
                <div class="button-grid">
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        🔄 New Parse
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
            document.getElementById('url').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
