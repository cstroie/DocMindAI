
// Global variables to store categories, profiles, languages, and prompts
let categoriesData = null;
let profilesData = null;
let languagesData = null;
let promptsData = null;

/**
 * Apply syntax highlighting using highlight.js
 */
function applySyntaxHighlighting() {
    // Initialize highlight.js
    if (typeof hljs !== 'undefined') {
        document.querySelectorAll('pre code').forEach((block) => {
            hljs.highlightElement(block);
        });
    }
}

/**
 * Escape HTML special characters
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Extract code fence information from text
 *
 * @param {string} text - Text to analyze
 * @param {string} [defaultType='text'] - Default type when no fence is found
 * @returns {Object} Object with 'type' and 'text' keys
 */
function extractCodeFenceInfo(text, defaultType = 'text') {
    const result = {
        type: '',
        text: text
    };

    // Regular expression to match markdown code fences
    const fenceRegex = /^```([a-zA-Z0-9_-]*)\s*(.*?)\s*```$/s;
    const matches = text.match(fenceRegex);

    // If a code fence is found, extract type and inner text
    if (matches) {
        result.type = matches[1] ? matches[1].toLowerCase() : '';
        result.text = matches[2];
    } else {
        // Set default type if no fence found
        result.type = defaultType;
    }
    
    // Return the result
    return result;
}

/**
 * Load JSON resource from file
 *
 * @param {string} filename - The JSON file to load
 * @param {string} rootKey - The root key to extract from the JSON data
 * @returns {Promise<Object|null>} Promise resolving to the extracted data or null on error
 */
async function loadJSONResource(filename, rootKey) {
    try {
        // Load data from JSON file
        const response = await fetch(filename);
        const data = await response.json();

        // Check for errors
        if (data.error) {
            console.error(`Failed to load ${filename}:`, data.error);
            return null;
        }

        // Return the specified root key if it exists, otherwise return the entire data
        return data[rootKey] ?? data;
    } catch (error) {
        console.error(`Failed to load ${filename}:`, error.message);
        return null;
    }
}

/**
 * Display profiles grouped by category in the UI
 *
 * @returns {void}
 */
function displayProfiles() {
    const profilesGrid = document.getElementById('profilesGrid');
    profilesGrid.innerHTML = '';

    // Check if profilesData is available
    if (!profilesData) {
        showError('No profiles data available');
        return;
    }

    // Group profiles by category
    const categories = {};
    for (const [profile_id, profile_data] of Object.entries(profilesData)) {
        const category = profile_data.category;
        if (!categories[category]) {
            categories[category] = [];
        }
        categories[category].push({
            'id': profile_id,
            'name': profile_data.name,
            'description': profile_data.description,
            'icon': profile_data.icon
        });
    }

    // Display each category
    for (const [category, categoryProfiles] of Object.entries(categories)) {
        const categoryDiv = document.createElement('section');
        categoryDiv.className = 'category-section category-' + category;

        // Get category info from categories.json
        const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;

        // Create category header
        const categoryHeader = document.createElement('hgroup');

        // Add category title with icon
        const categoryTitle = document.createElement('h2');
        categoryTitle.textContent = (categoryInfo ? categoryInfo.icon + ' ' + categoryInfo.name : category);
        categoryHeader.appendChild(categoryTitle);

        // Add category description if available
        if (categoryInfo && categoryInfo.description) {
            const categoryDescription = document.createElement('p');
            categoryDescription.textContent = categoryInfo.description;
            categoryHeader.appendChild(categoryDescription);
        }

        // Append header to category div
        categoryDiv.appendChild(categoryHeader);

        // Create grid for profiles
        const grid = document.createElement('main');

        // Add profiles to the grid
        categoryProfiles.forEach(profile => {
            const profileCard = document.createElement('a');
            profileCard.className = 'tool-card';
            profileCard.href = '#';
            profileCard.onclick = (e) => {
                e.preventDefault();
                loadProfileForm(profile.id);
            };

            // Add profile icon, name, and description
            profileCard.innerHTML = `
                <div class="tool-icon">${profile.icon || '📄'}</div>
                <h3>${profile.name}</h3>
                <p>${profile.description}</p>
            `;

            // Append profile card to grid
            grid.appendChild(profileCard);
        });

        // Append grid to category div
        categoryDiv.appendChild(grid);
        profilesGrid.appendChild(categoryDiv);
    }
}

