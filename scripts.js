
// Global variables to store categories, profiles, languages, and prompts
let categoriesData = null;
let profilesData = null;
let languagesData = null;
let promptsData = null;

/**
 * Toggle between light and dark themes
 *
 * This function toggles the application theme between light and dark modes
 * by updating the theme preference cookie and applying the appropriate CSS
 * classes. It also updates the theme toggle button icon.
 *
 * @return {void}
 *
 * @note Uses document.cookie to store theme preference
 * @note Updates the theme-toggle button icon based on current theme
 * @note Triggers a page reload to apply theme changes
 * @see Document.addEventListener('DOMContentLoaded') - Sets up theme toggle handler
 */
function toggleTheme() {
    // Get current theme from cookie or use system preference
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        acc[name] = decodeURIComponent(value);
        return acc;
    }, {});

    const currentTheme = cookies['docmind-theme'] || 'system';
    let newTheme;

    // Determine new theme based on current theme
    if (currentTheme === 'light') {
        newTheme = 'dark';
    } else if (currentTheme === 'dark') {
        newTheme = 'light';
    } else {
        // If system preference, check what the system is using
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        newTheme = systemPrefersDark ? 'light' : 'dark';
    }

    // Set theme cookie (30 days)
    const expirationDate = new Date();
    expirationDate.setDate(expirationDate.getDate() + 30);
    document.cookie = `docmind-theme=${encodeURIComponent(newTheme)}; expires=${expirationDate.toUTCString()}; path=/`;

    // Update theme icon immediately
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
    }

    // Reload page to apply theme
    window.location.reload();
}

/**
 * Toggle sidebar menu for mobile devices
 *
 * This function toggles the visibility of the sidebar menu on mobile devices.
 * It adds/removes the 'active' class to show/hide the sidebar.
 *
 * @return {void}
 *
 * @note Toggles the 'active' class on the sidebar element
 * @note Updates the menu toggle button icon
 * @see Document.addEventListener('DOMContentLoaded') - Sets up menu toggle handler
 */
function toggleMenu() {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.getElementById('menuToggle');

    if (sidebar && menuToggle) {
        sidebar.classList.toggle('active');
        // Update menu icon based on sidebar state
        menuToggle.innerHTML = sidebar.classList.contains('active') ? '✕' : '☰';
    }
}

/**
 * Apply theme based on user preference
 *
 * This function applies the theme based on user preference stored in cookies
 * or system preference. It updates the theme toggle button icon accordingly.
 *
 * @return {void}
 *
 * @note Reads theme preference from docmind-theme cookie
 * @note Falls back to system preference if no cookie is set
 * @note Updates the theme toggle button icon
 * @see Document.addEventListener('DOMContentLoaded') - Calls this function on page load
 */
function applyTheme() {
    // Get theme preference from cookie
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        acc[name] = decodeURIComponent(value);
        return acc;
    }, {});

    const theme = cookies['docmind-theme'] || 'system';

    // Update theme icon based on current theme
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        if (theme === 'dark') {
            themeIcon.textContent = '☀️';
        } else if (theme === 'light') {
            themeIcon.textContent = '🌙';
        } else {
            // System preference - check what the system is using
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            themeIcon.textContent = systemPrefersDark ? '☀️' : '🌙';
        }
    }
}

/**
 * Switch between different views in the application
 *
 * This function handles view switching by hiding all views and showing
 * the selected view. It also updates the active state of navigation buttons.
 *
 * @param {string} viewName - The name of the view to show (e.g., 'home', 'tools', 'history', 'settings')
 * @return {void}
 *
 * @note Hides all views and shows only the selected one
 * @note Updates active state of sidebar navigation buttons
 * @note Updates the page title based on the view
 * @see Document.addEventListener('DOMContentLoaded') - Sets up view switching handlers
 */
function switchView(viewName) {
    // Hide all views
    const views = document.querySelectorAll('.view');
    views.forEach(view => {
        view.classList.remove('active-view');
        view.style.display = 'none';
    });

    // Show the selected view
    const selectedView = document.querySelector(`.${viewName}-view`);
    if (selectedView) {
        selectedView.classList.add('active-view');
        selectedView.style.display = 'block';
    }

    // Update active state of navigation buttons
    const navButtons = document.querySelectorAll('.nav-item');
    navButtons.forEach(button => {
        button.classList.remove('active');
        if (button.dataset.view === viewName) {
            button.classList.add('active');
        }
    });

    // Update page title
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        switch (viewName) {
            case 'home':
                pageTitle.textContent = 'Welcome to DocMind AI';
                break;
            case 'tools':
                pageTitle.textContent = 'AI Tools';
                break;
            case 'history':
                pageTitle.textContent = 'Analysis History';
                break;
            case 'settings':
                pageTitle.textContent = 'Application Settings';
                break;
            default:
                pageTitle.textContent = 'DocMind AI';
        }
    }
}


