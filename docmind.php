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

// Create chat endpoint URL by appending the chat completions path
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Determine if this is an API request
// Check for: 1) action parameter in GET/POST, 2) JSON Accept header
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
 * Handle incoming API requests by validating and routing to appropriate handlers
 *
 * This function acts as the main API router. It:
 * 1. Determines the requested action from GET/POST parameters
 * 2. Validates the action against a whitelist of available actions
 * 3. Dynamically loads valid actions from tools.json
 * 4. Routes to the appropriate handler function based on the action
 *
 * @return void Terminates script execution after sending response
 *
 * @see handleGetModels() - Handles 'get_models' action
 * @see handleGetPrompts() - Handles 'get_prompts' action
 * @see handleToolAction() - Handles tool-specific actions
 * @see sendJsonResponse() - Sends JSON responses
 * @see loadResourceFromJson() - Loads tool configuration
 */
function handleApiRequest() {
    // Extract action from request parameters
    $action = $_REQUEST['action'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'];

    // Validate action - load valid actions dynamically from tools.json
    $valid_actions = ['get_models', 'get_form', 'get_prompts', 'get_tools'];

    // Load tools to get additional valid actions
    // This allows new tools to be added without modifying this code
    $tools_data = loadResourceFromJson('tools.json');
    if (!isset($tools_data['error']) && isset($tools_data['tools'])) {
        foreach ($tools_data['tools'] as $tool_id => $tool_data) {
            $valid_actions[] = $tool_id;
        }
    }

    // Check if action is specified
    if ($action === null) {
        // If no action specified, check for 'tool' parameter
        $tool_id = $_REQUEST['tool'] ?? null;
        if ($tool_id && isset($tools_data['tools'][$tool_id])) {
            $action = $tool_id;
        } else {
            sendJsonResponse(['error' => 'No action or tool specified'], true);
        }
    }

    // Reject invalid actions with error response
    if (!in_array($action, $valid_actions)) {
        sendJsonResponse(['error' => 'Invalid action'], true);
    }

    // Route to appropriate handler based on action
    switch ($action) {
        case 'get_models':
            // Handle request for available AI models
            handleGetModels();
            break;
        case 'get_prompts':
            // Handle request for available prompts
            handleGetPrompts();
            break;
        default:
            // Handle tool-specific actions
            // Check if action corresponds to a valid tool
            if (in_array($action, array_keys($tools_data['tools'] ?? []))) {
                handleToolAction($action);
            } else {
                sendJsonResponse(['error' => 'Unhandled action'], true);
            }
            break;
    }
}

/**
 * Handle the 'get_models' API action - retrieve available AI models
 * 
 * This function fetches the list of available AI models from the configured
 * LLM API endpoint. It uses server-side configuration values and provides
 * fallback defaults if the API call fails.
 * 
 * @global string $LLM_API_ENDPOINT The base API endpoint URL
 * @global string $LLM_API_KEY The API authentication key
 * @global string $LLM_API_FILTER Optional regex filter for model names
 * 
 * @return void Sends JSON response with models list or error
 * 
 * @see getAvailableModels() - Fetches models from API
 * @see sendJsonResponse() - Sends the final response
 * 
 * @note If API call fails, returns default models for fallback functionality
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

    // Fetch available models from the API
    $models = getAvailableModels($api_endpoint, $api_key, $filter);

    // Check if models contain an error
    if (isset($models['error'])) {
        // If API call fails, use default models as fallback
        // This ensures the application remains functional even if API is unavailable
        $models = [
            'gemma3:1b' => 'Gemma 3 (1B)',
            'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
        ];
    }

    // Sort models alphabetically by key (model name) for consistent UI display
    ksort($models);

    // Send the models list as JSON response
    sendJsonResponse(['models' => $models], true);
}


/**
 * Handle tool-specific API actions - main processing pipeline
 * 
 * This function implements the complete processing pipeline for tool-specific actions:
 * 1. Validates API configuration and tool existence
 * 2. Processes form data and file uploads (images and documents)
 * 3. Builds prompts based on tool configuration
 * 4. Prepares and sends API request to LLM
 * 5. Processes and returns the response
 * 
 * @param string $tool_id The identifier of the tool to process
 * @global string $LLM_API_ENDPOINT_CHAT The chat completion API endpoint
 * @global string $LLM_API_KEY The API authentication key
 * 
 * @return void Sends JSON response with processed results or errors
 * 
 * @see loadResourceFromJson() - Loads tool configuration
 * @see processUploadedImage() - Handles image uploads
 * @see extractTextFromDocument() - Extracts text from documents
 * @see buildToolPrompt() - Constructs the prompt
 * @see callLLMApi() - Calls the LLM API
 * @see processToolResponse() - Processes the API response
 * @see sendJsonResponse() - Sends final response
 * 
 * @note Supports both text documents and images
 * @note Includes debug information in response
 * @note Sets CORS headers for cross-origin requests
 */
function handleToolAction($tool_id) {
    global $LLM_API_ENDPOINT_CHAT, $LLM_API_KEY;

    // Validate required parameters
    if (empty($LLM_API_ENDPOINT_CHAT)) {
        sendJsonResponse(['error' => 'API endpoint not configured'], true);
    }

    // Load tool configuration
    $tools_data = loadResourceFromJson('tools.json');
    if (isset($tools_data['error'])) {
        sendJsonResponse(['error' => $tools_data['error']], true);
    }

    if (!isset($tools_data['tools'][$tool_id])) {
        sendJsonResponse(['error' => 'Invalid tool'], true);
    }

    $tool = $tools_data['tools'][$tool_id];

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
    foreach ($tool['form']['fields'] as $field) {
        if (isset($field['required']) && $field['required'] && !isset($form_data[$field['name']])) {
            $required_fields[] = $field['name'];
        }
    }

    if (!empty($required_fields)) {
        sendJsonResponse(['error' => 'Missing required fields: ' . implode(', ', $required_fields)], true);
    }

    // Build the prompt based on tool type
    $prompt = buildToolPrompt($tool_id, $form_data);

    // Prepare API request data
    // TODO: use a default model if not specified in form_data
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
    $result = processToolResponse($tool_id, $response);

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
 * Execute a helper based on tool configuration
 * 
 * This function acts as a helper dispatcher, executing different helpers based on the
 * tool's helper specification. It validates input parameters and returns helper
 * output or false on error.
 * 
 * @param string $helper_name Name of the helper to execute (e.g., 'web_scraper', 'medical_literature_search')
 * @param array $form_data Form data containing helper parameters (URL, query, etc.)
 * @return string|false Helper output as string, or false on error/invalid parameters
 * 
 * @see scrapeUrl() - Web scraping helper
 * @see searchPubMed() - Medical literature search helper
 * 
 * @note Currently supports:
 *       - 'web_scraper': Scrapes content from a URL
 *       - 'medical_literature_search': Searches PubMed for medical articles
 * @note All helpers validate their required parameters before execution
 */
function executeHelper($helper_name, $form_data) {
    switch ($helper_name) {
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

        case 'lynx':
            // Check if URL is provided
            if (empty($form_data['url'])) {
                return false;
            }
            // Validate URL format
            if (!filter_var($form_data['url'], FILTER_VALIDATE_URL)) {
                return false;
            }
            // Run lynx command to extract clean text
            $content = runLynxCommand($form_data['url']);
            // Return content if any, otherwise false
            return empty($content) ? false : $content;
        
        default:
            return false;
    }
}

/**
 * Build prompt based on tool configuration with placeholder replacement
 * 
 * This function constructs the final prompt by:
 * 1. Loading tool and language configurations
 * 2. Executing helpers if specified by the tool
 * 3. Selecting the appropriate prompt template
 * 4. Converting array-based prompts to text format
 * 5. Replacing placeholders with actual form data
 * 6. Handling language-specific instructions
 * 
 * @param string $tool_id The tool identifier
 * @param array $form_data User-submitted form data containing values for placeholders
 * @return string The constructed prompt ready for LLM processing
 * 
 * @see loadResourceFromJson() - Loads configuration files
 * @see executeHelper() - Executes tool helpers
 * @see convertPromptArrayToText() - Converts array prompts to text
 * 
 * @note Supported placeholders: {language_instruction}, {language}, and any form field name
 * @note If tool has 'helper' specification, helper output is added to form_data['content']
 * @note Falls back to generic analysis prompt if no valid prompt found
 */
function buildToolPrompt($tool_id, $form_data) {
    // Load tools from JSON
    $tools_data = loadResourceFromJson('tools.json');

    if (isset($tools_data['error'])) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Load languages from JSON
    $languages_data = loadResourceFromJson('languages.json');

    // Get the tool configuration
    $tool = $tools_data['tools'][$tool_id] ?? null;

    // Check if tool has a prompt field, otherwise look for it in form_data
    if (!$tool) {
        return "Analyze the following input: " . json_encode($form_data);
    }

    // Check if tool has a helper specification
    if (isset($tool['helper']) && !empty($tool['helper'])) {
        $helper_output = executeHelper($tool['helper'], $form_data);
        if ($helper_output !== false) {
            // Add helper output to form data as 'content' field
            $form_data['content'] = $helper_output;
        }
        else {
            // If helper execution failed, set content to empty
            $form_data['content'] = "";
        }
    }

    // Handle case where tool has 'prompts' key (multiple prompts)
    if (isset($tool['prompts']) && is_array($tool['prompts'])) {
        // Get the selected prompt from form data
        $selected_prompt_key = $form_data['prompt'] ?? '';
        // Check if the selected prompt exists
        if (!empty($selected_prompt_key) && isset($tool['prompts'][$selected_prompt_key])) {
            // Use the selected prompt
            $prompt = $tool['prompts'][$selected_prompt_key];
        } else {
            // If no specific prompt selected or invalid, use the first available prompt
            $first_prompt = reset($tool['prompts']);
            $prompt = $first_prompt;
        }
    }
    // Handle case where tool has a 'prompt' field
    elseif (!empty($tool['prompt'])) {
        $prompt = $tool['prompt'];
    }
    // If no prompt found in tool, check form_data
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
 * This function handles two types of array structures:
 * 1. Associative arrays (JSON objects): Converted to uppercase headers with content
 * 2. Sequential arrays: Converted to newline-separated text
 * 
 * @param array $prompt_array The prompt array to convert
 * @param int $indent_level Current indentation level (for nested arrays)
 * @return string Formatted text version of the prompt
 * 
 * @note Example associative array:
 *       ['role' => 'You are a helpful assistant', 'rules' => ['rule1', 'rule2']]
 *       becomes:
 *       ROLE
 *       You are a helpful assistant
 *       
 *       RULES
 *       rule1
 *       rule2
 * @note Example sequential array:
 *       ['line1', 'line2', 'line3'] becomes "line1\nline2\nline3"
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
 * Process tool response and extract JSON if required
 * 
 * This function processes the LLM API response based on tool configuration.
 * It can optionally extract JSON data from the response content if the tool
 * specifies JSON output format.
 * 
 * @param string $tool_id The tool identifier
 * @param array $api_response The raw API response from the LLM
 * @return array Processed result containing tool info, response, and optional JSON data
 * 
 * @see loadResourceFromJson() - Loads tool configuration
 * @see extractJsonFromResponse() - Extracts JSON from response content
 * 
 * @note If tool has 'output' => 'json', attempts to extract JSON from response
 * @note Returns array with keys: 'tool', 'response', and optionally 'json'
 */
function processToolResponse($tool_id, $api_response) {
    $result = [
        'tool' => $tool_id,
        'response' => $api_response
    ];

    // Load tools from JSON
    $tools_data = loadResourceFromJson('tools.json');

    if (!isset($tools_data['error']) && isset($tools_data['tools'][$tool_id])) {
        $tool = $tools_data['tools'][$tool_id];

        // Check if tool has 'output' key set to 'json'
        if (isset($tool['output']) && $tool['output'] === 'json') {
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
 * Handle the 'get_prompts' API action - retrieve available prompt templates
 * 
 * This function scans the 'prompts' directory for prompt template files and
 * returns them as a structured list. It supports multiple file formats and
 * provides clean labels for display.
 * 
 * @return void Sends JSON response with prompts list
 * 
 * @see sendJsonResponse() - Sends the final response
 * 
 * @note Supported file extensions: .txt, .md, .xml
 * @note Prompts are loaded from the 'prompts' subdirectory
 * @note File names are converted to readable labels (e.g., 'medical_report' -> 'Medical Report')
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
 * 
 * This function serves the main web interface by including the index.html file.
 * It acts as a simple wrapper for the web interface presentation layer.
 * 
 * @return void Includes and executes index.html content
 * 
 * @note The index.html file should contain the complete web interface markup
 * @note This function is called when the request is not an API request
 */
function displayWebInterface() {
    include 'index.html';
}
?>