/**
 * Populate the profile select dropdown with grouped options
 *
 * @param {HTMLSelectElement} profileSelect - The select element to populate
 * @returns {void}
 */
function populateProfileSelect(profileSelect) {
    // Clear existing options
    profileSelect.innerHTML = '<option value="">-- Select a profile --</option>';

    // Check if profilesData is available
    if (!profilesData) {
        console.error('No profiles data available');
        return;
    }

    // Group profiles by category
    const categories = {};
    for (const [profileId, profileData] of Object.entries(profilesData)) {
        const category = profileData.category;
        if (!categories[category]) {
            categories[category] = [];
        }
        categories[category].push({
            id: profileId,
            name: profileData.name,
            icon: profileData.icon
        });
    }

    // Add optgroups for each category
    for (const [category, categoryProfiles] of Object.entries(categories)) {
        const optgroup = document.createElement('optgroup');

        // Use category name from categories.json if available
        const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;
        optgroup.label = categoryInfo ? categoryInfo.name : category;

        // Add profiles as options
        categoryProfiles.forEach(profile => {
            const option = document.createElement('option');
            option.value = profile.id;
            // Add profile icon before the name
            option.textContent = `${profile.icon || '📄'} ${profile.name}`;
            optgroup.appendChild(option);
        });

        // Append optgroup to select
        profileSelect.appendChild(optgroup);
    }
}


/**
 * Load a profile form based on the selected profile ID
 *
 * @param {string} profileId - The ID of the profile to load
 * @returns {Promise<void>}
 */
async function loadProfileForm(profileId) {
    try {
        // Clear the page
        document.querySelector('.profile-selector').style.display = 'none';
        // Use the global profilesData if available
        if (!profilesData) {
            showError('No profiles data available');
            return;
        }

        // Get the selected profile
        const profile = profilesData[profileId];
        if (!profile) {
            showError('Profile not found');
            return;
        }
        // Set the profile ID in the profile object
        profile.id = profileId;

        // Show the profile select dropdown and enable the profile-select-container nav element
        const profileSelect = document.getElementById('profileSelect');
        profileSelect.style.display = 'block';
        const profileSelectContainer = document.querySelector('.profile-select-container');
        if (profileSelectContainer) {
            profileSelectContainer.style.display = 'block';
        }

        // Populate the profile select dropdown
        populateProfileSelect(profileSelect);

        // Add event listener to handle profile selection
        profileSelect.addEventListener('change', function() {
            const selectedProfileId = this.value;
            if (selectedProfileId) {
                loadProfileForm(selectedProfileId);
            }
        });

        // Update language field if present
        if (profile.form.fields) {
            profile.form.fields.forEach(field => {
                if (field.name === 'language' && field.type === 'hidden') {
                    field.value = 'en';
                }
            });
        }

        // Display the form
        displayProfileForm(profile);
    } catch (error) {
        showError('Failed to load form: ' + error.message);
    }
}

/**
 * Display a profile form with the given configuration
 *
 * @param {Object} formConfig - The form configuration object
 * @param {string} profileId - The ID of the profile
 * @param {string} profileName - The name of the profile
 * @param {string} profileDescription - The description of the profile
 * @returns {void}
 */
function displayProfileForm(profile) {
    // Update top title and description
    const topTitle = document.getElementById('topTitle');
    const topDescription = document.getElementById('topDescription');
    topTitle.textContent = profile.icon + ' ' + profile.name;
    topDescription.textContent = profile.description || '';

    // Populate the form fields
    const profileForm = document.getElementById('profileForm');
    const formFields = document.getElementById('formFields');
    const actionInput = document.getElementById('actionInput');
    actionInput.value = profile.id;
    formFields.innerHTML = '';

    // Get cookies for model and language
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        acc[name] = decodeURIComponent(value);
        return acc;
    }, {});

    // Create form fields based on formConfig
    profile.form.fields.forEach(field => {
        const fieldElement = createFormField(field, cookies);
        formFields.appendChild(fieldElement);
    });

    // Show the form and hide results area
    profileForm.style.display = 'block';
    document.getElementById('resultsArea').style.display = 'none';

    // Scroll to form
    profileForm.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Create a form field element based on field configuration
 *
 * @param {Object} field - The field configuration object
 * @param {string} field.name - The name attribute for the field
 * @param {string} field.type - The type of field (textarea, select, hidden, text, etc.)
 * @param {string} [field.label] - The label text for the field
 * @param {boolean} [field.required] - Whether the field is required
 * @param {Array} [field.options] - Options for select fields
 * @param {string} [field.value] - Default value for the field
 * @param {Object} cookies - Object containing cookie values
 * @returns {HTMLElement} The created form field element
 */