/**
 * Apply syntax highlighting using highlight.js
 *
 * This function applies syntax highlighting to all code blocks on the page
 * using the highlight.js library. It automatically detects the programming
 * language of each code block and applies appropriate styling.
 *
 * @return {void}
 *
 * @note This function is called after rendering results to enhance code readability
 * @note Requires the highlight.js library to be loaded
 * @note Uses the hljs global object for highlighting
 * @see displayResults() - Calls this function after rendering content
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
 * 
 * This function safely escapes HTML special characters to prevent XSS attacks
 * and ensure proper rendering of text content. It converts characters like
 * <, >, &, and " into their HTML entity equivalents.
 * 
 * @param {string} text - The text to escape
 * @return {string} The escaped HTML-safe text
 * 
 * @note Uses the DOM API for reliable escaping
 * @note Prevents script injection and HTML injection attacks
 * @note Used for displaying user-generated or API-generated content safely
 * @see displayResults() - Uses this for rendering code blocks
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Extract code fence information from text
 * 
 * This function analyzes text to detect markdown code fences and extract
 * the programming language type and the actual code content. It's used to
 * determine how to display and highlight code blocks in the UI.
 * 
 * @param {string} text - Text to analyze
 * @param {string} [defaultType='text'] - Default type when no fence is found
 * @returns {Object} Object with 'type' and 'text' keys
 * 
 * @note Supports markdown code fences in format ```language
 * @note Returns empty type if no language is specified in the fence
 * @note If no fence is found, uses the default type
 * @note The returned object contains:
 *       - type: The detected language type (e.g., 'json', 'markdown', 'text')
 *       - text: The content inside the code fences (or original text if no fence)
 * @see displayResults() - Uses this to determine how to render response content
 * @see jsonToMarkdown() - Uses this for JSON content processing
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
 * This function fetches and parses a JSON configuration file from the server.
 * It handles errors gracefully and can extract a specific root key from the
 * JSON data if provided.
 * 
 * @param {string} filename - The JSON file to load (e.g., 'profiles.json')
 * @param {string} rootKey - The root key to extract from the JSON data (e.g., 'profiles')
 * @returns {Promise<Object|null>} Promise resolving to the extracted data or null on error
 * 
 * @note Uses the Fetch API to retrieve the JSON file
 * @note Handles network errors and JSON parsing errors
 * @note If rootKey is provided, returns data[rootKey] if it exists, otherwise returns entire data
 * @note Logs errors to console for debugging
 * @note Used for loading profiles, languages, categories, and prompts
 * @see displayProfiles() - Uses this to load profiles data
 * @see populateProfileSelect() - Uses this to load profiles data
 * @see Document.addEventListener('DOMContentLoaded') - Calls this for initial data loading
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
 * This function renders all available profiles in the main interface,
 * organizing them by category. Each profile is displayed as a clickable
 * card that loads the corresponding form when clicked.
 *
 * @return {void}
 *
 * @note Profiles are grouped by their 'category' property
 * @note Each category section includes a header with icon and description
 * @note Profile cards display the profile icon, name, and description
 * @note Clicking a profile card calls loadProfileForm() with the profile ID
 * @note Uses the global profilesData and categoriesData variables
 * @note Displays an error if profilesData is not available
 * @see loadProfileForm() - Called when a profile card is clicked
 * @see showError() - Used to display error messages
 * @see Document.addEventListener('DOMContentLoaded') - Calls this function on page load
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
 * Display profiles for a specific category
 *
 * This function displays only the profiles belonging to a specific category.
 * It filters the profiles by category and renders them in the main interface.
 *
 * @param {string} category - The category to display
 * @return {void}
 *
 * @note Shows only profiles from the specified category
 * @note Updates the page title to show the category name
 * @note Uses the global profilesData and categoriesData variables
 * @note Displays an error if profilesData is not available
 * @see switchView() - Calls this function when a category is selected
 * @see loadProfileForm() - Called when a profile card is clicked
 */
