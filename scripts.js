
// Global variable to store categories
let categoriesData = null;

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
 * Load categories from JSON file
 *
 * @returns {Promise<Object|null>} Promise resolving to categories object or null on error
 */
async function loadCategories() {
    try {
        // Load categories data directly from JSON file
        const response = await fetch('categories.json');
        const data = await response.json();
        // Check for errors
        if (data.error) {
            console.error('Failed to load categories:', data.error);
            return null;
        }
        // Return categories object
        return data.categories;
    } catch (error) {
        console.error('Failed to load categories:', error.message);
        return null;
    }
}

/**
 * Load profiles from JSON file and display them in the UI
 *
 * @returns {Promise<void>}
 */
async function loadProfiles() {
    try {
        // Load profiles data directly from JSON file
        const response = await fetch('profiles.json');
        const data = await response.json();
        // Check for errors
        if (data.error) {
            showError(data.error);
            return;
        }
        // Extract just the profile metadata (id, name, description, category, icon)
        const profiles = [];
        for (const [profile_id, profile_data] of Object.entries(data.profiles)) {
            profiles.push({
                'id': profile_id,
                'name': profile_data.name,
                'description': profile_data.description,
                'category': profile_data.category,
                'icon': profile_data.icon
            });
        }
        // Display profiles in the UI
        displayProfiles(profiles);
    } catch (error) {
        showError('Failed to load profiles: ' + error.message);
    }
}

/**
 * Display profiles grouped by category in the UI
 *
 * @param {Array} profiles - Array of profile objects containing id, name, description, category, and icon
 * @returns {void}
 */
function displayProfiles(profiles) {
    const profilesGrid = document.getElementById('profilesGrid');
    const profileSelect = document.getElementById('profileSelect');
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
            console.log('Adding profile:', profile);
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
 * @param {Array} profiles - Array of profile objects containing id, name, category, and icon
 * @param {HTMLSelectElement} profileSelect - The select element to populate
 * @returns {void}
 */
function populateProfileSelect(profiles, profileSelect) {
    // Populate the select dropdown
    profileSelect.innerHTML = '<option value="">-- Select a profile --</option>';
    // Group profiles by category
    const categories = {};
    profiles.forEach(profile => {
        if (!categories[profile.category]) {
            categories[profile.category] = [];
        }
        categories[profile.category].push(profile);
    });
    // Add optgroups for each category
    for (const [category, categoryProfiles] of Object.entries(categories)) {
        const optgroup = document.createElement('optgroup');
        optgroup.label = category;
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
        // Load profiles data directly from JSON file
        const response = await fetch('profiles.json');
        const data = await response.json();

        if (data.error) {
            showError(data.error);
            return;
        }

        // Get the selected profile
        const profile = data.profiles[profileId];

        if (!profile) {
            showError('Profile not found');
            return;
        }

        // Show and populate the profile select dropdown with all profiles
        const profiles = [];
        for (const [id, profileData] of Object.entries(data.profiles)) {
            profiles.push({
                'id': id,
                'name': profileData.name,
                'category': profileData.category,
                'icon': profileData.icon
            });
        }
        const profileSelect = document.getElementById('profileSelect');
        profileSelect.style.display = 'block';
        populateProfileSelect(profiles, profileSelect);

        // Use the form configuration from profiles.json
        const formConfig = profile.form;

        // Update language field if present
        if (formConfig.fields) {
            formConfig.fields.forEach(field => {
                if (field.name === 'language' && field.type === 'hidden') {
                    field.value = 'en';
                }
            });
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
    const actionInput = document.getElementById('actionInput');
    formTitle.textContent = profileName;
    actionInput.value = profileId;
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

            // If options are empty and field name is 'model', fetch models from API
            if ((!field.options || field.options.length === 0) && field.name === 'model') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading models...';
                input.appendChild(loadingOption);

                // Fetch models from API
                fetchModelsForSelect(input);
            }
            // If options are empty and field name is 'language', fetch languages from JSON
            else if ((!field.options || field.options.length === 0) && field.name === 'language') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading languages...';
                input.appendChild(loadingOption);

                // Fetch languages from JSON
                fetchLanguagesForSelect(input);
            }
            else if (field.options && field.options.length) {
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

/**
 * Fetch models from API and populate select element
 */
async function fetchModelsForSelect(selectElement) {
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
 */
async function fetchLanguagesForSelect(selectElement) {
    try {
        const response = await fetch('languages.json');
        const data = await response.json();

        if (data.error) {
            console.error('Failed to load languages:', data.error);
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

        // Add languages from JSON
        if (data.languages) {
            for (const [langCode, langData] of Object.entries(data.languages)) {
                const option = document.createElement('option');
                option.value = langCode;
                option.textContent = (langData.flag ? ' ' + langData.flag : '') + langData.name;
                selectElement.appendChild(option);
            }
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

/**
 * Display an error message in the results area
 *
 * @param {string} message - The error message to display
 * @returns {void}
 */
function showError(message) {
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');

    resultsContent.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
    resultsArea.style.display = 'block';

    // Scroll to error
    resultsArea.scrollIntoView({ behavior: 'smooth' });
}

// DocMind-specific JavaScript
document.addEventListener('DOMContentLoaded', async function() {
    // Load categories data
    categoriesData = await loadCategories();
    // Load available profiles
    loadProfiles();
    // Set up form submission
    document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
});
