
// Global variables to store categories, profiles, and languages
let categoriesData = null;
let profilesData = null;
let languagesData = null;

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
 * @returns {Object} Object with 'type', 'function', and 'text' keys
 */
function extractCodeFenceInfo(text, defaultType = 'text') {
    const result = {
        type: '',
        function: '',
        text: text
    };

    // Regular expression to match markdown code fences
    const fenceRegex = /^```([a-zA-Z0-9_-]*)\s*(.*?)\s*```$/s;
    const matches = text.match(fenceRegex);

    if (matches) {
        result.type = matches[1] ? matches[1].toLowerCase() : '';
        result.text = matches[2];
        result.function = getHighlightFunction(result.type);
    } else {
        // Set default type if no fence found
        result.type = defaultType;
        result.function = getHighlightFunction(defaultType);
    }

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
        document.getElementById('profilesGrid').style.display = 'none';
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

        // Show and populate the profile select dropdown with all profiles
        const profileSelect = document.getElementById('profileSelect');
        profileSelect.style.display = 'block';
        populateProfileSelect(profileSelect);

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
            // If options are empty and field name is 'model', fetch models from API
            if ((!field.options || field.options.length === 0) && field.name === 'model') {
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

        // Parse JSON response
        const result = await response.json();
        // Check for errors
        if (result.error) {
            showError(result.error);
            return;
        }

        // Display results
        displayResults(result);

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

    // Extract the actual response content from the API response
    let responseContent = '';
    if (results.response && results.response.choices && results.response.choices[0] && results.response.choices[0].message && results.response.choices[0].message.content) {
        responseContent = results.response.choices[0].message.content;
    } else if (results.error) {
        responseContent = results.error;
    } else {
        responseContent = 'No response content available';
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
    // If results is an object, stringify it with indentation
    if (typeof results === 'object') {
        return `<pre><code class="json">${JSON.stringify(results, null, 2)}</code></pre>`;
    }
    // Otherwise, escape and return as preformatted text
    return `<pre><code>${escapeHtml(results)}</code></pre>`;
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