function createFormField(field, cookies = {}) {
    // Create container div
    const container = document.createElement('div');
    container.className = 'form-field';

    // Create label if not hidden field
    if (field.type !== 'hidden') {
        const label = document.createElement('label');
        label.textContent = field.label || field.name;
        label.htmlFor = field.name;
        container.appendChild(label);
    }

    let input;
    // Create input element based on field type
    switch (field.type) {
        case 'textarea':
            input = document.createElement('textarea');
            input.name = field.name;
            input.id = field.name;
            input.rows = field.rows || 10;
            input.required = field.required || false;
            if (field.placeholder) {
                input.placeholder = field.placeholder;
            }
            break;
        case 'select':
            input = document.createElement('select');
            input.name = field.name;
            input.id = field.name;
            input.required = field.required || false;
            // If options are empty and field name is 'prompts', fetch prompts from API
            if ((!field.options || field.options.length === 0) && field.name === 'prompts') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading prompts...';
                input.appendChild(loadingOption);
                // Fetch prompts from API
                fetchPromptsForSelect(input);
            }
            // If options are empty and field name is 'model', fetch models from API
            else if ((!field.options || field.options.length === 0) && field.name === 'model') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading models...';
                input.appendChild(loadingOption);
                // Fetch models from API
                fetchModelsForSelect(input, cookies);
            }
            // If options are empty and field name is 'language', use the languages object
            else if ((!field.options || field.options.length === 0) && field.name === 'language') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading languages...';
                input.appendChild(loadingOption);
                // Use global languagesData
                if (languagesData) {
                    input.innerHTML = '';
                    // Add default option
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select a language';
                    input.appendChild(defaultOption);
                    // Add languages from global data
                    for (const [langCode, langData] of Object.entries(languagesData)) {
                        const option = document.createElement('option');
                        option.value = langCode;
                        option.textContent = (langData.flag ? langData.flag + ' ' : '') + langData.name;
                        // Set selected if this language matches the cookie
                        if (cookies['docmind-language'] === langCode) {
                            option.selected = true;
                        }
                        input.appendChild(option);
                    }
                } else {
                    console.error('No languages data available');
                    input.innerHTML = '';
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.textContent = 'Failed to load languages';
                    input.appendChild(errorOption);
                }
            }
            else if (field.options && field.options.length) {
                field.options.forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.label;
                    // Set selected if this option matches the cookie
                    if (field.name === 'model' && cookies['docmind-model'] === option.value) {
                        opt.selected = true;
                    }
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
            if (field.placeholder) {
                input.placeholder = field.placeholder;
            }
    }
    // Append input to container
    container.appendChild(input);

    // Add help text if available
    if (field.help) {
        const helpElement = document.createElement('small');
        helpElement.textContent = field.help;
        container.appendChild(helpElement);
    }

    // Return the container
    return container;
}

/**
 * Fetch models from API and populate select element
 *
 * @param {HTMLSelectElement} selectElement - The select element to populate
 * @param {Object} cookies - Object containing cookie values
 * @returns {Promise<void>}
 */
