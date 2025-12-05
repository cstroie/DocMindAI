<?php
/**
 * Discharge Paper Analyzer
 * 
 * A PHP web application that uses AI to analyze patient discharge papers and summarize
 * key medical information for radiology use in a structured JSON format.
 * 
 * Features:
 * - AI-powered analysis of patient discharge papers
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
 * - API endpoint: POST /pda.php with discharge paper data
 * 
 * API Usage:
 * POST /dpa.php
 * Parameters:
 * - report (required): Patient discharge paper text
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: ro)
 * 
 * Response:
 * {
 *   "pathologic": "yes/no",
 *   "severity": 0-10,
 *   "summary": "summary text"
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
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['dpa-model']) ? $_COOKIE['dpa-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['dpa-language']) ? $_COOKIE['dpa-language'] : 'ro'));

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'qwen2.5:1.5b'; // Default to a valid model
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
 * Contains instructions for analyzing radiology reports and returning structured JSON
 * Includes language-specific instructions and examples
 */
$SYSTEM_PROMPT = "You are a medical assistant analyzing patient discharge papers for radiology relevance.

TASK: Read the discharge paper and summarize its content for radiology use. Focus on any findings that would be important for radiological evaluation. Do not exceed 50 words.
" . getLanguageInstruction($LANGUAGE) . "

OUTPUT FORMAT (JSON):
{
  \"pathologic\": \"yes/no\",
  \"severity\": 0-10,
  \"summary\": \"summary paragraph\",
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"]
}

RULES:
- \"pathologic\": \"yes\" if the discharge paper contains any findings relevant to radiology
- \"severity\": 0=normal, 1=minimal, 5=moderate, 10=critical/urgent
- \"summary\": maximum 50 words summarizing radiology-relevant information
- \"keywords\": exactly 3 relevant medical keywords from the discharge paper
- Focus on findings, conditions, treatments affecting radiology
- Ignore non-radiology relevant information
- Respond ONLY with the JSON, without additional text

EXAMPLES:

Discharge: \"Patient discharged after treatment for pneumonia. Chest X-ray shows resolved infiltrate.\"
Response: {\"pathologic\": \"yes\", \"severity\": 2, \"summary\": \"Patient treated for pneumonia with resolved infiltrate on chest X-ray. No current radiological concerns.\", \"keywords\": [\"pneumonia\", \"chest X-ray\", \"infiltrate\"]}

Discharge: \"Post-operative knee surgery. X-ray shows proper hardware placement.\"
Response: {\"pathologic\": \"no\", \"severity\": 0, \"summary\": \"Post-operative knee surgery with proper hardware placement confirmed on X-ray. Normal findings.\", \"keywords\": [\"knee surgery\", \"X-ray\", \"hardware placement\"]}

Discharge: \"Acute appendicitis, laparoscopic appendectomy. Post-op CT showed small abscess.\"
Response: {\"pathologic\": \"yes\", \"severity\": 5, \"summary\": \"Post-operative appendectomy patient with small abscess identified on CT scan requiring monitoring.\", \"keywords\": [\"appendectomy\", \"CT scan\", \"abscess\"]}";

/**
 * Application state variables
 * @var array|null $result Analysis result data
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for report analysis
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['report'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['report']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $report = trim(isset($_POST['report']) ? $_POST['report'] : $_GET['report']);
    
    // Validate report length (prevent extremely large inputs)
    if (strlen($report) > 10000) {
        $error = 'The report is too long. Maximum 10000 characters allowed.';
        $processing = false;
    } 
    // Validate report is not empty after trimming
    elseif (empty($report)) {
        $error = 'The report cannot be empty.';
        $processing = false;
    }
    
    // Only proceed with API call if validation passed
    if ($processing) {
    
    // Prepare API request
    $data = [
        'model' => $MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $SYSTEM_PROMPT],
            ['role' => 'user', 'content' => "REPORT TO ANALYZE:\n" . $report]
        ]
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
            } elseif (!isset($result['pathologic']) || !isset($result['severity']) || !isset($result['summary']) || !isset($result['keywords'])) {
                $error = 'JSON response missing required fields';
            } elseif (!in_array($result['pathologic'], ['yes', 'no'])) {
                $error = 'Invalid pathologic value in response';
            } elseif (!is_numeric($result['severity']) || $result['severity'] < 0 || $result['severity'] > 10) {
                $error = 'Invalid severity value in response';
            } elseif (!is_string($result['summary']) || empty($result['summary'])) {
                $error = 'Invalid summary value in response';
            } elseif (!is_array($result['keywords']) || count($result['keywords']) < 1) {
                $error = 'Invalid keywords in response (must be array with at least 1 item)';
            } else {
                // Validate keywords array contents (take first 3 if more are provided)
                $result['keywords'] = array_slice($result['keywords'], 0, 3);
                foreach ($result['keywords'] as $keyword) {
                    if (!is_string($keyword) || empty($keyword)) {
                        $error = 'Invalid keyword in response';
                        break;
                    }
                }
            }
        } else {
            $error = 'No JSON found in response: ' . $content;
        }
    } else {
        $error = 'Invalid API response format';
    }
    
    // Set cookies with the selected model and language only for web requests
    if (!$is_api_request) {
        setcookie('dpa-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        setcookie('dpa-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
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
} // Close the if ($processing) block
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discharge Paper Analyzer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>🏥 Discharge paper analyzer</h1>
            <p>AI-powered summary of patient discharge papers for radiology use</p>
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
                        <div>
                            <span class="pathology-badge <?php echo $result['pathologic'] === 'yes' ? 'pathology-yes' : 'pathology-no'; ?>">
                                <?php echo htmlspecialchars($result['summary']); ?>
                            </span>
                        </div>
                    </header>
                    
                    <section>
                        <h3>Severity level</h3>
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <span style="font-weight: 600; color: <?php echo getSeverityColor($result['severity']); ?>">
                                <?php echo getSeverityLabel($result['severity']); ?> (<?php echo $result['severity']; ?>/10)
                            </span>
                            <progress class="severity-bar" value="<?php echo $result['severity']; ?>" max="10" data-severity="<?php echo $result['severity']; ?>" style="flex: 1;"></progress>
                        </div>
                    </section>
                    
                    <footer>
                        <h3>Keywords</h3>
                        <div>
                            <?php foreach ($result['keywords'] as $keyword): ?>
                                <span><?php echo htmlspecialchars($keyword); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </footer>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="analysisForm">
                <fieldset>
                    <label for="report">Patient discharge paper:</label>
                    <textarea 
                        id="report" 
                        name="report" 
                        rows="8" 
                        required
                        placeholder="Enter the patient discharge paper here...&#10;&#10;Example: Patient discharged after treatment for pneumonia. Chest X-ray shows resolved infiltrate."
                    ><?php echo isset($_POST['report']) ? htmlspecialchars($_POST['report']) : (isset($_GET['report']) ? htmlspecialchars($_GET['report']) : ''); ?></textarea>
                    <small>
                        Enter the patient discharge paper you want to analyze.
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
                    📋 Analyze report
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New analysis
                </button>
            </form>
        </main>
    </div>
    
    <script>
        // Set progress bar color based on severity
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.severity-bar');
            if (progressBar) {
                const severity = progressBar.dataset.severity;
                const color = getSeverityColor(severity);
                
                // Set color for WebKit browsers
                progressBar.style.setProperty('--progress-color', color);
                
                // For Firefox, we need to use a different approach
                if (typeof InstallTrigger !== 'undefined') {
                    // Firefox
                    progressBar.style.accentColor = color;
                }
            }
        });
        
        // Helper function to get severity color (matching PHP function)
        function getSeverityColor(severity) {
            if (severity == 0) return '#10b981'; // green
            if (severity <= 3) return '#3b82f6'; // blue
            if (severity <= 6) return '#f59e0b'; // orange
            return '#ef4444'; // red
        }
        
        function clearForm() {
            document.getElementById('report').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