function displayProfilesByCategory(category) {
    const profilesGrid = document.getElementById('profilesGrid');
    profilesGrid.innerHTML = '';

    // Check if profilesData is available
    if (!profilesData) {
        showError('No profiles data available');
        return;
    }

    // Filter profiles by category
    const categoryProfiles = [];
    for (const [profile_id, profile_data] of Object.entries(profilesData)) {
        if (profile_data.category === category) {
            categoryProfiles.push({
                'id': profile_id,
                'name': profile_data.name,
                'description': profile_data.description,
                'icon': profile_data.icon
            });
        }
    }

    // Get category info from categories.json
    const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;

    // Create category section
    const categoryDiv = document.createElement('section');
    categoryDiv.className = 'category-section category-' + category;

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

    // Update page title
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.textContent = (categoryInfo ? categoryInfo.icon + ' ' + categoryInfo.name : category) + ' Tools';
    }
}

/**
 * Populate the profile select dropdown with grouped options
 * 
 * This function populates the profile selection dropdown with all available
 * profiles, organized by category. It creates optgroups for each category
 * and adds profile options with icons and names.
 * 
 * @param {HTMLSelectElement} profileSelect - The select element to populate
 * @return {void}
 * 
 * @note Profiles are grouped by their 'category' property
 * @note Each category becomes an optgroup in the dropdown
 * @note Profile icons are displayed before the profile name
 * @note Uses the global profilesData and categoriesData variables
 * @note Logs an error to console if profilesData is not available
 * @see loadProfileForm() - Uses this to populate the dropdown after loading a profile
 * @see populateProfileSelect() - Called by loadProfileForm()
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
 * This function loads and displays the form for a specific profile. It:
 * 1. Hides the profile selector grid
 * 2. Retrieves the profile data from profilesData
 * 3. Shows the profile selection dropdown
 * 4. Populates the dropdown with all profiles
 * 5. Sets up event listeners for profile selection
 * 6. Updates hidden language fields if present
 * 7. Displays the profile form
 * 
 * @param {string} profileId - The ID of the profile to load
 * @return {Promise<void>}
 * 
 * @note Uses the global profilesData variable
 * @note Shows an error if profile is not found or profilesData is unavailable
 * @note Automatically sets hidden language fields to 'en' if present
 * @note Sets up a change event listener on the profileSelect dropdown
 * @note Calls displayProfileForm() to render the form
 * @see displayProfileForm() - Called to render the form
 * @see showError() - Used to display error messages
 * @see populateProfileSelect() - Called to populate the dropdown
 * @see displayProfiles() - Called when user clicks a profile card
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
            console.error('Profile not found:', profileId);
            // Check if resultsArea exists before showing error
            const resultsArea = document.getElementById('resultsArea');
            if (resultsArea) {
                showError('Profile not found');
            } else {
                console.error('Profile not found and results area not available');
                // Show error in the main content area as fallback
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    const errorElement = document.createElement('div');
                    errorElement.className = 'error';
                    errorElement.innerHTML = `
                        <strong>Error:</strong> Profile not found
                    `;
                    mainContent.innerHTML = '';
                    mainContent.appendChild(errorElement);
                }
            }
            return;
        }
        // Set the profile ID in the profile object
        profile.id = profileId;

        // Check if required form elements exist
        const profileForm = document.getElementById('profileForm');
        const formFields = document.getElementById('formFields');
        const actionInput = document.getElementById('actionInput');
        const topTitle = document.getElementById('topTitle');
        const topDescription = document.getElementById('topDescription');
        const profileSelect = document.getElementById('profileSelect');
        const profileSelector = document.querySelector('.profile-selector');

        if (!profileForm || !formFields || !actionInput || !topTitle || !topDescription || !profileSelect || !profileSelector) {
            console.error('Required form elements not found');
            // Show error in the main content area as fallback
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                const errorElement = document.createElement('div');
                errorElement.className = 'error';
                errorElement.innerHTML = `
                    <strong>Error:</strong> Required form elements not found
                `;
                mainContent.innerHTML = '';
                mainContent.appendChild(errorElement);
            }
            return;
        }

        // Clear the page and show profile selector
        try {
            profileSelector.style.display = 'none';
        } catch (error) {
            console.error('Failed to hide profile selector:', error);
        }

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
 * This function renders the form for a specific profile. It:
 * 1. Updates the top title and description with profile information
 * 2. Sets up the form action input with the profile ID
 * 3. Retrieves saved preferences from cookies
 * 4. Creates form fields based on the profile configuration
 * 5. Shows the form and hides the results area
 * 6. Scrolls to the form for better UX
 * 
 * @param {Object} profile - The profile configuration object
 * @return {void}
 * 
 * @note Uses the profile's form.fields array to create input elements
 * @note Retrieves saved model and language preferences from cookies
 * @note Hides the results area when displaying a new form
 * @note Uses smooth scrolling to bring the form into view
 * @see createFormField() - Called to create each form field
 * @see loadProfileForm() - Calls this function after loading profile data
 */