async function fetchModelsForSelect(selectElement, cookies = {}) {
    try {
        const response = await fetch('docmind.php?action=get_models');
        const data = await response.json();

        if (data.error) {
            console.error('Failed to load models:', data.error);
            // Clear loading option and add error option
            selectElement.innerHTML = '';
            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Failed to load models';
            selectElement.appendChild(errorOption);
            return;
        }

        // Clear loading option
        selectElement.innerHTML = '';

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select a model';
        selectElement.appendChild(defaultOption);

        // Add models from API
        if (data.models) {
            for (const [modelId, modelName] of Object.entries(data.models)) {
                const option = document.createElement('option');
                option.value = modelId;
                option.textContent = modelName;
                // Set selected if this model matches the cookie
                if (cookies['docmind-model'] === modelId) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            }
        }
    } catch (error) {
        console.error('Failed to load models:', error.message);
        // Clear loading option and add error option
        selectElement.innerHTML = '';
        const errorOption = document.createElement('option');
        errorOption.value = '';
        errorOption.textContent = 'Failed to load models';
        selectElement.appendChild(errorOption);
    }
}

/**
 * Fetch languages from JSON and populate select element
 * TODO: This function is currently not used since languagesData is loaded globally
 */
async function fetchLanguagesForSelect(selectElement) {
    try {
        // Use the global languagesData if available
        if (!languagesData) {
            console.error('No languages data available');
            // Clear loading option and add error option
            selectElement.innerHTML = '';
            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Failed to load languages';
            selectElement.appendChild(errorOption);
            return;
        }

        // Clear loading option
        selectElement.innerHTML = '';

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select a language';
        selectElement.appendChild(defaultOption);

        // Add languages from global data
        for (const [langCode, langData] of Object.entries(languagesData)) {
            const option = document.createElement('option');
            option.value = langCode;
            option.textContent = (langData.flag ? ' ' + langData.flag : '') + langData.name;
            selectElement.appendChild(option);
        }
    } catch (error) {
        console.error('Failed to load languages:', error.message);
        // Clear loading option and add error option
        selectElement.innerHTML = '';
        const errorOption = document.createElement('option');
        errorOption.value = '';
        errorOption.textContent = 'Failed to load languages';
        selectElement.appendChild(errorOption);
    }
}

/**
 * Fetch prompts from API and populate select element
 *
 * @param {HTMLSelectElement} selectElement - The select element to populate
 * @returns {Promise<void>}
 */
async function fetchPromptsForSelect(selectElement) {
    try {
        // If promptsData is not loaded, fetch it from API
        if (!promptsData) {
            const response = await fetch('docmind.php?action=get_prompts');
            const data = await response.json();

            if (data.error) {
                console.error('Failed to load prompts:', data.error);
                // Clear loading option and add error option
                selectElement.innerHTML = '';
                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Failed to load prompts';
                selectElement.appendChild(errorOption);
                return;
            }

            // Store prompts data globally
            promptsData = data.prompts || {};
        }

        // Clear loading option
        selectElement.innerHTML = '';

        // Add default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'Select a prompt';
        selectElement.appendChild(defaultOption);

        // Add prompts from API
        for (const [promptId, promptData] of Object.entries(promptsData)) {
            const option = document.createElement('option');
            option.value = promptId;
            option.textContent = promptData.label || promptId;
            selectElement.appendChild(option);
        }

        // Add event listener to populate prompt textarea when a prompt is selected
        selectElement.addEventListener('change', function() {
            const selectedPromptId = this.value;
            if (selectedPromptId && promptsData[selectedPromptId]) {
                const promptTextarea = document.getElementById('prompt');
                if (promptTextarea) {
                    promptTextarea.value = promptsData[selectedPromptId].prompt || '';
                }
            }
        });
    } catch (error) {
        console.error('Failed to load prompts:', error.message);
        // Clear loading option and add error option
        selectElement.innerHTML = '';
        const errorOption = document.createElement('option');
        errorOption.value = '';
        errorOption.textContent = 'Failed to load prompts';
        selectElement.appendChild(errorOption);
    }
}

/**
 * Handle form submission
 *
 * @param {Event} event - The form submission event
 * @returns {Promise<void>}
 */
