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
 * Load resource from JSON file
 */
function loadResourceFromJson($filename) {
    // Check if resource file exists
    if (!file_exists($filename)) {
        $resource_name = ucfirst(str_replace('.json', '', $filename));
        return ['error' => $resource_name . ' configuration file not found'];
    }

    // Read and decode JSON file
    $json_content = file_get_contents($filename);
    if ($json_content === false) {
        return ['error' => 'Failed to read ' . $filename . ' configuration file'];
    }

    $resource_data = json_decode($json_content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON format in ' . $filename . ' configuration: ' . json_last_error_msg()];
    }

    return $resource_data;
}

/**
 * Load languages from JSON file
 */
function loadLanguagesFromJson() {
    return loadResourceFromJson('languages.json');
}

/**
 * Load profiles from JSON file
 */
function loadProfilesFromJson() {
    return loadResourceFromJson('profiles.json');
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

    // Handle file upload if present
    $file_content = '';
    $is_image = false;
    $image_data = null;
    $mime_type = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];

        // Check if it's an image
        $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $image_types)) {
            $is_image = true;
            // Get max image size from form or use default
            $max_size = isset($_POST['max_image_size']) ? $_POST['max_image_size'] : '500';

            // Process uploaded image
            $image_processing_result = processUploadedImage($file, $max_size);

            if (isset($image_processing_result['error'])) {
                sendJsonResponse(['error' => $image_processing_result['error']], true);
            } else {
                $image_data = $image_processing_result['image_data'];
                $mime_type = $image_processing_result['mime_type'];
            }
        } else {
            // Text document - try to extract text
            $file_content = extractTextFromDocument($file['tmp_name'], $file['type']);
            if ($file_content === false) {
                sendJsonResponse(['error' => 'Failed to extract text from the uploaded document. Please ensure you have the required tools installed (antiword, catdoc, pdftotext, odt2txt, or pandoc).'], true);
            } else {
                // Clean up the text content
                $file_content = trim($file_content);
                // Remove BOM if present
                $file_content = preg_replace('/^\xEF\xBB\xBF/', '', $file_content);
                // Normalize line endings
                $file_content = str_replace(["\r\n", "\r"], "\n", $file_content);
            }
        }
    }

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
        'messages' => [],
        'stream' => false
    ];

    // Add user message with prompt and file content
    $user_content = $prompt;
    if (!empty($file_content)) {
        $user_content .= $file_content;
    }

    $api_data['messages'][] = ['role' => 'user', 'content' => $user_content];

    // If it's an image, add it as a separate message with image data
    if ($is_image && $image_data !== null) {
        // Convert image to base64
        $base64_image = base64_encode($image_data);

        $api_data['messages'][] = [
            'role' => 'user',
            'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => "data:$mime_type;base64,$base64_image"]]
            ]
        ];
    }

    // Call LLM API
    $response = callLLMApi($LLM_API_ENDPOINT_CHAT, $api_data, $LLM_API_KEY);

    if (isset($response['error'])) {
        sendJsonResponse(['error' => 'API error: ' . $response['error']], true);
    }

    // Process and return the response
    $result = processProfileResponse($profile_id, $response);

    // Add form data and API data to the response
    $result['form_data'] = $form_data;
    $result['api_data'] = $api_data;
    $result['prompt'] = $prompt;

    // Set CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    sendJsonResponse($result, true);
}

/**
 * Build prompt based on profile configuration
 */
function buildProfilePrompt($profile_id, $form_data) {
    // Load profiles from JSON
    $profiles_data = loadProfilesFromJson();

    if (isset($profiles_data['error'])) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Load languages from JSON
    $languages_data = loadLanguagesFromJson();

    // Get the profile configuration
    $profile = $profiles_data['profiles'][$profile_id] ?? null;

    if (!$profile || empty($profile['prompt'])) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Replace placeholders in the prompt with actual form data
    $prompt = $profile['prompt'];

    // Replace {language_instruction} placeholder if present
    $language = $form_data['language'] ?? 'en';
    $language_instruction = $languages_data['languages'][$language]['instruction'] ?? 'Respond in English.';
    $prompt = str_replace('{language_instruction}', $language_instruction, $prompt);

    // Replace other placeholders with form data
    foreach ($form_data as $key => $value) {
        $placeholder = '{' . $key . '}';
        if (strpos($prompt, $placeholder) !== false) {
            $prompt = str_replace($placeholder, $value, $prompt);
        }
    }

    return $prompt;
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
    include 'index.html';
}
?>
