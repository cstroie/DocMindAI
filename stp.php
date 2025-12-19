<?php
/**
 * Summarize This Paper
 * 
 * A PHP web application that summarizes academic papers using different structured approaches.
 * 
 * Features:
 * - Text input or file upload (txt/markdown)
 * - Predefined prompt templates
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
 * - API endpoint: POST /stp.php with content
 * 
 * API Usage:
 * POST /stp.php
 * Parameters:
 * - content (optional): Paper content (if no file uploaded)
 * - file (optional): Text/markdown file to summarize
 * - prompt_type (optional): Prompt template to use (default: three_pass)
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "summary": "structured summary based on selected prompt"
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
 * Available prompt templates
 */
$AVAILABLE_PROMPTS = [
    'three_pass' => 'Three-Pass Summary',
    'problem_idea_evidence' => 'Problem–Idea–Evidence'
];

/**
 * Get selected model, language, and prompt from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['stp-model']) ? $_COOKIE['stp-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['stp-language']) ? $_COOKIE['stp-language'] : 'en'));
$PROMPT_TYPE = isset($_POST['prompt_type']) ? $_POST['prompt_type'] : (isset($_GET['prompt_type']) ? $_GET['prompt_type'] : (isset($_COOKIE['stp-prompt']) ? $_COOKIE['stp-prompt'] : 'three_pass'));

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
 * Validate prompt selection
 * Falls back to three_pass if invalid prompt is selected
 */
if (!array_key_exists($PROMPT_TYPE, $AVAILABLE_PROMPTS)) {
    $PROMPT_TYPE = 'three_pass'; // Default to three_pass
}

/**
 * Get system prompt based on selected template
 */
function getSystemPrompt($prompt_type, $language) {
    global $AVAILABLE_LANGUAGES;
    
    switch ($prompt_type) {
        case 'problem_idea_evidence':
            return "You are an academic paper analyzer. Your task is to analyze research papers and extract key information using a structured approach.

" . getLanguageInstruction($language) . "

OUTPUT FORMAT (JSON):
{
  \"problem\": \"problem description\",
  \"idea\": \"main idea\",
  \"evidence\": \"supporting evidence\",
  \"results\": \"meaning of results\"
}

RULES:
- \"problem\": What problem the paper tries to solve
- \"idea\": The main idea or approach they use
- \"evidence\": How they support their idea (methods, data, experiments)
- \"results\": What the results mean and their implications
- Focus on the most important information
- Be concise but comprehensive
- Respond ONLY with the JSON, without additional text

EXAMPLE:
Input: Research paper about a new machine learning algorithm...
Response: {
  \"problem\": \"Current machine learning algorithms struggle with noisy data and require extensive preprocessing.\",
  \"idea\": \"The paper proposes a novel noise-resistant neural network architecture that can handle raw data directly.\",
  \"evidence\": \"They tested their approach on 5 benchmark datasets with varying noise levels and compared performance against 3 state-of-the-art methods.\",
  \"results\": \"The new algorithm achieved 15% better accuracy on average and reduced preprocessing time by 80%.\"
}";

        case 'three_pass':
        default:
            return "You are an academic paper analyzer. Your task is to analyze research papers using a three-pass approach.

" . getLanguageInstruction($language) . "

OUTPUT FORMAT (JSON):
{
  \"pass1\": \"quick skim\",
  \"pass2\": \"main ideas\",
  \"pass3\": \"deeper details\"
}

RULES:
- \"pass1\": A quick skim of what the paper is about (1-2 sentences)
- \"pass2\": The main ideas and why they matter (2-3 sentences)
- \"pass3\": The deeper details you should pay attention to (3-4 sentences)
- Focus on the most important information at each level
- Be concise but comprehensive
- Respond ONLY with the JSON, without additional text

EXAMPLE:
Input: Research paper about a new cancer treatment...
Response: {
  \"pass1\": \"This paper presents a novel immunotherapy approach for treating pancreatic cancer using modified T-cells.\",
  \"pass2\": \"The main contribution is a new method for engineering T-cells to better target pancreatic cancer cells while minimizing side effects. This matters because pancreatic cancer has poor survival rates and current treatments are limited.\",
  \"pass3\": \"The authors used CRISPR technology to modify T-cell receptors, conducted in vitro experiments on 50 cell lines, and performed mouse model trials. Key results show 75% reduction in tumor growth with minimal off-target effects.\"
}";
    }
}