function displayProfileForm(profile) {
    // Update top title and description
    const topTitle = document.getElementById('topTitle');
    const topDescription = document.getElementById('topDescription');
    if (topTitle) topTitle.textContent = profile.icon + ' ' + profile.name;
    if (topDescription) topDescription.textContent = profile.description || '';

    // Populate the form fields
    const profileForm = document.getElementById('profileForm');
    const formFields = document.getElementById('formFields');
    const actionInput = document.getElementById('actionInput');

    if (!profileForm || !formFields || !actionInput) {
        console.error('Required form elements not found in displayProfileForm');
        return;
    }

    actionInput.value = profile.id;
    formFields.innerHTML = '';

    // Get cookies for model and language
    const cookies = document.cookie.split(';').reduce((acc, cookie) => {
        const [name, value] = cookie.trim().split('=');
        acc[name] = decodeURIComponent(value);
        return acc;
    }, {});

    // Create form fields based on formConfig
    if (profile.form && profile.form.fields) {
        profile.form.fields.forEach(field => {
            const fieldElement = createFormField(field, cookies);
            formFields.appendChild(fieldElement);
        });
    }

    // Show the form and hide results area
    try {
        if (profileForm.style) profileForm.style.display = 'block';
        const resultsArea = document.getElementById('resultsArea');
        if (resultsArea && resultsArea.style) resultsArea.style.display = 'none';
    } catch (error) {
        console.error('Failed to update form visibility:', error);
    }

    // Set up cancel button to go back to tools view
    const cancelBtn = document.getElementById('cancelBtn');
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            switchView('tools');
        };
    }

    // Switch to form view
    switchView('form');

    // Scroll to form
    if (profileForm.scrollIntoView) {
        profileForm.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Create a form field element based on field configuration
 * 
 * This function creates a form field element based on the provided field
 * configuration. It supports various field types including text, textarea,
 * select, hidden, and file inputs. For select fields, it can fetch options
 * dynamically from the API (for models and prompts) or use predefined options.
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
 * 
 * @note Supports dynamic fetching of models and prompts from API
 * @note Uses global languagesData for language selection
 * @note Applies saved preferences from cookies (docmind-model, docmind-language)
 * @note Creates appropriate HTML elements based on field type
 * @note Adds help text if provided in field configuration
 * @see fetchModelsForSelect() - Called for dynamic model loading
 * @see fetchPromptsForSelect() - Called for dynamic prompt loading
 * @see displayProfileForm() - Calls this function for each field
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
 * This function fetches available AI models from the server API and populates
 * a select element with the retrieved models. It handles loading states,
 * errors, and applies saved preferences from cookies.
 * 
 * @param {HTMLSelectElement} selectElement - The select element to populate
 * @param {Object} cookies - Object containing cookie values
 * @return {Promise<void>}
 * 
 * @note Makes API call to docmind.php?action=get_models
 * @note Shows loading state while fetching
 * @note Applies saved model preference from docmind-model cookie
 * @note Handles network errors and API errors gracefully
 * @note Clears loading option and adds error option on failure
 * @see createFormField() - Calls this function for dynamic model loading
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
 * 
 * This function populates a select element with available languages from the
 * global languagesData variable. It's currently not used since languagesData
 * is loaded globally and used directly in createFormField().
 * 
 * @param {HTMLSelectElement} selectElement - The select element to populate
 * @return {Promise<void>}
 * 
 * @note This function is currently not used in the codebase
 * @note Languages are loaded globally and used directly in createFormField()
 * @note Kept for potential future use or refactoring
 * @see createFormField() - Uses global languagesData directly
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
 * This function fetches available prompt templates from the server API and
 * populates a select element with the retrieved prompts. It also sets up an
 * event listener to automatically populate the prompt textarea when a prompt
 * is selected.
 * 
 * @param {HTMLSelectElement} selectElement - The select element to populate
 * @return {Promise<void>}
 * 
 * @note Makes API call to docmind.php?action=get_prompts
 * @note Caches prompts data globally in promptsData variable
 * @note Shows loading state while fetching
 * @note Handles network errors and API errors gracefully
 * @note Sets up change event listener to auto-fill prompt textarea
 * @see createFormField() - Calls this function for dynamic prompt loading
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
 * This function handles the form submission event. It:
 * 1. Prevents the default form submission
 * 2. Collects form data
 * 3. Shows a loading state on the submit button
 * 4. Sends the form data to the server API
 * 5. Processes the response
 * 6. Saves user preferences as cookies
 * 7. Restores the button state
 * 
 * @param {Event} event - The form submission event
 * @return {Promise<void>}
 * 
 * @note Uses the FormData API to collect form data
 * @note Makes POST request to docmind.php
 * @note Expects JSON response from the server
 * @note Saves model and language preferences as cookies (30-day expiration)
 * @note Handles network errors and API errors gracefully
 * @note Shows loading state during processing
 * @see displayResults() - Called to render successful responses
 * @see showError() - Called to display error messages
 * @see Document.addEventListener('DOMContentLoaded') - Sets up this handler
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
 * This function renders the API response in the results area. It:
 * 1. Extracts the response content from the API response
 * 2. Updates the results title and description based on the profile
 * 3. Detects the content type (JSON, markdown, or other)
 * 4. Converts the content to the appropriate display format
 * 5. Renders the content with syntax highlighting
 * 6. Shows the results area and applies syntax highlighting
 * 7. Scrolls to the results
 * 
 * @param {Object} results - The results object from the API
 * @return {void}
 * 
 * @note Handles JSON, markdown, and plain text content
 * @note Supports profile-specific display formats (markdown, html, json)
 * @note Uses marked.js for markdown to HTML conversion
 * @note Uses highlight.js for syntax highlighting
 * @note Calls jsonToMarkdown() for JSON content conversion
 * @see handleFormSubmit() - Calls this function on successful form submission
 * @see applySyntaxHighlighting() - Called to highlight code blocks
 * @see showError() - Called if no valid response content is found
 */
function displayResults(results) {
    // Get results area and title elements
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsDescription = document.getElementById('resultsDescription');

    // Extract the actual response content from the API response
    let responseContent = '';
    if (results.error) {
        // If results contain an error, display it
        console.error('Error found:\n', results.error);
        showError(results.error);
        return;
    //} else if (results.json) {
    //    // If results contain 'json' key, return it as object
    //    // TODO Do we need it?
    //    responseContent = results.json;
    } else if (results.html) {
        // If results contain HTML, return it directly
        // TODO Do we need it?
        console.log('HTML content found:\n', results.html);
        responseContent = results.html;
    } else if (results.response && results.response.choices && results.response.choices[0] && results.response.choices[0].message && results.response.choices[0].message.content) {
        // Extract content from OpenAI chat completion response
        console.log('OpenAI response found:\n', results.response.choices[0].message.content);
        responseContent = results.response.choices[0].message.content;
    } else {
        console.error('No valid response content found');
        showError('No response content available');
        return;
    }

    // Get the current profile from results.profile
    const profileId = results.profile || '';
    const profile = profilesData[profileId] || null;

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

    // Check if the result contains markdown code fences
    const resultsInfo = extractCodeFenceInfo(responseContent, 'markdown');
    console.log('Code fence info:\n', resultsInfo);

    // Check the desired display format from profile
    const displayFormat = profile && profile.display ? profile.display.toLowerCase() : '';
    console.log('Display format requested: ', displayFormat);

    // Check if the response need conversion based on resultsInfo format and display format
    if (resultsInfo.type === 'json') {
        // If the result is JSON, parse it
        try {
            const jsonData = JSON.parse(resultsInfo.text);
            console.log('Parsed JSON data:\n', jsonData);

            // JSON can be converted to markdown, html, or displayed as JSON (with syntax highlighting)
            if (displayFormat === 'markdown') {
                // Convert JSON to markdown
                const markdownContent = jsonToMarkdown(jsonData);
                console.log('JSON to Markdown conversion:\n', markdownContent);
                resultsContent.innerHTML = `<pre><code class="${displayFormat}">${markdownContent}</code></pre>`;
            } else if (displayFormat === 'html') {
                // Convert JSON to HTML via markdown
                const markdownContent = jsonToMarkdown(jsonData);
                console.log('JSON to Markdown conversion for HTML:\n', markdownContent);
                resultsContent.innerHTML = marked.parse(markdownContent);
            } else {
                // Convert JSON to pretty JSON string
                const prettyJson = JSON.stringify(jsonData, null, 2);
                console.log('Displaying as pretty JSON:\n', prettyJson);
                resultsContent.innerHTML = `<pre><code class="json">${prettyJson}</code></pre>`;
            }
        } catch (error) {
            console.error('Error parsing JSON:', error);
            resultsContent.innerHTML = `<pre><code>${resultsInfo.text}</code></pre>`;
        }
    } else if (resultsInfo.type === 'markdown') {
        // If the result is markdown, convert it to HTML
        console.log('Processing markdown content');
        if (displayFormat === 'html') {
            // Convert JSON to HTML via markdown
            console.log('Converting markdown to HTML');
            resultsContent.innerHTML = marked.parse(resultsInfo.text);
        } else {
            // Keep as markdown with syntax highlighting
            console.log('Displaying as markdown with syntax highlighting');
            resultsContent.innerHTML = `<pre><code class="markdown">${escapeHtml(resultsInfo.text)}</code></pre>`;
        }
    } else {
        // For other types, use the original response content, with syntax highlighting
        console.log(`Displaying as ${resultsInfo.type} type\n`, resultsInfo.text);
        resultsContent.innerHTML = `<pre><code class="${resultsInfo.type}">${escapeHtml(resultsInfo.text)}</code></pre>`;
    }

    // Check if resultsContent is not empty
    if (resultsContent.innerHTML.trim() !== '') {
        // Show results area
        resultsArea.style.display = 'block';
        // Switch to results view
        switchView('results');
        // Apply syntax highlighting
        applySyntaxHighlighting();
    } else {
        console.error('Results content is empty');
    }

    // Set up new analysis button to go back to tools view
    const newAnalysisBtn = document.getElementById('newAnalysisBtn');
    if (newAnalysisBtn) {
        newAnalysisBtn.onclick = function() {
            switchView('tools');
        };
    }

    // Scroll to results
    resultsArea.scrollIntoView({ behavior: 'smooth' });
}

/**
 * Display an error message in the results area
 * 
 * This function displays an error message in the results area. It uses the
 * error template if available, or falls back to direct HTML rendering.
 * 
 * @param {string} message - The error message to display
 * @return {void}
 * 
 * @note Uses the error template (#errorTemplate) if available
 * @note Falls back to direct HTML rendering if template is not found
 * @note Shows the results area and scrolls to it
 * @note Escapes the message for security
 * @see handleFormSubmit() - Calls this function on form submission errors
 * @see loadProfileForm() - Calls this function if profile loading fails
 * @see displayProfiles() - Calls this function if profiles data is unavailable
 */
function showError(message) {
    // Get the results area element
    const resultsArea = document.getElementById('resultsArea');

    // Check if resultsArea exists
    if (!resultsArea) {
        console.error('Results area not found:', message);
        // Try to show error in main content as fallback
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            const errorElement = document.createElement('div');
            errorElement.className = 'error';
            errorElement.innerHTML = `
                <strong>Error:</strong> ${escapeHtml(message)}
            `;
            mainContent.innerHTML = '';
            mainContent.appendChild(errorElement);
        }
        return;
    }

    // Get the error template
    const errorTemplate = document.getElementById('errorTemplate');
    if (errorTemplate) {
        // Clone the template content
        const errorElement = errorTemplate.content.cloneNode(true);

        // Set the error message
        const errorMessageElement = errorElement.querySelector('.error-message');
        if (errorMessageElement) {
            errorMessageElement.textContent = message;
        }

        // Display error message
        resultsArea.innerHTML = '';
        resultsArea.appendChild(errorElement);
    } else {
        // Fallback to direct HTML if template not found
        resultsArea.innerHTML = `<div class="error-message">${escapeHtml(message)}</div>`;
    }

    // Check if resultsArea.style exists before trying to access it
    if (resultsArea.style) {
        // Show results area
        resultsArea.style.display = 'block';
        // Switch to results view
        switchView('results');
        // Scroll to error
        resultsArea.scrollIntoView({ behavior: 'smooth' });
    } else {
        console.error('Results area style property not available');
    }
}

