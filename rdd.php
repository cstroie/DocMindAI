<?php
/**
 * Radiology Differential Diagnosis
 * 
 * A PHP web application that uses AI to analyze radiology reports and provide
 * differential diagnoses with supporting information and references.
 * 
 * Features:
 * - AI-powered differential diagnosis generation
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
 * - API endpoint: POST /rdd.php with report data
 * 
 * API Usage:
 * POST /rdd.php
 * Parameters:
 * - report (required): Radiology report text
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "diagnoses": [
 *     {
 *       "condition": "diagnosis name",
 *       "probability": 0-100,
 *       "description": "detailed explanation",
 *       "supporting_features": ["feature1", "feature2"],
 *       "references": ["reference1", "reference2"]
 *     }
 *   ]
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
        'qwen3:1.7b' => 'Qwen 3 (1.7B)',
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
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['rdd-model']) ? $_COOKIE['rdd-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['rdd-language']) ? $_COOKIE['rdd-language'] : 'en'));

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
 * Contains instructions for generating differential diagnoses with supporting information
 */
$SYSTEM_PROMPT = "You are a radiology expert specializing in differential diagnosis. Your task is to analyze radiology reports and provide a ranked list of differential diagnoses with supporting information.

" . getLanguageInstruction($LANGUAGE) . "

OUTPUT FORMAT (JSON):
{
  \"diagnoses\": [
    {
      \"condition\": \"medical condition name\",
      \"probability\": 85,
      \"description\": \"detailed explanation of the condition\",
      \"supporting_features\": [\"radiological feature 1\", \"radiological feature 2\", \"clinical correlation\"],
      \"references\": [\"reference 1\", \"reference 2\"]
    }
  ]
}

RULES:
- Provide exactly 3 differential diagnoses, ranked by probability (highest first)
- \"condition\": Name of the medical condition
- \"probability\": Likelihood as a percentage (0-100)
- \"description\": Detailed explanation of the condition (2-3 sentences)
- \"supporting_features\": 2-4 key radiological findings that support this diagnosis
- \"references\": 1-2 relevant medical references or resources
- Focus ONLY on radiological findings in the report
- Do not include treatments or management recommendations
- Respond ONLY with the JSON, without additional text

EXAMPLE:

Report: \"Hazy opacity in the left mid lung field, possibly representing consolidation or infiltrate. No pleural effusion, pneumothorax or pneumoperitoneum.\"
Response: {
  \"diagnoses\": [
    {
      \"condition\": \"Bacterial Pneumonia\",
      \"probability\": 75,
      \"description\": \"Infection of the lung parenchyma causing consolidation. Common pathogens include Streptococcus pneumoniae and Haemophilus influenzae. Presents with fever, cough, and focal consolidation on imaging.\",
      \"supporting_features\": [\"Hazy opacity in lung field\", \"Consolidation pattern\", \"No pleural effusion\"],
      \"references\": [\"Harrison's Principles of Internal Medicine, 21st Edition\", \"Radiology Assistant: Pneumonia Patterns\"]
    },
    {
      \"condition\": \"Viral Pneumonia\",
      \"probability\": 60,
      \"description\": \"Viral infection causing interstitial and alveolar inflammation. Common viruses include influenza, RSV, and COVID-19. May present with similar imaging findings to bacterial pneumonia.\",
      \"supporting_features\": [\"Hazy infiltrate pattern\", \"Single lobe involvement\", \"Absence of cavitation\"],
      \"references\": [\"Chest Imaging Patterns in Viral Pneumonia, Radiographics\", \"ATS Guidelines on Community-Acquired Pneumonia\"]
    },
    {
      \"condition\": \"Pulmonary Edema\",
      \"probability\": 40,
      \"description\": \"Accumulation of fluid in the lung interstitium and alveoli. Can be cardiogenic (heart failure) or non-cardiogenic (ARDS). May present with bilateral hazy opacities.\",
      \"supporting_features\": [\"Hazy opacity\", \"Single lobe distribution (atypical)\", \"No pleural effusion\"],
      \"references\": [\"ACR Appropriateness Criteria: Acute Respiratory Illness\", \"Radiology Education: Pulmonary Edema Patterns\"]
    }
  ]
}";

