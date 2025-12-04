<?php
/**
 * Web Page Summarizer
 * 
 * A PHP web application that scrapes web pages and returns a structured summary
 * of the most important points in the article.
 * 
 * Features:
 * - Web scraping with Chrome browser simulation
 * - AI-powered content summarization
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
 * - API endpoint: POST /sum.php with URL
 * 
 * API Usage:
 * POST /sum.php
 * Parameters:
 * - url (required): URL to summarize
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "title": "article title",
 *   "summary": "main summary",
 *   "key_points": ["point 1", "point 2", ...],
 *   "keywords": ["keyword1", "keyword2", ...]
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
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['sum-model']) ? $_COOKIE['sum-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['sum-language']) ? $_COOKIE['sum-language'] : 'en'));

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
 * Contains instructions for summarizing web page content in structured format
 */
$SYSTEM_PROMPT = "You are a content summarization assistant. Your task is to identify the main content of a web page and create a structured summary of the most important points.

" . getLanguageInstruction($LANGUAGE) . "

OUTPUT FORMAT (JSON):
{
  \"title\": \"article title\",
  \"summary\": \"main summary paragraph\",
  \"key_points\": [\"point 1\", \"point 2\", \"point 3\", \"point 4\", \"point 5\"],
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}

RULES:
- \"title\": Extract or create a concise title for the main article
- \"summary\": Create a 2-3 sentence summary of ONLY the main article content
- \"key_points\": Extract exactly 5 key points from the main article content (as strings)
- \"keywords\": Extract exactly 3 relevant keywords from the main content (as strings)
- Focus ONLY on the primary article content
- IGNORE navigation menus, sidebars, footers, ads, related articles, and other secondary content
- IGNORE links, comments, and social media elements
- Respond ONLY with the JSON, without additional text or markdown formatting

EXAMPLE:

Input: A web page with a main article about climate change, plus sidebars and navigation
Response: {
  \"title\": \"Climate Change: Latest Scientific Findings\",
  \"summary\": \"Recent studies show global temperatures have risen by 1.2°C since pre-industrial times. The primary driver is greenhouse gas emissions from human activities. Immediate action is needed to limit warming to 1.5°C.\",
  \"key_points\": [
    \"Global temperatures have risen 1.2°C since pre-industrial times\",
    \"Human activities are the primary cause of climate change\",
    \"Sea levels are rising at an accelerating rate\",
    \"Extreme weather events are becoming more frequent\",
    \"Limiting warming to 1.5°C requires immediate action\"
  ],
  \"keywords\": [\"climate change\", \"global warming\", \"greenhouse gases\"]
}";

/**
 * Application state variables
 * @var array|null $result Structured summary result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for URL summarization
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
                    ['role' => 'user', 'content' => "URL: " . $url . "\n\nCONTENT TO SUMMARIZE:\n" . $scraped_content]
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000
            ];
            
            // Make API request using common function
            $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
            
            if (isset($response_data['error'])) {
                $error = $response_data['error'];
            } elseif (isset($response_data['choices'][0]['message']['content'])) {
                $content = trim($response_data['choices'][0]['message']['content']);
                
                // Extract JSON from response (in case model adds extra text)
                if (preg_match('/\{[^}]+\}/', $content, $matches)) {
                    $json_str = $matches[0];
                    $result = json_decode($json_str, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = 'Invalid JSON response: ' . json_last_error_msg();
                    } elseif (!isset($result['title']) || !isset($result['summary']) || 
                              !isset($result['key_points']) || !isset($result['keywords'])) {
                        $error = 'JSON response missing required fields';
                    } elseif (!is_string($result['title']) || empty($result['title'])) {
                        $error = 'Invalid title in response';
                    } elseif (!is_string($result['summary']) || empty($result['summary'])) {
                        $error = 'Invalid summary in response';
                    } elseif (!is_array($result['key_points']) || count($result['key_points']) < 3) {
                        $error = 'Invalid key_points in response (must be array with at least 3 items)';
                    } elseif (!is_array($result['keywords']) || count($result['keywords']) < 1) {
                        $error = 'Invalid keywords in response (must be array with at least 1 item)';
                    } else {
                        // Validate key_points array contents (take first 5 if more are provided)
                        $result['key_points'] = array_slice($result['key_points'], 0, 5);
                        foreach ($result['key_points'] as $point) {
                            if (!is_string($point) || empty($point)) {
                                $error = 'Invalid key point in response';
                                break;
                            }
                        }
                        
                        // Validate keywords array contents (take first 3 if more are provided)
                        $result['keywords'] = array_slice($result['keywords'], 0, 3);
                        if (!$error) {
                            foreach ($result['keywords'] as $keyword) {
                                if (!is_string($keyword) || empty($keyword)) {
                                    $error = 'Invalid keyword in response';
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    $error = 'No JSON found in response: ' . $content;
                }
            } else {
                $error = 'Invalid API response format';
            }
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('sum-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('sum-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
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

/**
 * Scrape URL content with Chrome browser simulation
 * 
 * @param string $url URL to scrape
 * @return string|false Page content or false on error
 */
function scrapeUrl($url) {
    // Create a temporary file to store cookies
    $cookie_file = tempnam(sys_get_temp_dir(), 'sum_cookies');
    
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
    <title>Web Page Summarizer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>📝 Web Page Summarizer</h1>
            <p>AI-powered structured summary of web articles</p>
        </header>

        <main class="content">
            <?php if ($error): ?>
                <section class="error">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </section>
            <?php endif; ?>
            
            <?php if ($result): ?>
                <article class="result-card">
                    <header class="result-header">
                        <h2 style="color: #111827; font-size: 20px;"><?php echo htmlspecialchars($result['title']); ?></h2>
                    </header>
                    
                    <section class="summary-box">
                        <div class="summary-label">Summary</div>
                        <div class="summary-text"><?php echo htmlspecialchars($result['summary']); ?></div>
                    </section>
                    
                    <section style="margin-top: 20px;">
                        <div class="summary-label">Key Points</div>
                        <ul style="padding-left: 20px; margin-top: 8px;">
                            <?php foreach ($result['key_points'] as $point): ?>
                                <li style="margin-bottom: 8px;"><?php echo htmlspecialchars($point); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    
                    <section style="margin-top: 20px;">
                        <div class="summary-label">Keywords</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px;">
                            <?php foreach ($result['keywords'] as $keyword): ?>
                                <span style="background: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 20px; font-size: 14px;">
                                    <?php echo htmlspecialchars($keyword); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="summarizeForm">
                <fieldset>
                    <label for="url">Web page URL:</label>
                    <input 
                        type="url" 
                        id="url" 
                        name="url" 
                        value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>"
                        placeholder="https://example.com/article"
                        required
                    >
                    <small class="file-info">
                        Enter the full URL of the web page you want to summarize.
                    </small>
                
                    <label for="model">AI model:</label>
                    <select id="model" name="model">
                        <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($MODEL === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                
                    <label for="language">Response language:</label>
                    <select id="language" name="language">
                        <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($LANGUAGE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📋 Summarize Content
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Summary
                </button>
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
