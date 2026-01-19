
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
 */
async function loadCategories() {
    try {
        const response = await fetch('categories.json');
        const data = await response.json();

        if (data.error) {
            console.error('Failed to load categories:', data.error);
            return null;
        }

        return data.categories;
    } catch (error) {
        console.error('Failed to load categories:', error.message);
        return null;
    }
}

// Global variable to store categories
let categoriesData = null;

// DocMind-specific JavaScript
document.addEventListener('DOMContentLoaded', async function() {
    // Load categories data
    categoriesData = await loadCategories();

    // Load available profiles
    loadProfiles();

    // Set up form submission
    document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
});

async function loadProfiles() {
    try {
        // Load profiles data directly from JSON file
        const response = await fetch('profiles.json');
        const data = await response.json();

        if (data.error) {
            showError(data.error);
            return;
        }

        // Extract just the profile metadata (id, name, description, category)
        const profiles = [];
        for (const [profile_id, profile_data] of Object.entries(data.profiles)) {
            profiles.push({
                'id': profile_id,
                'name': profile_data.name,
                'description': profile_data.description,
                'category': profile_data.category
            });
        }

        displayProfiles(profiles);
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

        // Get category info from categories.json
        const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;

        const categoryTitle = document.createElement('h2');
        categoryTitle.textContent = getCategoryIcon(category) + ' ' + (categoryInfo ? categoryInfo.name : category);
        categoryDiv.appendChild(categoryTitle);

        // Add category description if available
        if (categoryInfo && categoryInfo.description) {
            const categoryDescription = document.createElement('p');
            categoryDescription.textContent = categoryInfo.description;
            categoryDescription.className = 'category-description';
            categoryDiv.appendChild(categoryDescription);
        }

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
    // Use categories data if available
    if (categoriesData && categoriesData[category]) {
        return categoriesData[category].icon || '📋';
    }

    // Fallback to hardcoded icons
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
