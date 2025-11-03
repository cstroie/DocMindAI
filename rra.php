<?php
// Configuration - Load from config.php if available, otherwise use defaults
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Safe defaults
    $API_ENDPOINT = 'http://192.168.3.16:11434/v1/chat/completions';
    $API_KEY = '';
}

// Available models
$AVAILABLE_MODELS = [
    'gemma3:1b' => 'Gemma 3 (1B)',
    'gemma2:2b' => 'Gemma 2 (2B)',
    'qwen3:1.7b' => 'Qwen 3 (1.7B)',
    'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)',
    'phi3:mini' => 'Phi 3 Mini (3.8B)',
    'llama3.2:1b' => 'Llama 3.2 (1B)'
];

// Available output languages
$AVAILABLE_LANGUAGES = [
    'ro' => 'Română',
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
];

// Get selected model and language
$MODEL = isset($_POST['model']) ? $_POST['model'] : 'qwen2.5:1.5b';
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : 'ro';

// System prompt with language support
$language_instructions = [
    'ro' => 'Respond in Romanian.',
    'en' => 'Respond in English.',
    'es' => 'Responde en español.',
    'fr' => 'Répondez en français.',
    'de' => 'Antworte auf Deutsch.',
    'it' => 'Rispondi in italiano.'
];

// Validate model selection
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'qwen2.5:1.5b'; // Default to a valid model
}

// Validate language selection
if (!array_key_exists($LANGUAGE, $AVAILABLE_LANGUAGES)) {
    $LANGUAGE = 'ro'; // Default to Romanian
}

// System prompt
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

$result = null;
$error = null;
$processing = false;
$is_api_request = false;

// Handle POST request
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
    $ch = curl_init($API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
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

// Helper function to get severity color
function getSeverityColor($severity) {
    if ($severity == 0) return '#10b981'; // green
    if ($severity <= 3) return '#3b82f6'; // blue
    if ($severity <= 6) return '#f59e0b'; // orange
    return '#ef4444'; // red
}

// Helper function to get severity label
function getSeverityLabel($severity) {
    if ($severity == 0) return 'Normal';
    if ($severity <= 3) return 'Minor';
    if ($severity <= 6) return 'Moderat';
    if ($severity <= 8) return 'Sever';
    return 'Critic';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radiology report analyzer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }
        
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            transition: border-color 0.3s;
        }
        
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary {
            width: 100%;
        }
        
        .btn-secondary {
            background: #6b7280;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            box-shadow: 0 10px 20px rgba(107, 114, 128, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .result-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 2px solid #e5e7eb;
        }
        
        .result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .pathology-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .pathology-yes {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .pathology-no {
            background: #d1fae5;
            color: #059669;
        }
        
        .severity-container {
            margin: 20px 0;
        }
        
        .severity-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .severity-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .severity-label {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .diagnostic-box {
            background: white;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .diagnostic-label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .diagnostic-text {
            font-size: 20px;
            font-weight: 600;
            color: #111827;
        }
        
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #dc2626;
        }
        
        .config-info {
            background: #eff6ff;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #1e40af;
            margin-bottom: 20px;
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }
    </style>
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