/**
 * Convert structured JSON objects to markdown for better human readability
 * 
 * This function converts JSON data structures into human-readable markdown
 * format. It handles strings, arrays, and objects differently:
 * - Strings: Returned as-is
 * - Arrays of objects: Converted to markdown tables
 * - Arrays of primitives: Converted to markdown lists
 * - Objects: Converted to markdown headings and paragraphs
 * 
 * @param {Object|Array|string} data - The JSON data to convert
 * @return {string} Markdown representation of the data
 * 
 * @note Uses arrayOfObjectsToTable() for arrays of objects
 * @note Uses arrayToList() for arrays of primitives
 * @note Uses objectToMarkdown() for objects
 * @note Falls back to String() for other types
 * @see displayResults() - Calls this function for JSON content conversion
 * @see arrayOfObjectsToTable() - Called for table conversion
 * @see arrayToList() - Called for list conversion
 * @see objectToMarkdown() - Called for object conversion
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
 * This function converts a JavaScript object into markdown format with
 * headings and paragraphs. Each key becomes a heading, and each value
 * becomes content. Nested objects and arrays are handled recursively.
 * 
 * @param {Object} obj - The object to convert
 * @param {number} [level=3] - The heading level to start with (1-6)
 * @return {string} Markdown representation
 * 
 * @note Skips null and undefined values
 * @note Capitalizes the first letter of each key
 * @note Uses markdown headings (#) based on the level parameter
 * @note Handles nested objects recursively with increasing heading level
 * @note Handles arrays by calling jsonToMarkdown() recursively
 * @note Converts numbers and booleans to strings
 * @see jsonToMarkdown() - Calls this function for object conversion
 */
