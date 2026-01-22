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
    $profiles_data = loadResourceFromJson('profiles.json');
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
        case 'get_prompts':
            handleGetPrompts();
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
 * Handle profile-specific actions
 */
function handleProfileAction($profile_id) {
    global $LLM_API_ENDPOINT_CHAT, $LLM_API_KEY;

    // Validate required parameters
    if (empty($LLM_API_ENDPOINT_CHAT)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    // Load profile configuration
    $profiles_data = loadResourceFromJson('profiles.json');
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
    // TOOD use a default model if not specified in form_data
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
        sendJsonResponse(['error' => $response['error']], true);
    }

    // Process and return the response
    $result = processProfileResponse($profile_id, $response);

    // Add form data and API data to the response
    $result['debug']['form_data'] = $form_data;
    $result['debug']['api_data'] = $api_data;

    // Set CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');

    // Send the final JSON response
    sendJsonResponse($result, true);
}

/**
 * Execute a tool based on profile configuration
 *
 * @param string $tool_name Name of the tool to execute
 * @param array $form_data Form data containing tool parameters
 * @return string|false Tool output or false on error
 */
function executeTool($tool_name, $form_data) {
    switch ($tool_name) {
        case 'web_scraper':
            // Check if URL is provided
            if (empty($form_data['url'])) {
                return false;
            }
            // Validate URL format
            if (!filter_var($form_data['url'], FILTER_VALIDATE_URL)) {
                return false;
            }
            // Scrape the URL content
            $content = scrapeUrl($form_data['url']);
            // Return scraped content or false on failure
            return $content;

        case 'medical_literature_search':
            // Check if query is provided
            if (empty($form_data['query'])) {
                return false;
            }
            // Validate query length
            if (strlen($form_data['query']) > 500) {
                return false;
            }
            // Search PubMed for articles
            $articles = searchPubMed($form_data['query'], 5); // Get top 5 results
            // Return false if no articles found
            if ($articles === false) {
                return false;
            } elseif (empty($articles)) {
                return false;
            }
            // Convert articles to JSON format
            return json_encode($articles, JSON_PRETTY_PRINT);

        default:
            return false;
    }
}

/**
 * Build prompt based on profile configuration
 */
function buildProfilePrompt($profile_id, $form_data) {
    // Load profiles from JSON
    $profiles_data = loadResourceFromJson('profiles.json');

    if (isset($profiles_data['error'])) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Load languages from JSON
    $languages_data = loadResourceFromJson('languages.json');

    // Get the profile configuration
    $profile = $profiles_data['profiles'][$profile_id] ?? null;

    // Check if profile has a prompt field, otherwise look for it in form_data
    if (!$profile) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Check if profile has a tool specification
    if (isset($profile['tool']) && !empty($profile['tool'])) {
        $tool_output = executeTool($profile['tool'], $form_data);
        if ($tool_output === false) {
            return "Failed to execute tool: " . $profile['tool'];
        }
        // Add tool output to form data as 'content' field
        $form_data['content'] = $tool_output;
    }

    // Handle case where profile has 'prompts' key (multiple prompts)
    if (isset($profile['prompts']) && is_array($profile['prompts'])) {
        // Get the selected prompt from form data
        $selected_prompt_key = $form_data['prompt'] ?? '';
        // Check if the selected prompt exists
        if (!empty($selected_prompt_key) && isset($profile['prompts'][$selected_prompt_key])) {
            // Use the selected prompt
            $prompt = $profile['prompts'][$selected_prompt_key];
        } else {
            // If no specific prompt selected or invalid, use the first available prompt
            $first_prompt = reset($profile['prompts']);
            $prompt = $first_prompt;
        }
    }
    // Handle case where profile has a 'prompt' field
    elseif (!empty($profile['prompt'])) {
        $prompt = $profile['prompt'];
    }
    // If no prompt found in profile, check form_data
    elseif (isset($form_data['prompt']) && !empty($form_data['prompt'])) {
        $prompt = $form_data['prompt'];
    }
    // Fallback case - no prompt found
    else {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // If prompt is an array (JSON object), convert to formatted text
    if (is_array($prompt)) {
        $prompt = convertPromptArrayToText($prompt);
    }

    // Ensure prompt is a string
    $prompt = (string)$prompt;
    
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

    // Return the final prompt
    return $prompt;
}

/**
 * Convert prompt array to formatted text recursively
 *
 * @param array $prompt_array The prompt array to convert
 * @param int $indent_level Current indentation level (for nested arrays)
 * @return string Formatted text version of the prompt
 */
function convertPromptArrayToText($prompt_array, $indent_level = 0) {
    // Check if this is an associative array (JSON object)
    if (array_keys($prompt_array) !== range(0, count($prompt_array) - 1)) {
        // Convert JSON object to formatted text
        $prompt_text = '';
        // Process each key-value pair
        foreach ($prompt_array as $key => $value) {
            // Replace underscores with spaces in key names
            $formatted_key = str_replace('_', ' ', $key);

            // Handle array values recursively
            if (is_array($value)) {
                $value = convertPromptArrayToText($value, $indent_level + 1);
            }
            // Append to prompt text with formatting
            $prompt_text .= strtoupper($formatted_key) . "\n" . $value . "\n\n";
        }
        // Trim trailing newlines and return
        return rtrim($prompt_text, "\n");
    } else {
        // If prompt is a sequential array, process each item recursively
        $result = [];
        foreach ($prompt_array as $item) {
            if (is_array($item)) {
                $result[] = convertPromptArrayToText($item, $indent_level);
            } else {
                $result[] = $item;
            }
        }
        // Join items with newlines and return
        return implode("\n", $result);
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

    // Load profiles from JSON
    $profiles_data = loadResourceFromJson('profiles.json');

    if (!isset($profiles_data['error']) && isset($profiles_data['profiles'][$profile_id])) {
        $profile = $profiles_data['profiles'][$profile_id];

        // Check if profile has 'output' key set to 'json'
        if (isset($profile['output']) && $profile['output'] === 'json') {
            // Try to extract JSON if present
            $content = $api_response['choices'][0]['message']['content'] ?? '';
            $json_data = extractJsonFromResponse($content);

            if ($json_data) {
                $result['json'] = $json_data;
            }
        }
    }

    // Return the processed result
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