/**
 * Application state variables
 * @var array|null $result Differential diagnoses result
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for differential diagnosis
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
                ['role' => 'user', 'content' => "RADIOLOGY REPORT TO ANALYZE:\n" . $report]
            ]
        ];
        
        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
        
        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $content = trim($response_data['choices'][0]['message']['content']);
            
            // Try to extract JSON from response (in case model adds extra text)
            $json_str = null;
            
            // First try to find JSON between code fences
            if (preg_match('/```(?:json)?\s*({.*?})\s*```/s', $content, $matches)) {
                $json_str = $matches[1];
            } 
            // Then try to find any JSON object
            elseif (preg_match('/\{.*\}/s', $content, $matches)) {
                $json_str = $matches[0];
            }
            
            if ($json_str) {
                // Clean up the JSON string
                $json_str = trim($json_str);
                
                // Try to decode JSON
                $result = json_decode($json_str, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Try to fix common JSON issues
                    $json_str = preg_replace('/,\s*([\]}])/m', '$1', $json_str); // Remove trailing commas
                    $json_str = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_str); // Add quotes to keys
                    $json_str = preg_replace('/:\s*\'([^\']*)\'/', ':"$1"', $json_str); // Replace single quotes with double quotes
                    $json_str = preg_replace('/\s+/', ' ', $json_str); // Normalize whitespace
                    
                    $result = json_decode($json_str, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = 'Invalid JSON response: ' . json_last_error_msg() . ' (Content: ' . substr($json_str, 0, 200) . '...)';
                    }
                }
                
                if (!$error) {
                    if (!isset($result['diagnoses']) || !is_array($result['diagnoses'])) {
                        $error = 'JSON response missing diagnoses array';
                    } elseif (count($result['diagnoses']) < 1) {
                        $error = 'No differential diagnoses provided';
                    } else {
                        // Validate each diagnosis
                        foreach ($result['diagnoses'] as $index => $diagnosis) {
                            if (!isset($diagnosis['condition']) || !is_string($diagnosis['condition']) || empty($diagnosis['condition'])) {
                                $error = 'Invalid condition in diagnosis ' . ($index + 1);
                                break;
                            }
                            if (!isset($diagnosis['probability']) || !is_numeric($diagnosis['probability']) || 
                                $diagnosis['probability'] < 0 || $diagnosis['probability'] > 100) {
                                $error = 'Invalid probability in diagnosis ' . ($index + 1);
                                break;
                            }
                            if (!isset($diagnosis['description']) || !is_string($diagnosis['description']) || empty($diagnosis['description'])) {
                                $error = 'Invalid description in diagnosis ' . ($index + 1);
                                break;
                            }
                            if (!isset($diagnosis['supporting_features']) || !is_array($diagnosis['supporting_features']) || 
                                count($diagnosis['supporting_features']) < 1) {
                                $error = 'Invalid supporting features in diagnosis ' . ($index + 1);
                                break;
                            }
                            if (!isset($diagnosis['references']) || !is_array($diagnosis['references'])) {
                                $error = 'Invalid references in diagnosis ' . ($index + 1);
                                break;
                            }
                            
                            // Validate supporting features
                            foreach ($diagnosis['supporting_features'] as $feature) {
                                if (!is_string($feature) || empty($feature)) {
                                    $error = 'Invalid supporting feature in diagnosis ' . ($index + 1);
                                    break 2;
                                }
                            }
                            
                            // Validate references
                            foreach ($diagnosis['references'] as $reference) {
                                if (!is_string($reference) || empty($reference)) {
                                    $error = 'Invalid reference in diagnosis ' . ($index + 1);
                                    break 2;
                                }
                            }
                        }
                    }
                }
            } else {
                $error = 'No JSON found in response: ' . substr($content, 0, 200) . '...';
            }
        } else {
            $error = 'Invalid API response format';
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('rdd-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('rdd-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
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
    <title>Radiology Differential Diagnosis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%A9%BA%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>🩺 Radiology Differential Diagnosis</h1>
            <p>AI-powered differential diagnosis generator for radiology reports</p>
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
                        <h2>Differential Diagnoses</h2>
                    </header>
                    
                    <?php foreach ($result['diagnoses'] as $index => $diagnosis): ?>
                    <section class="diagnosis-item">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 12px;">
                            <h3 style="margin: 0;">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($diagnosis['condition']); ?>
                            </h3>
                            <span style="font-weight: 600; color: <?php echo getProbabilityColor($diagnosis['probability']); ?>">
                                <?php echo $diagnosis['probability']; ?>%
                            </span>
                        </div>
                        
                        <p><?php echo htmlspecialchars($diagnosis['description']); ?></p>
                        
                        <div>
                            <h4>Supporting Radiological Features:</h4>
                            <ul>
                                <?php foreach ($diagnosis['supporting_features'] as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <?php if (!empty($diagnosis['references'])): ?>
                        <div>
                            <h4>References:</h4>
                            <ul>
                                <?php foreach ($diagnosis['references'] as $reference): ?>
                                    <li><?php echo htmlspecialchars($reference); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php if ($index < count($result['diagnoses']) - 1): ?>
                        <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e7eb;">
                    <?php endif; ?>
                    <?php endforeach; ?>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="diagnosisForm">
                <fieldset>
                    <label for="report">Radiology report:</label>
                    <textarea 
                        id="report" 
                        name="report" 
                        rows="8" 
                        required
                        placeholder="Enter the radiology report here...&#10;&#10;Example: Hazy opacity in the left mid lung field, possibly representing consolidation or infiltrate. No pleural effusion, pneumothorax or pneumoperitoneum."
                    ><?php echo isset($_POST['report']) ? htmlspecialchars($_POST['report']) : (isset($_GET['report']) ? htmlspecialchars($_GET['report']) : ''); ?></textarea>
                    <small>
                        Enter the radiology report for which you want differential diagnoses.
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
                        Select the AI model to use for differential diagnosis.
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
                        Select the language for the diagnosis output.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    🩺 Generate Differential Diagnosis
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Diagnosis
                </button>
            </form>
        </main>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('report').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
        
        // Helper function to get probability color
        function getProbabilityColor(probability) {
            if (probability >= 80) return '#ef4444'; // red
            if (probability >= 60) return '#f59e0b'; // orange
            if (probability >= 40) return '#3b82f6'; // blue
            return '#10b981'; // green
        }
    </script>
</body>
</html>

<?php
/**
 * Get the color associated with a probability level
 * 
 * @param int $probability Probability percentage (0-100)
 * @return string Hex color code
 */
function getProbabilityColor($probability) {
    if ($probability >= 80) return '#ef4444'; // red
    if ($probability >= 60) return '#f59e0b'; // orange
    if ($probability >= 40) return '#3b82f6'; // blue
    return '#10b981'; // green
}
?>