function objectToMarkdown(obj, level = 3) {
    let markdown = '';

    for (const [key, value] of Object.entries(obj)) {
        // Skip null/undefined values
        if (value === null || value === undefined) continue;

        // Capitalize first letter of key
        const formattedKey = key.charAt(0).toUpperCase() + key.slice(1);

        // Create heading based on level
        const heading = '\n' + '#'.repeat(Math.min(level, 6)) + ' ' + formattedKey + '\n\n';

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
 * This function converts a JavaScript array into a markdown list format.
 * Each array item becomes a list item. Nested arrays and objects are
 * handled recursively.
 * 
 * @param {Array} arr - The array to convert
 * @return {string} Markdown list representation
 * 
 * @note Skips null and undefined values
 * @note Handles nested arrays recursively
 * @note Handles objects by calling objectToMarkdown() recursively
 * @note Converts simple values (strings, numbers, booleans) to strings
 * @note Each item is prefixed with "- " for markdown list format
 * @see jsonToMarkdown() - Calls this function for array conversion
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
 * This function converts an array of objects into a markdown table format.
 * Each object represents a row, and each key represents a column. The
 * function automatically detects all unique keys across all objects.
 * 
 * @param {Array<Object>} arr - The array of objects to convert
 * @return {string} Markdown table representation
 * 
 * @note Returns empty string if array is empty
 * @note Automatically detects all unique keys from all objects
 * @note Creates a header row with column names
 * @note Creates a separator row with markdown table syntax
 * @note Creates data rows for each object
 * @note Handles null/undefined values as empty cells
 * @note Converts nested objects to JSON strings
 * @see jsonToMarkdown() - Calls this function for arrays of objects
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

/**
 * DocMind-specific JavaScript initialization
 *
 * This function initializes the DocMind AI application when the DOM is fully loaded.
 * It performs the following tasks:
 * 1. Loads configuration data (categories, profiles, languages)
 * 2. Displays available profiles in the UI
 * 3. Sets up the form submission handler
 * 4. Sets up the theme toggle button
 * 5. Sets up view switching for sidebar navigation
 * 6. Sets up category buttons in the sidebar
 * 7. Applies the user's theme preference
 *
 * @note This is the main entry point for the application
 * @note Uses async/await for sequential data loading
 * @note Sets up global variables: categoriesData, profilesData, languagesData
 * @note Calls displayProfiles() to render profile cards
 * @note Attaches form submission handler to the API form
 * @note Sets up theme toggle button click handler
 * @note Sets up sidebar navigation click handlers
 * @note Sets up category button click handlers
 * @note Applies theme preference on page load
 * @see loadJSONResource() - Used to load configuration data
 * @see displayProfiles() - Renders profile cards
 * @see handleFormSubmit() - Handles form submissions
 * @see toggleTheme() - Handles theme toggling
 * @see switchView() - Handles view switching
 * @see applyTheme() - Applies theme preference
 */
document.addEventListener('DOMContentLoaded', async function() {
    // Load categories data
    categoriesData = await loadJSONResource('categories.json', 'categories');
    // Load profiles data
    profilesData = await loadJSONResource('profiles.json', 'profiles');
    // Load languages data
    languagesData = await loadJSONResource('languages.json', 'languages');

    // Add category buttons to sidebar after loading data
    if (categoriesData) {
        addCategoryButtonsToSidebar(categoriesData);
    }

    // Display profiles in the UI
    displayProfiles();
    // Set up form submission
    document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
    // Set up theme toggle button
    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
    // Set up menu toggle button
    document.getElementById('menuToggle')?.addEventListener('click', toggleMenu);
    // Set up view switching for sidebar navigation
    const navButtons = document.querySelectorAll('.nav-item');
    navButtons.forEach(button => {
        button.addEventListener('click', function() {
            const viewName = this.dataset.view;
            if (viewName) {
                switchView(viewName);
                // Close sidebar on mobile after selecting a view
                const sidebar = document.querySelector('.sidebar');
                if (sidebar && window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    const menuToggle = document.getElementById('menuToggle');
                    if (menuToggle) {
                        menuToggle.innerHTML = '☰';
                    }
                }
            }
        });
    });
    // Apply theme preference
    applyTheme();
});

/**
 * Add category buttons to the sidebar
 *
 * This function dynamically adds category buttons to the sidebar navigation
 * based on the categories data. Each button represents a category and when
 * clicked, it displays the tools/profiles belonging to that category.
 *
 * @param {Object} categories - The categories data object
 * @return {void}
 *
 * @note Creates a button for each category in the categories object
 * @note Inserts buttons after the Home button in the sidebar
 * @note Sets up click handlers to display profiles by category
 * @note Updates active state of navigation buttons
 * @see displayProfilesByCategory() - Called when a category button is clicked
 * @see switchView() - Called to switch to tools view
 */
function addCategoryButtonsToSidebar(categories) {
    const sidebarNav = document.querySelector('.sidebar-nav');
    const homeButton = document.querySelector('.nav-item[data-view="home"]');

    // Create a separator for categories
    const categorySeparator = document.createElement('div');
    categorySeparator.className = 'nav-separator';
    categorySeparator.textContent = 'Categories';
    categorySeparator.style.padding = '0.5rem 1.5rem';
    categorySeparator.style.fontSize = '0.75rem';
    categorySeparator.style.fontWeight = '600';
    categorySeparator.style.color = 'var(--text-secondary)';
    categorySeparator.style.textTransform = 'uppercase';
    categorySeparator.style.letterSpacing = '0.05em';

    // Insert separator after Home button
    if (homeButton && homeButton.nextSibling) {
        sidebarNav.insertBefore(categorySeparator, homeButton.nextSibling);
    }

    // Add category buttons
    for (const [categoryId, categoryData] of Object.entries(categories)) {
        const categoryButton = document.createElement('button');
        categoryButton.className = 'nav-item';
        categoryButton.dataset.category = categoryId;
        categoryButton.innerHTML = `
            <span class="nav-icon">${categoryData.icon || '📄'}</span>
            <span class="nav-text">${categoryData.name}</span>
        `;

        // Insert category button after the separator
        if (categorySeparator.nextSibling) {
            sidebarNav.insertBefore(categoryButton, categorySeparator.nextSibling);
        } else {
            sidebarNav.appendChild(categoryButton);
        }

        // Set up click handler
        categoryButton.addEventListener('click', function() {
            // Update active state of navigation buttons
            const navButtons = document.querySelectorAll('.nav-item');
            navButtons.forEach(button => {
                button.classList.remove('active');
            });
            this.classList.add('active');

            // Switch to tools view and display profiles by category
            switchView('tools');
            displayProfilesByCategory(categoryId);

            // Close sidebar on mobile
            const sidebar = document.querySelector('.sidebar');
            if (sidebar && window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                const menuToggle = document.getElementById('menuToggle');
                if (menuToggle) {
                    menuToggle.innerHTML = '☰';
                }
            }
        });
    }
}