async function handleFormSubmit(event) {
    event.preventDefault();
    // Get form data
    const form = event.target;
    const formData = new FormData(form);

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading"></span> Processing...';

    try {
        const response = await fetch('docmind.php', {
            method: 'POST',
            body: formData
        });

        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Check if response has JSON content type
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            // Parse JSON response
            const result = await response.json();
            // Check for errors
            if (result.error) {
                showError(result.error);
                return;
            }

            // Display results
            displayResults(result);
        } else {
            // Handle non-JSON response
            const text = await response.text();
            showError(`Unexpected response format: ${text}`);
            return;
        }

        // Set cookies for selected model and language (30 days)
        const model = formData.get('model');
        const language = formData.get('language');

        if (model) {
            const expirationDate = new Date();
            expirationDate.setDate(expirationDate.getDate() + 30);
            document.cookie = `docmind-model=${encodeURIComponent(model)}; expires=${expirationDate.toUTCString()}; path=/`;
        }

        if (language) {
            const expirationDate = new Date();
            expirationDate.setDate(expirationDate.getDate() + 30);
            document.cookie = `docmind-language=${encodeURIComponent(language)}; expires=${expirationDate.toUTCString()}; path=/`;
        }

    } catch (error) {
        showError('Failed to submit form: ' + error.message);
    } finally {
        // Restore button state
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
    }
}

/**
 * Display results in the results area
 *
 * @param {Object} results - The results object to display
 * @returns {void}
 */
