<?php
/**
 * DocMind AI - Unified Gateway
 *
 * Central endpoint for all AI tool operations, serving both as API and web interface
 *
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */

include 'common.php';

// Load configuration if available
if (file_exists('config.php')) {
    include 'config.php';
}

// Create chat endpoint URL
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Determine if this is an API request
$is_api_request = isset($_GET['action']) || isset($_POST['action']) ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle API actions
if ($is_api_request) {
    handleApiRequest();
    exit;
}

// Default web interface
displayWebInterface();

/**
 * Handle API requests
 */
function handleApiRequest() {
    $action = $_REQUEST['action'] ?? 'list_actions';
    $method = $_SERVER['REQUEST_METHOD'];

    // Validate action - load valid actions dynamically from profiles.json
    $valid_actions = ['get_models', 'get_form', 'get_prompts', 'get_profiles'];

    // Load profiles to get additional valid actions
    $profiles_data = loadProfilesFromJson();
    if (!isset($profiles_data['error']) && isset($profiles_data['profiles'])) {
        foreach ($profiles_data['profiles'] as $profile_id => $profile_data) {
            $valid_actions[] = $profile_id;
        }
    }

    if (!in_array($action, $valid_actions)) {
        sendJsonResponse(['error' => 'Invalid action'], true);
    }

    // Route to appropriate handler
    switch ($action) {
        case 'get_models':
            handleGetModels();
            break;
        case 'get_form':
            handleGetForm();
            break;
        case 'get_prompts':
            handleGetPrompts();
            break;
        case 'get_profiles':
            handleGetProfiles();
            break;
        default:
            // Handle profile-specific actions
            if (in_array($action, array_keys($profiles_data['profiles'] ?? []))) {
                handleProfileAction($action);
            } else {
                sendJsonResponse(['error' => 'Unhandled action'], true);
            }
            break;
    }
}

/**
 * Handle get_models action
 */
function handleGetModels() {
    global $LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER;

    // Use server-side configured values
    $api_endpoint = $LLM_API_ENDPOINT;
    $api_key = $LLM_API_KEY;
    $filter = $LLM_API_FILTER;

    // Validate required parameters
    if (empty($api_endpoint)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    $models = getAvailableModels($api_endpoint, $api_key, $filter);

    // Check if models contain an error
    if (isset($models['error'])) {
        // If API call fails, use default models
        $models = [
            'gemma3:1b' => 'Gemma 3 (1B)',
            'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
        ];
    }

    // Sort models alphabetically by key (model name)
    ksort($models);

    sendJsonResponse(['models' => $models], true);
}

/**
 * Load profiles from JSON file
 */
function loadProfilesFromJson() {
    $profiles_file = 'profiles.json';

    // Check if profiles file exists
    if (!file_exists($profiles_file)) {
        return ['error' => 'Profiles configuration file not found'];
    }

    // Read and decode JSON file
    $json_content = file_get_contents($profiles_file);
    if ($json_content === false) {
        return ['error' => 'Failed to read profiles configuration file'];
    }

    $profiles_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON format in profiles configuration: ' . json_last_error_msg()];
    }

    return $profiles_data;
}

/**
 * Handle get_form action
 */
function handleGetForm() {
    $profile = $_REQUEST['profile'] ?? '';
    $language = $_REQUEST['language'] ?? 'en';

    // Load profiles from JSON
    $profiles_data = loadProfilesFromJson();

    if (isset($profiles_data['error'])) {
        sendJsonResponse(['error' => $profiles_data['error']], true);
    }

    // Get form configuration for the requested profile
    if (isset($profiles_data['profiles'][$profile]['form'])) {
        $form_config = $profiles_data['profiles'][$profile]['form'];

        // Update language field if present
        if (isset($form_config['fields'])) {
            foreach ($form_config['fields'] as &$field) {
                if ($field['name'] === 'language' && $field['type'] === 'hidden') {
                    $field['value'] = $language;
                }
            }
        }

        sendJsonResponse($form_config, true);
    } else {
        sendJsonResponse(['error' => 'Unknown profile or no form configuration available'], true);
    }
}

/**
 * Handle get_profiles action
 */
function handleGetProfiles() {
    // Load profiles from JSON
    $profiles_data = loadProfilesFromJson();

    if (isset($profiles_data['error'])) {
        sendJsonResponse(['error' => $profiles_data['error']], true);
    }

    // Extract just the profile metadata (id, name, description, category)
    $profiles = [];
    foreach ($profiles_data['profiles'] as $profile_id => $profile_data) {
        $profiles[] = [
            'id' => $profile_id,
            'name' => $profile_data['name'],
            'description' => $profile_data['description'],
            'category' => $profile_data['category']
        ];
    }

    sendJsonResponse(['profiles' => $profiles], true);
}

/**
 * Handle profile-specific actions
 */