/**
 * Application state variables
 * @var string|null $result Summary result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for paper summarization
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['content']) || (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK))) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['content']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    $content = '';
    
    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        // Validate file type
        $allowed_types = ['text/plain', 'text/markdown'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Only plain text (.txt) and Markdown (.md) files are allowed.';
            $processing = false;
        }
        
        // Validate file size (max 1MB)
        if ($file['size'] > 1024 * 1024) {
            $error = 'The file is too large. Maximum 1MB allowed.';
            $processing = false;
        }
        
        if ($processing) {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                $error = 'Failed to read the uploaded file.';
                $processing = false;
            }
        }
    } 
    // Handle text input
    elseif (!empty($_POST['content']) || !empty($_GET['content'])) {
        $content = trim(isset($_POST['content']) ? $_POST['content'] : $_GET['content']);
    }
    
    // Validate content
    if ($processing && empty($content)) {
        $error = 'No content provided. Please enter text or upload a file.';
        $processing = false;
    }
    
    // Validate content length (prevent extremely large inputs)
    if ($processing && strlen($content) > 50000) {
        $error = 'The content is too long. Maximum 50000 characters allowed.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
        // Get the system prompt based on selected template
        $SYSTEM_PROMPT = getSystemPrompt($PROMPT_TYPE, $LANGUAGE);
        
        // Prepare API request
        $data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                ['role' => 'user', 'content' => "PAPER CONTENT TO ANALYZE:\n" . $content]
            ]
        ];
        
        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
        
        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $result = trim($response_data['choices'][0]['message']['content']);
            // Remove markdown code fences if present
            $result = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $result);
        } else {
            $error = 'Invalid API response format';
        }
        
        // Set cookies with the selected model, language, and prompt only for web requests
        if (!$is_api_request) {
            setcookie('stp-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('stp-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('stp-prompt', $PROMPT_TYPE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['summary' => $result]);
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
    <title>Summarize This Paper</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E📄%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📄 Summarize This Paper</h1>
            <p>AI-powered academic paper summarization with structured approaches</p>
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
                        <h2>Analysis Result</h2>
                    </header>
                    
                        <?php 
                        // Try to decode as JSON first
                        $json_result = json_decode($result, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            // Format JSON result nicely
                            if (isset($json_result['pass1']) && isset($json_result['pass2']) && isset($json_result['pass3'])) {
                                echo '<section>';
                                echo '<h3>Quick Skim</h3>';
                                echo '<p>' . htmlspecialchars($json_result['pass1']) . '</p>';
                                echo '</section>';
                                echo '<section>';
                                echo '<h3>Main Ideas</h3>';
                                echo '<p>' . htmlspecialchars($json_result['pass2']) . '</p>';
                                echo '</section>';
                                echo '<section>';
                                echo '<h3>Deeper Details</h3>';
                                echo '<p>' . htmlspecialchars($json_result['pass3']) . '</p>';
                                echo '</section>';
                            } elseif (isset($json_result['problem']) && isset($json_result['idea']) && isset($json_result['evidence']) && isset($json_result['results'])) {
                                echo '<section>';
                                echo '<h3>Problem</h3>';
                                echo '<p>' . htmlspecialchars($json_result['problem']) . '</p>';
                                echo '</section>';
                                echo '<section>';
                                echo '<h3>Main Idea</h3>';
                                echo '<p>' . htmlspecialchars($json_result['idea']) . '</p>';
                                echo '</section>';
                                echo '<section>';
                                echo '<h3>Evidence</h3>';
                                echo '<p>' . htmlspecialchars($json_result['evidence']) . '</p>';
                                echo '</section>';
                                echo '<section>';
                                echo '<h3>Results Meaning</h3>';
                                echo '<p>' . htmlspecialchars($json_result['results']) . '</p>';
                                echo '</section>';
                            } else {
                                // Generic JSON display
                                echo '<pre>' . htmlspecialchars(json_encode($json_result, JSON_PRETTY_PRINT)) . '</pre>';
                            }
                        } else {
                            // Display as plain text
                            echo '<pre>' . htmlspecialchars($result) . '</pre>';
                        }
                        ?>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="summarizeForm" enctype="multipart/form-data">
                <fieldset>
                    <label for="content">Paper content:</label>
                    <textarea 
                        id="content" 
                        name="content" 
                        rows="8" 
                        placeholder="Enter the paper content here...&#10;&#10;Tip: You can also upload a text or markdown file below."
                    ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : (isset($_GET['content']) ? htmlspecialchars($_GET['content']) : ''); ?></textarea>
                    <small>
                        Enter the paper content you want to analyze, or upload a file below.
                    </small>
                    
                    <label for="file">Or upload a file:</label>
                    <input 
                        type="file" 
                        id="file" 
                        name="file" 
                        accept=".txt,.md,text/plain,text/markdown"
                    >
                    <small>
                        Upload a plain text (.txt) or Markdown (.md) file. Maximum size: 1MB.
                    </small>

                    <label for="prompt_type">Analysis approach:</label>
                    <select id="prompt_type" name="prompt_type">
                        <?php foreach ($AVAILABLE_PROMPTS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($PROMPT_TYPE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the structured approach for analyzing the paper.
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
                        Select the AI model to use for analysis.
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
                        Select the language for the analysis output.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📄 Analyze Paper
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Analysis
                </button>
            </form>
        </main>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('content').value = '';
            document.getElementById('file').value = '';
            document.getElementById('prompt_type').selectedIndex = 0;
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