function displayResults(results) {
    // Get results area elements
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsDescription = document.getElementById('resultsDescription');

    // Extract the actual response content from the API response
    let responseContent = '';
    if (results.response && results.response.choices && results.response.choices[0] && results.response.choices[0].message && results.response.choices[0].message.content) {
        responseContent = results.response.choices[0].message.content;
    } else if (results.error) {
        responseContent = results.error;
    } else {
        responseContent = 'No response content available';
    }

    // Get the current profile from the action input
    const actionInput = document.getElementById('actionInput');
    const profileId = actionInput.value;
    const profile = profilesData[profileId];

    // Update results title and description if profile has form.title and form.description
    if (profile && profile.form && profile.form.title) {
        resultsTitle.textContent = profile.form.title;
    } else {
        resultsTitle.textContent = '📝 Results';
    }

    if (profile && profile.form && profile.form.description) {
        resultsDescription.textContent = profile.form.description;
    } else {
        resultsDescription.textContent = 'Review the AI-generated results below. You can copy the content or download it as a file.';
    }

    // Format and display results
    resultsContent.innerHTML = formatResults(responseContent);
    resultsArea.style.display = 'block';
    // Apply syntax highlighting
    applySyntaxHighlighting();
    // Scroll to results
    resultsArea.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Format results for display
 *
 * @param {Object|string} results - The results to format
 * @returns {string} HTML string for displaying the results
 */
function formatResults(results) {
    // Check for error in results
    if (results.error) {
        return `<div class="error">${escapeHtml(results.error)}</div>`;
    }
    // If results contain HTML, return it directly
    if (results.html) {
        return results.html;
    }

    // Check if the result contains markdown code fences
    const fenceInfo = extractCodeFenceInfo(results, 'text');
    if (fenceInfo.type) {
        // If the fence type is 'html', return the text directly without escaping
        if (fenceInfo.type === 'html') {
            return fenceInfo.text;
        }
        // Use marked.js to convert markdown to HTML
        try {
            // Convert markdown to HTML using marked.js
            const htmlContent = marked.parse(results);
            // Apply syntax highlighting to any code blocks in the HTML
            setTimeout(() => {
                applySyntaxHighlighting();
            }, 0);
            return htmlContent;
        } catch (error) {
            console.error('Error parsing markdown:', error);
            // Fallback to syntax highlighting if markdown parsing fails
            return `<pre><code class="${fenceInfo.type}">${escapeHtml(fenceInfo.text)}</code></pre>`;
        }
    }

    // If results is an object, convert to markdown for better readability
    if (typeof results === 'object') {
        try {
            const markdown = jsonToMarkdown(results);
            const htmlContent = marked.parse(markdown);
            // Apply syntax highlighting to any code blocks in the HTML
            setTimeout(() => {
                applySyntaxHighlighting();
            }, 0);
            return htmlContent;
        } catch (error) {
            console.error('Error converting JSON to markdown:', error);
            // Fallback to JSON stringify if conversion fails
            return `<pre><code class="json">${JSON.stringify(results, null, 2)}</code></pre>`;
        }
    }

    // For simple strings, just return them
    return escapeHtml(results);
}

/**
 * Display an error message in the results area
 *
 * @param {string} message - The error message to display
 * @returns {void}
 */
function showError(message) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');

    // Display error message
    resultsContent.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
    resultsArea.style.display = 'block';

    // Scroll to error
    resultsArea.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Convert structured JSON objects to markdown for better human readability
 *
 * @param {Object|Array|string} data - The JSON data to convert
 * @returns {string} Markdown representation of the data
 */
function jsonToMarkdown(data) {
    // If data is already a string, return it as-is (could be markdown or HTML)
    if (typeof data === 'string') {
        return data;
    }

    // If data is an array, process it as a list or table
    if (Array.isArray(data)) {
        // Check if array contains objects (convert to table)
        if (data.length > 0 && typeof data[0] === 'object' && !Array.isArray(data[0])) {
            return arrayOfObjectsToTable(data);
        }
        // Otherwise convert to list
        return arrayToList(data);
    }

    // If data is an object, process it as headings and paragraphs
    if (typeof data === 'object') {
        return objectToMarkdown(data);
    }

    // Fallback for other types
    return String(data);
}

/**
 * Convert an object to markdown headings and paragraphs
 *
 * @param {Object} obj - The object to convert
 * @param {number} [level=1] - The heading level to start with
 * @returns {string} Markdown representation
 */
function objectToMarkdown(obj, level = 1) {
    let markdown = '';

    for (const [key, value] of Object.entries(obj)) {
        // Skip null/undefined values
        if (value === null || value === undefined) continue;

        // Create heading based on level
        const heading = '#'.repeat(Math.min(level, 6)) + ' ' + key + '\n\n';

        // Process value based on its type
        if (typeof value === 'string') {
            // String values become paragraphs
            markdown += heading + value + '\n\n';
        } else if (typeof value === 'object') {
            // Nested objects get deeper headings
            if (Array.isArray(value)) {
                markdown += heading + jsonToMarkdown(value) + '\n\n';
            } else {
                markdown += heading + objectToMarkdown(value, level + 1);
            }
        } else {
            // Other types (numbers, booleans) become paragraphs
            markdown += heading + String(value) + '\n\n';
        }
    }

    return markdown.trim();
}

/**
 * Convert an array to a markdown list
 *
 * @param {Array} arr - The array to convert
 * @returns {string} Markdown list representation
 */
function arrayToList(arr) {
    let markdown = '';

    arr.forEach(item => {
        if (item === null || item === undefined) return;

        // Handle nested arrays
        if (Array.isArray(item)) {
            markdown += '- ' + jsonToMarkdown(item) + '\n';
        }
        // Handle objects (convert to nested list)
        else if (typeof item === 'object') {
            markdown += '- ' + objectToMarkdown(item, 1) + '\n';
        }
        // Handle simple values
        else {
            markdown += '- ' + String(item) + '\n';
        }
    });

    return markdown.trim();
}

/**
 * Convert an array of objects to a markdown table
 *
 * @param {Array<Object>} arr - The array of objects to convert
 * @returns {string} Markdown table representation
 */
function arrayOfObjectsToTable(arr) {
    if (arr.length === 0) return '';

    // Get all unique keys from all objects
    const keys = [...new Set(arr.flatMap(obj => Object.keys(obj)))];

    // Create header row
    let markdown = '| ' + keys.join(' | ') + ' |\n';

    // Create separator row
    markdown += '| ' + keys.map(() => '---').join(' | ') + ' |\n';

    // Create data rows
    arr.forEach(obj => {
        const row = keys.map(key => {
            const value = obj[key];
            if (value === null || value === undefined) return '';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
        });
        markdown += '| ' + row.join(' | ') + ' |\n';
    });

    return markdown.trim();
}

// DocMind-specific JavaScript
document.addEventListener('DOMContentLoaded', async function() {
    // Load categories data
    categoriesData = await loadJSONResource('categories.json', 'categories');
    // Load profiles data
    profilesData = await loadJSONResource('profiles.json', 'profiles');
    // Load languages data
    languagesData = await loadJSONResource('languages.json', 'languages');
    // Display profiles in the UI
    displayProfiles();
    // Set up form submission
    document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
});
