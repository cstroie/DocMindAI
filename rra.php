<?php
/**
 * Radiology Report Analyzer
 * 
 * A PHP web application that uses AI to analyze radiology reports and extract
 * key medical information in a structured JSON format.
 * 
 * Features:
 * - AI-powered analysis of radiology reports
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
 * - API endpoint: POST /rra.php with report data
 * 
 * API Usage:
 * POST /rra.php
 * Parameters:
 * - report (required): Radiology report text
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: ro)
 * 
 * Response:
 * {
 *   "pathologic": "yes/no",
 *   "severity": 0-10,
 *   "diagnostic": "diagnosis text"
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
        'gemma3:1b' => 'Gemma 3 (1B)',
        'gemma2:2b' => 'Gemma 2 (2B)',
        'qwen3:1.7b' => 'Qwen 3 (1.7B)',
        'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)',
        'phi3:mini' => 'Phi 3 Mini (3.8B)',
        'llama3.2:1b' => 'Llama 3.2 (1B)'
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
 * Get selected model and language from POST data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_COOKIE['rra_last_model']) ? $_COOKIE['rra_last_model'] : 'qwen2.5:1.5b');
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_COOKIE['rra_last_language']) ? $_COOKIE['rra_last_language'] : 'ro');

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
$SYSTEM_PROMPT = "You are a medical assistant analyzing radiology reports.

TASK: Read the report and extract the main pathological information in JSON format.

" . $language_instructions[$LANGUAGE] . "

OUTPUT FORMAT (JSON):
{
  \"pathologic\": \"yes/no\",
  \"severity\": 1-10,
  \"diagnostic\": \"1-5 words\"
}

RULES:
- \"pathologic\": \"yes\" if any anomaly exists, otherwise \"no\"
- \"severity\": 1=minimal, 5=moderate, 10=critical/urgent
- \"diagnostic\": maximum 5 words (e.g., \"fracture\", \"pneumonia\", \"lung nodule\")
- If everything is normal: {\"pathologic\": \"no\", \"severity\": 0, \"diagnostic\": \"normal\"}
- Ignore spelling errors
- Respond ONLY with the JSON, without additional text

EXAMPLES:

Report: \"Hazy opacity in the left mid lung field, possibly representing consolidation or infiltrate.\"
Response: {\"pathologic\": \"yes\", \"severity\": 6, \"diagnostic\": \"pulmonary consolidation\"}

Report: \"No pathological changes. Heart of normal size.\"
Response: {\"pathologic\": \"no\", \"severity\": 0, \"diagnostic\": \"normal\"}

Report: \"Displaced fracture of the right distal femur with significant hematoma\"
Response: {\"pathologic\": \"yes\", \"severity\": 8, \"diagnostic\": \"femur fracture\"}";

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
 * Handle POST request for report analysis
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['report'])) {
    $processing = true;
    $is_api_request = !isset($_POST['submit']); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $report = trim($_POST['report']);
    
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
        ],
        'temperature' => 0.1,
        'max_tokens' => 150
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
            $content = trim($response_data['choices'][0]['message']['content']);
            
            // Extract JSON from response (in case model adds extra text)
            if (preg_match('/\{[^}]+\}/', $content, $matches)) {
                $json_str = $matches[0];
                $result = json_decode($json_str, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error = 'Invalid JSON response: ' . json_last_error_msg();
                } elseif (!isset($result['pathologic']) || !isset($result['severity']) || !isset($result['diagnostic'])) {
                    $error = 'JSON response missing required fields';
                } elseif (!in_array($result['pathologic'], ['yes', 'no'])) {
                    $error = 'Invalid pathologic value in response';
                } elseif (!is_numeric($result['severity']) || $result['severity'] < 0 || $result['severity'] > 10) {
                    $error = 'Invalid severity value in response';
                } elseif (!is_string($result['diagnostic']) || empty($result['diagnostic'])) {
                    $error = 'Invalid diagnostic value in response';
                }
            } else {
                $error = 'No JSON found in response: ' . $content;
            }
        } else {
            $error = 'Invalid API response format';
        }
    }
    
    curl_close($ch);
    
    // Set cookies with the selected model and language
    setcookie('rra_last_model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
    setcookie('rra_last_language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
    
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
    <title>Radiology report analyzer</title>
    <link rel="stylesheet" href="style.css">
    <script defer src="scripts.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 Radiology report analyzer</h1>
            <p>AI-powered automatic analysis of medical reports</p>
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
                        <h2 style="color: #111827; font-size: 20px;">Analysis Result</h2>
                        <span class="pathology-badge <?php echo $result['pathologic'] === 'yes' ? 'pathology-yes' : 'pathology-no'; ?>">
                            <?php echo $result['pathologic'] === 'yes' ? '⚠️ Pathological' : '✓ Normal'; ?>
                        </span>
                    </div>
                    
                    <div class="severity-container">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong style="color: #374151;">Severity:</strong>
                            <span style="font-weight: 600; color: <?php echo getSeverityColor($result['severity']); ?>">
                                <?php echo getSeverityLabel($result['severity']); ?> (<?php echo $result['severity']; ?>/10)
                            </span>
                        </div>
                        <div class="severity-bar">
                            <div class="severity-fill" style="width: <?php echo $result['severity'] * 10; ?>%; background: <?php echo getSeverityColor($result['severity']); ?>;"></div>
                        </div>
                    </div>
                    
                    <div class="diagnostic-box">
                        <div class="diagnostic-label">Diagnosis</div>
                        <div class="diagnostic-text"><?php echo htmlspecialchars($result['diagnostic']); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="analysisForm">
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
                    <label for="report">Radiology report:</label>
                    <textarea 
                        id="report" 
                        name="report" 
                        rows="8" 
                        required
                        placeholder="Enter the radiology report here...&#10;&#10;Example: Hazy opacity in the left mid lung field, possibly representing consolidation or infiltrate. No pleural effusion, pneumothorax or pneumoperitoneum."
                    ><?php echo isset($_POST['report']) ? htmlspecialchars($_POST['report']) : ''; ?></textarea>
                </div>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    Analyze report
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New analysis
                </button>
            </form>
        </div>
    </div>
    
    <script>
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
