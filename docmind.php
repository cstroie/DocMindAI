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
    // FIXME append the list dynamically from profiles.json
    $valid_actions = [
        'get_models', 'get_form', 'get_prompts', 'get_profiles',
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
        case 'get_profiles':
            handleGetProfiles();
            break;
        default:
            sendJsonResponse(['error' => 'Unhandled action'], true);
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
                        <input type="hidden" name="profile" id="profileInput">
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
                // Load available profiles
                loadProfiles();

                // Set up form submission
                document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
            });

            async function loadProfiles() {
                try {
                    const response = await fetch('docmind.php?action=get_profiles');
                    const data = await response.json();

                    if (data.error) {
                        showError(data.error);
                        return;
                    }

                    displayProfiles(data.profiles);
                } catch (error) {
                    showError('Failed to load profiles: ' + error.message);
                }
            }

            function displayProfiles(profiles) {
                const profilesGrid = document.getElementById('profilesGrid');
                profilesGrid.innerHTML = '';

                // Group profiles by category
                const categories = {};
                profiles.forEach(profile => {
                    if (!categories[profile.category]) {
                        categories[profile.category] = [];
                    }
                    categories[profile.category].push(profile);
                });

                // Display each category
                for (const [category, categoryProfiles] of Object.entries(categories)) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'tool-section';

                    const categoryTitle = document.createElement('h2');
                    categoryTitle.textContent = getCategoryIcon(category) + ' ' + category;
                    categoryDiv.appendChild(categoryTitle);

                    const grid = document.createElement('div');
                    grid.className = 'tools-grid';

                    categoryProfiles.forEach(profile => {
                        const profileCard = document.createElement('a');
                        profileCard.className = 'tool-card';
                        profileCard.href = '#';
                        profileCard.onclick = (e) => {
                            e.preventDefault();
                            loadProfileForm(profile.id);
                        };

                        profileCard.innerHTML = `
                            <div class="tool-icon">📄</div>
                            <h3>${profile.name}</h3>
                            <p>${profile.description}</p>
                        `;

                        grid.appendChild(profileCard);
                    });

                    categoryDiv.appendChild(grid);
                    profilesGrid.appendChild(categoryDiv);
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

            async function loadProfileForm(profileId) {
                try {
                    const response = await fetch(`docmind.php?action=get_form&profile=${profileId}`);
                    const formConfig = await response.json();

                    if (formConfig.error) {
                        showError(formConfig.error);
                        return;
                    }

                    displayForm(formConfig, profileId, profile.name, profile.description);
                } catch (error) {
                    showError('Failed to load form: ' + error.message);
                }
            }

            function displayForm(formConfig, profileId, profileName, profileDescription) {
                const profileForm = document.getElementById('profileForm');
                const formTitle = document.getElementById('formTitle');
                const formFields = document.getElementById('formFields');
                const profileInput = document.getElementById('profileInput');
                formTitle.textContent = profileName;
                profileInput.value = profileId;
                formFields.innerHTML = '';

                // Add description if available
                if (profileDescription) {
                    const descriptionElement = document.createElement('p');
                    descriptionElement.className = 'form-description';
                    descriptionElement.textContent = profileDescription;
                    formFields.insertBefore(descriptionElement, formFields.firstChild);
                }

                formConfig.fields.forEach(field => {
                    const fieldElement = createFormField(field);
                    formFields.appendChild(fieldElement);
                });

                profileForm.style.display = 'block';
                document.getElementById('resultsArea').style.display = 'none';

                // Scroll to form
                profileForm.scrollIntoView({ behavior: 'smooth' });
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
                const profile = formData.get('profile');
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
