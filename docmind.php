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

    // Validate action
    $valid_actions = [
        'get_models', 'get_form', 'get_prompts', 'get_actions',
        'rra', 'sde', 'exp', 'soap', 'rdd', 'dpa', 'pec', 'ocr', 'wpc', 'wps', 'stp', 'sml'
    ];

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
        case 'get_actions':
            handleGetActions();
            break;
        default:
            handleToolAction($action);
    }
}

/**
 * Handle get_models action
 */
function handleGetModels() {
    global $LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER;

    // Use configured values if not provided in request
    $api_endpoint = $_REQUEST['api_endpoint'] ?? $LLM_API_ENDPOINT;
    $api_key = $_REQUEST['api_key'] ?? $LLM_API_KEY;
    $filter = $_REQUEST['filter'] ?? $LLM_API_FILTER;

    // Validate required parameters
    if (empty($api_endpoint)) {
        sendJsonResponse(['error' => 'API endpoint parameter is required'], true);
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
 * Handle get_form action
 */
function handleGetForm() {
    $action = $_REQUEST['for_action'] ?? '';
    $language = $_REQUEST['language'] ?? 'en';

    // Define form configurations for each action
    $form_configs = [
        'rra' => [
            'title' => 'Radiology Report Analyzer',
            'description' => 'Analyze radiology reports and extract key medical information',
            'fields' => [
                ['name' => 'report_text', 'type' => 'textarea', 'label' => 'Radiology Report', 'required' => true],
                ['name' => 'model', 'type' => 'select', 'label' => 'AI Model', 'options' => []],
                ['name' => 'language', 'type' => 'hidden', 'value' => $language]
            ]
        ],
        'sde' => [
            'title' => 'Structured Data Extractor',
            'description' => 'Extract structured data from unstructured text',
            'fields' => [
                ['name' => 'text', 'type' => 'textarea', 'label' => 'Input Text', 'required' => true],
                ['name' => 'schema', 'type' => 'text', 'label' => 'Data Schema (optional)', 'required' => false],
                ['name' => 'model', 'type' => 'select', 'label' => 'AI Model', 'options' => []]
            ]
        ],
        // Add other form configurations here
    ];

    $config = $form_configs[$action] ?? ['error' => 'Unknown action'];
    sendJsonResponse($config, true);
}

/**
 * Handle get_actions action
 */
function handleGetActions() {
    $actions = [
        [
            'id' => 'rra',
            'name' => 'Radiology Report Analyzer',
            'description' => 'Analyze radiology reports and extract key medical information',
            'category' => 'Medical'
        ],
        [
            'id' => 'sde',
            'name' => 'Structured Data Extractor',
            'description' => 'Extract structured data from unstructured text',
            'category' => 'General'
        ],
        [
            'id' => 'exp',
            'name' => 'Experiment Tool',
            'description' => 'Test AI models with predefined prompts',
            'category' => 'Development'
        ],
        // Add other actions here
    ];

    sendJsonResponse(['actions' => $actions], true);
}

/**
 * Handle tool actions
 */
function handleToolAction($action) {
    // Include the specific tool file
    $tool_file = "$action.php";
    if (!file_exists($tool_file)) {
        sendJsonResponse(['error' => 'Tool not found'], true);
    }

    include $tool_file;

    // Call the appropriate function based on the action
    $handler_function = "handle${action}Action";
    if (function_exists($handler_function)) {
        $handler_function();
    } else {
        sendJsonResponse(['error' => 'No handler for this action'], true);
    }
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
                <div class="action-selector">
                    <h2>Available Actions</h2>
                    <div class="tools-grid" id="actionsGrid">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <div class="action-form" id="actionForm" style="display: none;">
                    <h2 id="formTitle">Action Form</h2>
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

        <script src="script.js"></script>
        <script>
            // DocMind-specific JavaScript
            document.addEventListener('DOMContentLoaded', function() {
                // Load available actions
                loadActions();

                // Set up form submission
                document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
            });

            async function loadActions() {
                try {
                    const response = await fetch('docmind.php?action=get_actions');
                    const data = await response.json();

                    if (data.error) {
                        showError(data.error);
                        return;
                    }

                    displayActions(data.actions);
                } catch (error) {
                    showError('Failed to load actions: ' + error.message);
                }
            }

            function displayActions(actions) {
                const actionsGrid = document.getElementById('actionsGrid');
                actionsGrid.innerHTML = '';

                // Group actions by category
                const categories = {};
                actions.forEach(action => {
                    if (!categories[action.category]) {
                        categories[action.category] = [];
                    }
                    categories[action.category].push(action);
                });

                // Display each category
                for (const [category, categoryActions] of Object.entries(categories)) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'tool-section';

                    const categoryTitle = document.createElement('h2');
                    categoryTitle.textContent = getCategoryIcon(category) + ' ' + category;
                    categoryDiv.appendChild(categoryTitle);

                    const grid = document.createElement('div');
                    grid.className = 'tools-grid';

                    categoryActions.forEach(action => {
                        const actionCard = document.createElement('a');
                        actionCard.className = 'tool-card';
                        actionCard.href = '#';
                        actionCard.onclick = (e) => {
                            e.preventDefault();
                            loadActionForm(action.id);
                        };

                        actionCard.innerHTML = `
                            <div class="tool-icon">📄</div>
                            <h3>${action.name}</h3>
                            <p>${action.description}</p>
                        `;

                        grid.appendChild(actionCard);
                    });

                    categoryDiv.appendChild(grid);
                    actionsGrid.appendChild(categoryDiv);
                }
            }

            function getCategoryIcon(category) {
                const icons = {
                    'Medical': '🏥',
                    'General': '📄',
                    'Development': '🧪',
                    'Research': '📚'
                };
                return icons[category] || '📋';
            }

            async function loadActionForm(actionId) {
                try {
                    const response = await fetch(`docmind.php?action=get_form&for_action=${actionId}`);
                    const formConfig = await response.json();

                    if (formConfig.error) {
                        showError(formConfig.error);
                        return;
                    }

                    displayForm(formConfig, actionId);
                } catch (error) {
                    showError('Failed to load form: ' + error.message);
                }
            }

            function displayForm(formConfig, actionId) {
                const actionForm = document.getElementById('actionForm');
                const formTitle = document.getElementById('formTitle');
                const formFields = document.getElementById('formFields');
                const actionInput = document.getElementById('actionInput');

                formTitle.textContent = formConfig.title;
                actionInput.value = actionId;
                formFields.innerHTML = '';

                formConfig.fields.forEach(field => {
                    const fieldElement = createFormField(field);
                    formFields.appendChild(fieldElement);
                });

                actionForm.style.display = 'block';
                document.getElementById('resultsArea').style.display = 'none';

                // Scroll to form
                actionForm.scrollIntoView({ behavior: 'smooth' });
            }

            function createFormField(field) {
                const container = document.createElement('div');
                container.className = 'form-field';

                if (field.type !== 'hidden') {
                    const label = document.createElement('label');
                    label.textContent = field.label || field.name;
                    label.htmlFor = field.name;
                    container.appendChild(label);
                }

                let input;
                switch (field.type) {
                    case 'textarea':
                        input = document.createElement('textarea');
                        input.name = field.name;
                        input.id = field.name;
                        input.required = field.required || false;
                        input.className = 'markdown-result';
                        break;
                    case 'select':
                        input = document.createElement('select');
                        input.name = field.name;
                        input.id = field.name;
                        input.required = field.required || false;
                        input.className = 'form-control';

                        if (field.options && field.options.length) {
                            field.options.forEach(option => {
                                const opt = document.createElement('option');
                                opt.value = option.value;
                                opt.textContent = option.label;
                                input.appendChild(opt);
                            });
                        }
                        break;
                    case 'hidden':
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = field.name;
                        input.value = field.value || '';
                        break;
                    default:
                        input = document.createElement('input');
                        input.type = field.type || 'text';
                        input.name = field.name;
                        input.id = field.name;
                        input.required = field.required || false;
                        input.className = 'form-control';
                }

                container.appendChild(input);
                return container;
            }

            async function handleFormSubmit(event) {
                event.preventDefault();

                const form = event.target;
                const formData = new FormData(form);
                const action = formData.get('action');
                const submitBtn = document.getElementById('submitBtn');

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="loading"></span> Processing...';

                try {
                    const response = await fetch('docmind.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.error) {
                        showError(result.error);
                        return;
                    }

                    displayResults(result);
                } catch (error) {
                    showError('Failed to submit form: ' + error.message);
                } finally {
                    // Restore button state
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit';
                }
            }

            function displayResults(results) {
                const resultsArea = document.getElementById('resultsArea');
                const resultsContent = document.getElementById('resultsContent');

                resultsContent.innerHTML = formatResults(results);
                resultsArea.style.display = 'block';

                // Apply syntax highlighting
                applySyntaxHighlighting();

                // Scroll to results
                resultsArea.scrollIntoView({ behavior: 'smooth' });
            }

            function formatResults(results) {
                if (results.error) {
                    return `<div class="error">${escapeHtml(results.error)}</div>`;
                }

                if (results.html) {
                    return results.html;
                }

                if (typeof results === 'object') {
                    return `<pre><code class="json">${JSON.stringify(results, null, 2)}</code></pre>`;
                }

                return `<pre>${escapeHtml(results)}</pre>`;
            }

            function showError(message) {
                const resultsArea = document.getElementById('resultsArea');
                const resultsContent = document.getElementById('resultsContent');

                resultsContent.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
                resultsArea.style.display = 'block';

                // Scroll to error
                resultsArea.scrollIntoView({ behavior: 'smooth' });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        </script>
    </body>
    </html>
    <?php
}
?>