function handleProfileAction($profile_id) {
    global $LLM_API_ENDPOINT_CHAT, $LLM_API_KEY;

    // Validate required parameters
    if (empty($LLM_API_ENDPOINT_CHAT)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    // Load profile configuration
    $profiles_data = loadProfilesFromJson();
    if (isset($profiles_data['error'])) {
        sendJsonResponse(['error' => $profiles_data['error']], true);
    }

    if (!isset($profiles_data['profiles'][$profile_id])) {
        sendJsonResponse(['error' => 'Invalid profile'], true);
    }

    $profile = $profiles_data['profiles'][$profile_id];

    // Get form data
    $form_data = $_POST;

    // Validate required fields
    $required_fields = [];
    foreach ($profile['form']['fields'] as $field) {
        if (isset($field['required']) && $field['required'] && !isset($form_data[$field['name']])) {
            $required_fields[] = $field['name'];
        }
    }

    if (!empty($required_fields)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $required_fields)], true);
    }

    // Build the prompt based on profile type
    $prompt = buildProfilePrompt($profile_id, $form_data);

    // Prepare API request data
    $api_data = [
        'model' => $form_data['model'] ?? '',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'stream' => false
    ];

    // Add language instruction if available
    $language = $form_data['language'] ?? 'en';
    $language_instruction = getLanguageInstruction($language);
    if (!empty($language_instruction)) {
        $api_data['messages'][0]['content'] = $language_instruction . "\n\n" . $api_data['messages'][0]['content'];
    }

    // Call LLM API
    $response = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY);

    if (isset($response['error'])) {
        sendJsonResponse(['error' => 'API error: ' . $response['error']], true);
    }

    // Process and return the response
    $result = processProfileResponse($profile_id, $response);

    sendJsonResponse($result, true);
}

/**
 * Build prompt based on profile type
 */
function buildProfilePrompt($profile_id, $form_data) {
    switch ($profile_id) {
        case 'rra':
            // Radiology Report Analyzer
            $report_text = $form_data['report_text'] ?? '';
            return "Analyze the following radiology report and extract key medical information:\n\n" .
                   "Report: " . $report_text . "\n\n" .
                   "Provide a structured analysis with findings, impressions, and recommendations.";

        case 'sde':
            // Structured Data Extractor
            $text = $form_data['text'] ?? '';
            $schema = $form_data['schema'] ?? '';
            $prompt = "Extract structured data from the following text:\n\n" .
                      "Text: " . $text . "\n\n";

            if (!empty($schema)) {
                $prompt .= "Use this schema: " . $schema . "\n\n";
            }

            $prompt .= "Return the extracted data in JSON format.";
            return $prompt;

        case 'exp':
            // Experiment Tool
            $prompt_text = $form_data['prompt'] ?? '';
            return $prompt_text;

        default:
            return "Analyze the following input: " . json_encode($form_data);
    }
}

/**
 * Process profile response
 */
function processProfileResponse($profile_id, $api_response) {
    $result = [
        'profile' => $profile_id,
        'response' => $api_response
    ];

    // Add profile-specific processing if needed
    switch ($profile_id) {
        case 'rra':
            // For radiology reports, try to extract JSON if present
            $content = $api_response['choices'][0]['message']['content'] ?? '';
            $json_data = extractJsonFromResponse($content);

            if ($json_data) {
                $result['structured_data'] = $json_data;
            }
            break;

        case 'sde':
            // For structured data extraction, try to extract JSON
            $content = $api_response['choices'][0]['message']['content'] ?? '';
            $json_data = extractJsonFromResponse($content);

            if ($json_data) {
                $result['extracted_data'] = $json_data;
            }
            break;
    }

    return $result;
}

/**
 * Handle get_prompts action
 */
function handleGetPrompts() {
    // Load prompts from files in the prompts directory
    $prompts = [];

    // Check if prompts directory exists
    if (is_dir('prompts')) {
        // Get all files in the prompts directory
        $files = scandir('prompts');

        foreach ($files as $file) {
            // Check for .txt, .md, or .xml extensions
            if (preg_match('/\.(txt|md|xml)$/i', $file)) {
                $file_path = 'prompts/' . $file;

                // Use filename (without extension) as the key
                $key = pathinfo($file, PATHINFO_FILENAME);

                // Use filename as label (clean up for display)
                $label = ucwords(str_replace(['_', '-'], ' ', $key));

                // Read file content as prompt
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    $prompts[$key] = [
                        'label' => $label,
                        'prompt' => $content
                    ];
                }
            }
        }
    }

    sendJsonResponse(['prompts' => $prompts], true);
}

/**
 * Display web interface
 */
function displayWebInterface() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>DocMind AI - Unified Gateway</title>
        <script src="script.js"></script>
        <script src="highlight.min.js"></script>
        <link rel="stylesheet" href="style.css">
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8F%A5%3C/text%3E%3C/svg%3E">
    </head>
    <body>
        <div class="container">
            <hgroup>
                <h1>🏥 DocMind AI - Unified Gateway</h1>
                <p>A central hub for all AI document processing tools</p>
            </hgroup>

            <div class="config-notice">
                <?php echo checkConfigStatus(); ?>
            </div>

            <main>
                <div class="profile-selector">
                    <h2>Available Profiles</h2>
                    <div class="tools-grid" id="profilesGrid">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="profile-form" id="profileForm" style="display: none;">
                    <h2 id="formTitle">Profile Form</h2>
                    <form id="apiForm">
                        <input type="hidden" name="action" id="actionInput">
                        <div id="formFields">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Submit</button>
                    </form>
                </div>

                <div class="results-area" id="resultsArea" style="display: none;">
                    <h2>Results</h2>
                    <div id="resultsContent"></div>
                </div>
            </main>
        </div>


    </body>
    </html>
    <?php
}
?>
