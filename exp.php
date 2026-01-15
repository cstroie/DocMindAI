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
 * Predefined prompts for different use cases
 */
$PREDEFINED_PROMPTS = [
    'general_medical' => [
        'label' => 'General Medical Information',
        'prompt' => 'You are a medical assistant. Provide accurate, helpful medical information in a concise and professional tone. Avoid giving specific medical advice. Always recommend consulting with healthcare professionals for personal medical concerns.'
    ],
    'diagnosis_helper' => [
        'label' => 'Diagnosis Helper',
        'prompt' => 'You are a medical diagnosis assistant. Given the following symptoms and patient history, provide possible diagnoses with their likelihood percentages. Include differential diagnoses and recommend appropriate next steps for evaluation.'
    ],
    'treatment_options' => [
        'label' => 'Treatment Options',
        'prompt' => 'You are a treatment options advisor. For the given medical condition, provide evidence-based treatment options including medications, therapies, and lifestyle changes. Include potential side effects and success rates where available.'
    ],
    'medical_research' => [
        'label' => 'Medical Research Summary',
        'prompt' => 'You are a medical researcher. Summarize the latest research on the given medical topic. Include key findings, study methodologies, and clinical significance. Provide references where possible.'
    ],
    'patient_education' => [
        'label' => 'Patient Education',
        'prompt' => 'You are a patient educator. Explain the given medical condition in simple, understandable terms. Include causes, symptoms, treatment options, and prevention strategies. Use analogies and avoid medical jargon.'
    ],
    'medical_coding' => [
        'label' => 'Medical Coding Assistant',
        'prompt' => 'You are a medical coding specialist. Given the medical description, provide appropriate ICD-10, CPT, and HCPCS codes. Include brief explanations for each code and any relevant coding guidelines.'
    ]
];

/**
 * Get selected model, language, and prompt from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['exp-model']) ? $_COOKIE['exp-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['exp-language']) ? $_COOKIE['exp-language'] : 'en'));
$PROMPT_TYPE = isset($_POST['prompt_type']) ? $_POST['prompt_type'] : (isset($_GET['prompt_type']) ? $_GET['prompt_type'] : (isset($_COOKIE['exp-prompt-type']) ? $_COOKIE['exp-prompt-type'] : 'general_medical'));

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
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['prompt'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['prompt']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request

    // Sanitize and validate input
    $prompt = trim(isset($_POST['prompt']) ? $_POST['prompt'] : $_GET['prompt']);

    // Validate prompt length
    if (strlen($prompt) > 10000) {
        $error = 'The prompt is too long. Maximum 10000 characters allowed.';
        $processing = false;
    }
    // Validate prompt is not empty after trimming
    elseif (empty($prompt)) {
        $error = 'The prompt cannot be empty.';
        $processing = false;
    }

    // Only proceed with API call if validation passed
    if ($processing) {
        // Prepare API request
        $api_data = [
            'model' => $MODEL,
            'messages' => [
                ['role' => 'system', 'content' => getLanguageInstruction($LANGUAGE)],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];

        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY);

        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $content = trim($response_data['choices'][0]['message']['content']);
            $result = $content;
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
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </section>
            <?php endif; ?>

            <?php if ($result): ?>
                <article>
                    <header>
                        <h2>AI Response</h2>
                    </header>

                    <section>
                        <pre><?php echo htmlspecialchars($result); ?></pre>
                    </section>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="experimentForm">
                <fieldset>
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

                    <label for="prompt">Prompt Text:</label>
                    <textarea
                        id="prompt"
                        name="prompt"
                        rows="8"
                        required
                        placeholder="Enter your prompt text here..."
                    ><?php
                        // If we have a selected prompt type, use its predefined text
                        if (isset($PREDEFINED_PROMPTS[$PROMPT_TYPE])) {
                            echo htmlspecialchars($PREDEFINED_PROMPTS[$PROMPT_TYPE]['prompt']);
                        } elseif (isset($_POST['prompt'])) {
                            echo htmlspecialchars($_POST['prompt']);
                        } elseif (isset($_GET['prompt'])) {
                            echo htmlspecialchars($_GET['prompt']);
                        }
                    ?></textarea>
                    <small>
                        Edit the prompt text as needed. The selected predefined prompt will be loaded here.
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

                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Experiment
                </button>
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
