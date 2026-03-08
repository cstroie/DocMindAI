// Global variables to store categories, tools, languages, and prompts
let categoriesData = {};
let toolsData = {};
let commonData = {};
let languagesData = {}
let promptsData = null;

/**
 * Populate top menu with categories
 * 
 * This creates clickable menu items for each category in the main navigation bar
 */
function populateCategoriesMenu() {
    const menuContainer = document.getElementById('categoriesMenu');
    if (!menuContainer || !categoriesData) return;

    for (const [categoryId, categoryData] of Object.entries(categoriesData)) {
        const menuItem = document.createElement('li');
        const menuLink = document.createElement('a');
        menuLink.href = '#';
        menuLink.dataset.view = `tools-${categoryId}`;
        //menuLink.title = categoryData.name;
        menuLink.dataset.tooltip = categoryData.name || '';
        menuLink.dataset.placement = "bottom";
        menuLink.innerHTML = `${categoryData.icon || '📁'}`;

        menuLink.addEventListener('click', (e) => {
            e.preventDefault();
            switchView(`tools-${categoryId}`);
        });

        menuItem.appendChild(menuLink);
        menuContainer.appendChild(menuItem);
    }
}

// Register Handlebars helpers
if (typeof Handlebars !== 'undefined') {
    Handlebars.registerHelper('eq', (a, b) => a === b);
    Handlebars.registerHelper('getSeverityColor', severity => {
        if (severity == 0) return '#10b981';
        if (severity <= 3) return '#3b82f6';
        if (severity <= 6) return '#f59e0b';
        return '#ef4444';
    });
    Handlebars.registerHelper('getSeverityLabel', severity => {
        if (severity == 0) return 'Normal';
        if (severity <= 3) return 'Minor';
        if (severity <= 6) return 'Moderate';
        if (severity <= 8) return 'Severe';
        return 'Critic';
    });
}

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
 * @note Updates the data-theme attribute on HTML element to apply theme changes
 * @see Document.addEventListener('DOMContentLoaded') - Sets up theme toggle handler
 */
function toggleTheme() {                                                                                                                 
    // Get current preference from localStorage                                                                                          
    const currentPreference = localStorage.getItem('docmind-theme') || 'system';                                                         
    let newPreference;                                                                                                                   

    // Cycle through theme preferences                                                                                                   
    if (currentPreference === 'light') {                                                                                                 
        newPreference = 'dark';                                                                                                          
    } else if (currentPreference === 'dark') {                                                                                           
        newPreference = 'system';                                                                                                        
    } else {                                                                                                                             
        newPreference = 'light';                                                                                                         
    }                                                                                                                                    

    // Save theme preference                                                                                                             
    localStorage.setItem('docmind-theme', newPreference);                                                                                

    // Apply the new theme                                                                                                               
    applyTheme();                                                                                                                        
}                                                                                                                                        

/**
 * Show global loading overlay
 *
 * This function displays a global loading overlay that covers the entire screen.
 * It's used during application initialization and other global operations.
 *
 * @param {string} [text="Loading..."] - Loading text to display
 * @return {void}
 *
 * @note Creates and appends loading overlay if it doesn't exist
 * @note Shows overlay with fade-in animation
 * @see hideGlobalLoading() - Hides the overlay
 * @see initializeApplication() - Uses this during app initialization
 */
function showGlobalLoading(text = "Loading...") {
    let overlay = document.getElementById('globalLoadingOverlay');
    
    if (!overlay) {
        const template = document.getElementById('globalLoadingTemplate');
        if (template) {
            overlay = template.content.cloneNode(true).querySelector('.global-loading-overlay');
            document.body.appendChild(overlay);
        } else {
            console.error('Global loading template not found');
            return;
        }
    }
    
    const loadingText = overlay.querySelector('.global-loading-text');
    if (loadingText) {
        loadingText.textContent = text;
    }
    
    overlay.classList.add('active');
}

/**
 * Hide global loading overlay
 *
 * This function hides the global loading overlay with a fade-out animation.
 *
 * @return {void}
 *
 * @note Removes active class to trigger fade-out animation
 * @see showGlobalLoading() - Shows the overlay
 */
function hideGlobalLoading() {
    const overlay = document.getElementById('globalLoadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

/**
 * Show form loading overlay
 *
 * This function displays a loading overlay specifically for form operations.
 * It's used when loading tool forms and processing form submissions.
 *
 * @param {string} [text="Loading form..."] - Loading text to display
 * @return {void}
 *
 * @note Creates and appends loading overlay if it doesn't exist
 * @note Shows overlay with fade-in animation
 * @see hideFormLoading() - Hides the overlay
 * @see displayToolForm() - Uses this when loading forms
 */
function showFormLoading(text = "Loading form...") {
    let overlay = document.getElementById('formLoadingOverlay');
    
    if (!overlay) {
        const template = document.getElementById('formLoadingTemplate');
        if (template) {
            overlay = template.content.cloneNode(true).querySelector('.form-loading-overlay');
            document.body.appendChild(overlay);
        } else {
            console.error('Form loading template not found');
            return;
        }
    }
    
    const loadingText = overlay.querySelector('.form-loading-text');
    if (loadingText) {
        loadingText.textContent = text;
    }
    
    overlay.classList.add('active');
}

/**
 * Hide form loading overlay
 *
 * This function hides the form loading overlay with a fade-out animation.
 *
 * @return {void}
 *
 * @note Removes active class to trigger fade-out animation
 * @see showFormLoading() - Shows the overlay
 */
function hideFormLoading() {
    const overlay = document.getElementById('formLoadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

/**
 * Show loading state for a specific element
 *
 * This function adds a loading state to a specific element, typically a button
 * or card. It adds loading classes and can optionally show a loading spinner.
 *
 * @param {HTMLElement} element - The element to show loading state for
 * @param {string} [text="Processing..."] - Loading text to display
 * @return {void}
 *
 * @note Adds 'loading' class to the element
 * @note Can add loading spinner if element is a button
 * @see hideLoadingState() - Removes loading state
 * @see handleFormSubmit() - Uses this for submit button
 */
function showLoadingState(element, text = "Processing...") {
    if (!element) return;
    
    element.classList.add('loading');
    
    if (element.tagName === 'BUTTON') {
        const btnText = element.querySelector('.btn-text');
        const btnLoading = element.querySelector('.btn-loading');
        
        if (btnText) btnText.style.display = 'none';
        if (btnLoading) {
            btnLoading.style.display = 'flex';
            const loadingText = btnLoading.querySelector('.loading-text');
            if (loadingText) loadingText.textContent = text;
        }
    }
}

/**
 * Hide loading state for a specific element
 *
 * This function removes the loading state from a specific element.
 *
 * @param {HTMLElement} element - The element to hide loading state for
 * @return {void}
 *
 * @note Removes 'loading' class from the element
 * @note Restores button text and hides loading spinner
 * @see showLoadingState() - Shows loading state
 */
function hideLoadingState(element) {
    if (!element) return;
    
    element.classList.remove('loading');
    
    if (element.tagName === 'BUTTON') {
        const btnText = element.querySelector('.btn-text');
        const btnLoading = element.querySelector('.btn-loading');
        
        if (btnText) btnText.style.display = 'block';
        if (btnLoading) btnLoading.style.display = 'none';
    }
}

/**
 * Apply theme based on user preference
 *
 * This function applies the theme based on user preference stored in localStorage
 * or system preference. It updates the theme toggle button icon accordingly
 * and sets the data-theme attribute on the HTML element.
 *
 * @return {void}
 *
 * @note Reads theme preference from docmind-theme localStorage
 * @note Falls back to system preference if no localStorage is set
 * @note Updates the theme toggle button icon
 * @note Sets data-theme attribute on HTML element
 * @see Document.addEventListener('DOMContentLoaded') - Calls this function on page load
 */
function applyTheme() {                                                                                                                  
    // Get theme preference from localStorage                                                                                            
    const preference = localStorage.getItem('docmind-theme') || 'system';                                                                
    let actualTheme, themeIconChar;                                                                                                      

    // Determine the actual theme to apply and icon to show                                                                              
    if (preference === 'light') {                                                                                                        
        actualTheme = 'light';                                                                                                           
        themeIconChar = '🌙';                                                                                                            
    } else if (preference === 'dark') {                                                                                                  
        actualTheme = 'dark';                                                                                                            
        themeIconChar = '☀️';                                                                                                            
    } else { // system                                                                                                                   
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;                                             
        actualTheme = systemPrefersDark ? 'dark' : 'light';                                                                              
        themeIconChar = systemPrefersDark ? '☀️' : '🌙';                                                                                  
    }                                                                                                                                    

    // Update theme button icon                                                                                                          
    const themeButton = document.getElementById('themeToggle');                                                                          
    if (themeButton) {                                                                                                                   
        themeButton.textContent = themeIconChar;                                                                                         
    }                                                                                                                                    

    // Apply the theme                                                                                                                   
    document.documentElement.setAttribute('data-theme', actualTheme);                                                                    
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
 * Copy results content to clipboard
 *
 * This function copies the content from the results area to the clipboard.
 * It handles both text content and HTML content, and shows a notification
 * to indicate success or failure.
 *
 * @return {Promise<void>}
 *
 * @note Uses the Clipboard API for modern browsers
 * @note Falls back to document.execCommand for older browsers
 * @note Shows success/error notifications using showNotification()
 * @note Handles cases where clipboard access is denied
 * @see Document.addEventListener('DOMContentLoaded') - Sets up this handler
 */
async function copyResultsToClipboard() {
    const resultsContent = document.getElementById('resultsContent');

    if (!resultsContent) {
        showError('Results content not found');
        return;
    }

    try {
        // Get the text content to copy - prioritize raw original data if available
        const rawData = resultsContent.dataset.raw;
        const codeBlock = resultsContent.querySelector('pre code');
        let textToCopy = rawData || (
            codeBlock ? 
                codeBlock.textContent :   // Use code block text if exists 
                resultsContent.textContent  // Fallback to regular content
        );

        // Use the Clipboard API if available
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(textToCopy);
            showNotification('Results copied to clipboard!', 'success');
        }
        // Fallback for older browsers
        else {
            // Create a temporary textarea element
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            textarea.style.position = 'fixed';  // Avoid scrolling to bottom
            document.body.appendChild(textarea);
            textarea.select();

            try {
                // Execute the copy command
                const success = document.execCommand('copy');
                if (success) {
                    showNotification('Results copied to clipboard!', 'success');
                } else {
                    showError('Failed to copy results to clipboard');
                }
            } catch (err) {
                showError('Failed to copy results: ' + err.message);
            } finally {
                // Remove the temporary textarea
                document.body.removeChild(textarea);
            }
        }
    } catch (error) {
        showError('Failed to copy results: ' + error.message);
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
function applySyntaxHighlighting(element) {
    // Initialize highlight.js
    if (typeof hljs !== 'undefined') {
        element.querySelectorAll('pre code').forEach((block) => {
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

    // TODO Strip the text of leading/trailing whitespace
    text = text.trim();

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
 * Load JSON resource from file with comprehensive error handling
 * 
 * This function fetches and parses a JSON configuration file from the server.
 * It provides robust error handling and can extract a specific root key from the
 * JSON data if provided. The function validates inputs and provides detailed
 * error messages for debugging.
 * 
 * @param {string} filename - The JSON file to load (e.g., 'config.json', 'tools/category/tool.json')
 * @param {string} [rootKey] - Optional root key to extract from the JSON data (e.g., 'tools')
 * @returns {Promise<Object|null>} Promise resolving to the extracted data or null on error
 * 
 * @throws {Error} If filename is invalid or network request fails
 * @note Uses the Fetch API with proper error handling for HTTP status codes
 * @note Handles network errors, JSON parsing errors, and missing data gracefully
 * @note If rootKey is provided, returns data[rootKey] if it exists, otherwise returns entire data
 * @note Logs detailed error messages to console for debugging
 * @note Used for loading configuration, tools, languages, categories, and prompts
 * @see displayTools() - Uses this to load tools data
 * @see populateToolSelect() - Uses this to load tools data
 * @see Document.addEventListener('DOMContentLoaded') - Calls this for initial data loading
 * @example
 * // Load entire config file
 * const config = await loadJSONResource('config.json');
 * 
 * // Load only tools section
 * const tools = await loadJSONResource('config.json', 'tools');
 * 
 * // Load individual tool file
 * const tool = await loadJSONResource('tools/category/tool.json');
 */
async function loadJSONResource(filename, rootKey) {
    try {
        // Validate input
        if (!filename || typeof filename !== 'string') {
            throw new Error('Invalid filename provided');
        }

        // Load data from JSON file
        const response = await fetch(filename);
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

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
 * Create category views dynamically using the tools view template
 *
 * This function creates view sections for each category in the categories data.
 * Each category view will display tools belonging to that category.
 *
 * @param {Object} categories - The categories data object
 * @return {void}
 *
 * @note Creates a view section for each category
 * @note Uses the toolsViewTemplate to create consistent category views
 * @note Each view has the class 'view' and 'category-view'
 * @note View ID is 'category-{categoryId}-view'
 * @note View data-view attribute is 'category-{categoryId}'
 * @note Each view contains a tools grid that will be populated with category tools
 * @note Calls displayToolsByCategory to populate each category view with tools
 * @see displayToolsByCategory() - Populates category views with tools
 * @see Document.addEventListener('DOMContentLoaded') - Calls this function after loading categories
 */
function createCategoriesViews(categories) {
    const viewContainer = document.querySelector('.view-container');
    const toolsViewTemplate = document.getElementById('toolsViewTemplate');
    const sidebarNav = document.querySelector('.sidebar-nav');
    const homeButton = document.querySelector('.nav-item[data-view="home"]');

    // Create a view for each category
    const categoryEntries = Object.entries(categories);
    for (let i = categoryEntries.length - 1; i >= 0; i--) {
        const [categoryId, categoryData] = categoryEntries[i];
        // Clone the template content
        const templateContent = toolsViewTemplate.content.cloneNode(true);
        const categoryView = templateContent.querySelector('.tools-view');

        // Update the view properties
        //categoryView.classList.add('category-view');
        categoryView.classList.add(`tools-${categoryId}-view`);
        categoryView.dataset.view = `tools-${categoryId}`;
        categoryView.style.display = 'none';

        // Update the title and description
        const titleElement = categoryView.querySelector('.tools-title');
        const descriptionElement = categoryView.querySelector('.tools-subtitle');
        if (titleElement) {
            titleElement.textContent = `${categoryData.icon || '📄'} ${categoryData.name}`;
        }
        if (descriptionElement) {
            descriptionElement.textContent = categoryData.description || '';
        }

        // Update the tools grid ID
        const toolsGrid = categoryView.querySelector('.tools-grid');
        if (toolsGrid) {
            toolsGrid.id = `${categoryId}ToolsGrid`;
        }

        // Append the category view to the container BEFORE populating tools
        viewContainer.appendChild(categoryView);
    }

    // After all views are appended to DOM, populate tools for each category
    for (const categoryId of Object.keys(categories)) {
        loadToolsInCategory(categoryId);
    }
}

/**
 * Download results content as text file
 *
 * This function handles downloading the results as a text file using the raw original content
 * when available. It generates a filename containing the tool ID and current date.
 *
 * @return {void}
 *
 * @note Uses dataset.raw from resultsContent when available
 * @note Falls back to text content when raw data isn't available
 * @note Shows success/error notifications using showNotification()
 */
function downloadResults() {
    const resultsContent = document.getElementById('resultsContent');
    if (!resultsContent) {
        showError('Results content not found');
        return;
    }

    // Get tool ID for filename
    const toolId = document.getElementById('toolId')?.value || 'analysis';
    const date = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    const filename = `${toolId.replace(/[^a-z0-9]/gi, '_')}-${date}.txt`;

    // Get content - same logic as copy functionality
    const rawData = resultsContent.dataset.raw;
    const textContent = rawData || resultsContent.textContent;

    if (!textContent) {
        showError('No content available to download');
        return;
    }

    try {
        // Create Blob and download
        const blob = new Blob([textContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);

        // Create temporary link
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();

        // Cleanup
        setTimeout(() => {
            URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 100);

        showNotification('Download started!', 'success');
    } catch (error) {
        showError('Failed to download: ' + error.message);
    }
}

/**
 * Populate home page with category cards
 * 
 * This function creates category cards for the home page using the template
 * and populates them with category data. Each card shows the category icon,
 * name, and description, and links to the tools view.
 * 
 * @return {void}
 * 
 * @note Uses the category card template (#cardTemplate)
 * @note Dynamically creates cards for each category with click handlers
 * @see Document.addEventListener('DOMContentLoaded') - Calls this function
 */
function populateCategoryCards() {
    const categoriesGrid = document.getElementById('categoriesGrid');
    if (!categoriesGrid) {
        console.error('Categories grid element not found');
        return;
    }

    // Clear existing grid content
    categoriesGrid.innerHTML = '';

    // Get category card template
    const template = document.getElementById('cardTemplate');
    if (!template) {
        console.error('Category card template not found');
        return;
    }

    // Add a card for each category
    for (const [categoryId, categoryData] of Object.entries(categoriesData)) {
        const clone = template.content.cloneNode(true);

        // Populate card elements
        const iconElement = clone.querySelector('aside');
        const titleElement = clone.querySelector('h4');
        const descriptionElement = clone.querySelector('p');

        if (iconElement) iconElement.textContent = categoryData.icon || '📁';
        if (titleElement) titleElement.textContent = categoryData.name;
        if (descriptionElement) descriptionElement.textContent = categoryData.description || '';

        // Add click handler to show category tools
        const card = clone.querySelector('.card');
        if (card) {
            card.addEventListener('click', () => {
                switchView('tools-' + categoryId);
            });
        }

        categoriesGrid.appendChild(clone);
    }
}

/**
 * Display tools for a specific category
 *
 * This function displays only the tools belonging to a specific category.
 * It filters the tools by category and renders them in the category view.
 *
 * @param {string} category - The category to display
 * @return {void}
 *
 * @note Shows only tools from the specified category
 * @note Updates the category view title and description
 * @note Uses the global toolsData and categoriesData variables
 * @note Displays an error if toolsData is not available
 * @see switchView() - Calls this function when a category is selected
 * @see displayToolForm() - Called when a tool card is clicked
 */
function loadToolsInCategory(category) {
    // Validate input
    if (!category || typeof category !== 'string') {
        console.error('Invalid category provided');
        return;
    }

    // Get the category tools grid
    const toolsGrid = document.getElementById(`${category}ToolsGrid`);
    if (!toolsGrid) {
        console.error(`Category tools grid not found for category: ${category}`);
        return;
    }

    // Check if toolsData is available
    if (!toolsData || Object.keys(toolsData).length === 0) {
        console.error('No tools data available');
        return;
    }

    // Filter tools by category
    const categoryTools = Object.entries(toolsData)
        .filter(([_, tool_data]) => tool_data.category === category)
        .map(([tool_id, tool_data]) => ({
            'id': tool_id,
            'name': tool_data.name || 'Unnamed Tool',
            'description': tool_data.description || 'No description available',
            'icon': tool_data.icon || '📄'
        }));

    // Get category info from categories.json
    const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;

    // Clear existing tools grid
    toolsGrid.innerHTML = '';

    // Get card template
    const template = document.getElementById('cardTemplate');
    if (!template) {
        console.error('Card template not found');
        return;
    }

    // Add a card for each tool
    categoryTools.forEach(tool => {
        const clone = template.content.cloneNode(true);

        // Populate card elements
        const iconElement = clone.querySelector('aside');
        const titleElement = clone.querySelector('h4');
        const descriptionElement = clone.querySelector('p');

        if (iconElement) iconElement.textContent = tool.icon;
        if (titleElement) titleElement.textContent = tool.name;
        if (descriptionElement) descriptionElement.textContent = tool.description;

        // Add click handler to show tool form
        const card = clone.querySelector('.card');
        if (card) {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                displayToolForm(tool.id);
            });
        }

        toolsGrid.appendChild(clone);
    });

    // Update category view title and description
    const categoryTitle = document.getElementById('categoryTitle');
    const categoryDescription = document.getElementById('categoryDescription');
    if (categoryTitle) {
        categoryTitle.textContent = (categoryInfo ? categoryInfo.icon + ' ' + categoryInfo.name : category);
    }
    if (categoryDescription) {
        categoryDescription.textContent = categoryInfo && categoryInfo.description ? categoryInfo.description : '';
    }
}

/**
 * Populate the tool select dropdown with grouped options
 * 
 * This function populates the tool selection dropdown with all available
 * tools, organized by category. It creates optgroups for each category
 * and adds tool options with icons and names.
 * 
 * @param {HTMLSelectElement} toolSelect - The select element to populate
 * @return {void}
 * 
 * @note Tools are grouped by their 'category' property
 * @note Each category becomes an optgroup in the dropdown
 * @note Tool icons are displayed before the tool name
 * @note Uses the global toolsData and categoriesData variables
 * @note Logs an error to console if toolsData is not available
 */
function populateToolSelect(toolSelect) {
    // Clear existing options
    toolSelect.innerHTML = '<option selected disabled value="">Select a tool</option>';

    // Check if toolsData is available
    if (!toolsData || Object.keys(toolsData).length === 0) {
        console.warn('No tools data available');
        return;
    }

    // Group tools by category
    const categories = {};
    for (const [toolId, toolData] of Object.entries(toolsData)) {
        const category = toolData.category;
        if (!categories[category]) {
            categories[category] = [];
        }
        categories[category].push({
            id: toolId,
            name: toolData.name,
            icon: toolData.icon
        });
    }

    // Add optgroups for each category
    for (const [category, categoryTools] of Object.entries(categories)) {
        const optgroup = document.createElement('optgroup');

        // Use category name from categories.json if available
        const categoryInfo = categoriesData && categoriesData[category] ? categoriesData[category] : null;
        optgroup.label = categoryInfo ? categoryInfo.name : category;

        // Add tools as options
        categoryTools.forEach(tool => {
            const option = document.createElement('option');
            option.value = tool.id;
            // Add tool icon before the name
            option.textContent = `${tool.icon || '📄'} ${tool.name}`;
            optgroup.appendChild(option);
        });

        // Append optgroup to select
        toolSelect.appendChild(optgroup);
    }

    // Add tool selection handler
    toolSelect.addEventListener('change', function() {
        const toolId = this.value;
        if (toolId) {
            displayToolForm(toolId);
        } else {
            switchView('home');
        }
    });
}

/**
 * Display a tool form with comprehensive configuration handling and loading states
 * 
 * This function renders the form for a specific tool based on its configuration.
 * It handles the complete form lifecycle including validation, population with saved preferences,
 * loading states, and proper error handling. The function supports both tool-specific fields and 
 * common fields referenced by string names.
 * 
 * @param {string} toolId - The ID of the tool to display
 * @returns {Promise<void>}
 * 
 * @throws {Error} If toolId is invalid or required form elements are missing
 * @note Validates toolId and checks for required form elements before proceeding
 * @note Shows loading overlay during form population
 * @note Updates page title and subtitle with tool and category information
 * @note Retrieves user preferences from localStorage for model, language, and image size
 * @note Supports both direct field objects and string references to common fields
 * @note Creates form fields using createFormField() with proper error handling
 * @note Sets up cancel button to return to the appropriate category view
 * @note Uses smooth scrolling to ensure the form is visible to the user
 * @see createFormField() - Called to create each form field with validation
 * @see toolsData - Global object containing tool configurations
 * @see categoriesData - Global object containing category information
 * @example
 * // Display a specific tool form
 * await displayToolForm('text-extractor');
 * 
 * // Function handles missing tools gracefully
 * displayToolForm('nonexistent-tool'); // Shows error message
 */
async function displayToolForm(toolId) {
    // Validate input
    if (!toolId || typeof toolId !== 'string') {
        showError('Invalid tool ID provided');
        return;
    }

    // Use the global toolsData if available
    if (!toolsData || Object.keys(toolsData).length === 0) {
        showError('No tools data available');
        return;
    }

    // Show form loading state
    showFormLoading('Loading tool form...');

    try {
        // Get the selected tool and category information
        let tool = toolsData[toolId];
        const category = categoriesData && tool?.category ? categoriesData[tool.category] : null;

        // If tool is not found, it might not be loaded yet
        if (!tool) {
            showError(`Tool ${toolId} not found. Please try again.`);
            hideFormLoading();
            return;
        }

        // Set the tool ID in the tool object
        tool.id = toolId;

        // Update language field if present and hidden
        if (tool.form?.fields) {
            tool.form.fields.forEach(field => {
                if (field.name === 'language' && field.type === 'hidden') {
                    field.value = 'en';
                }
            });
        }

        // Update form title and description
        const formTitle = document.getElementById('formTitle');
        const formSubtitle = document.getElementById('formSubtitle');
        if (formTitle) formTitle.textContent = (tool.icon || '📄') + ' ' + (tool.name || 'Unnamed Tool');
        if (formSubtitle) formSubtitle.textContent = tool.description || '';

        // Update page title and subtitle with category info
        const pageTitle = document.getElementById('pageTitle');
        const pageSubtitle = document.getElementById('pageSubtitle');
        if (pageTitle && pageSubtitle && tool.category && categoriesData) {
            if (category) {
                pageTitle.textContent = category.icon + ' ' + category.name;
                pageSubtitle.textContent = category.description;
            }
        }

        // Populate the form fields
        const toolForm = document.getElementById('toolForm');
        const formFields = document.getElementById('formFields');
        const actionInput = document.getElementById('action');
        
        if (!toolForm || !formFields || !actionInput) {
            showError('Required form elements not found');
            hideFormLoading();
            return;
        }

        actionInput.value = tool.id;
        formFields.innerHTML = '';

        // Get preferences from localStorage
        const preferences = {
            'docmind-model': localStorage.getItem('docmind-model'),
            'docmind-language': localStorage.getItem('docmind-language'),
            'docmind-max_image_size': localStorage.getItem('docmind-max_image_size')
        };

        // Create form fields based on formConfig                                                                         
        if (tool.form?.fields) {                                                                          
            tool.form.fields.forEach(field => {                                                                       
                // If field is a string, get the field definition from common/form/fields                             
                if (typeof field === 'string') {                                                                      
                    if (!commonData?.form?.fields) { 
                        console.error('No common data available or common form fields not found');                     
                        return;                                                                                       
                    }                                                                                                 
                    const commonField = commonData.form.fields.find(f => f.name === field);                     
                    if (!commonField) {                                                                               
                        console.error(`Field "${field}" not found in common form fields`);                            
                        return;                                                                                       
                    }                                                                                                 
                    field = commonField;                                                                              
                }                                                                                                     

                const fieldElement = createFormField(field, preferences);                                                 
                if (fieldElement) {                                                                                   
                    formFields.appendChild(fieldElement);                                                             
                } else if (field.type === 'hidden') {                                                                 
                    // Create and append hidden input directly to the form                                            
                    const hiddenInput = document.createElement('input');                                              
                    hiddenInput.type = 'hidden';                                                                      
                    hiddenInput.name = field.name;                                                                    
                    hiddenInput.value = field.value || '';                                                            
                    toolForm.appendChild(hiddenInput);                                                                
                }                                                                                                     
            });                                                                                                       
        }

        // Show the form and hide results area
        showForm();

        // Set up cancel button to go back to category view
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) {
            cancelBtn.onclick = function() {
                // Get the current tool's category
                const currentTool = toolsData[toolId];
                if (currentTool?.category) {
                    switchView('tools-' + currentTool.category);
                } else {
                    // Fallback to tools view if category not found
                    switchView('tools');
                }
            };
        }

        // Scroll to form
        if (toolForm.scrollIntoView) {
            toolForm.scrollIntoView({ behavior: 'smooth' });
        }
    } catch (error) {
        showError('Failed to load tool form: ' + error.message);
    } finally {
        // Hide form loading state
        hideFormLoading();
    }
}

/**
 * Create a form field element with comprehensive type handling and dynamic loading
 * 
 * This function creates form field elements based on field configuration objects.
 * It supports multiple field types with proper validation, dynamic option loading,
 * and user preference integration. The function handles complex scenarios like
 * dynamic API fetching for select fields and proper error states.
 * 
 * @param {Object} field - The field configuration object
 * @param {string} field.name - The name attribute for the field
 * @param {string} field.type - The type of field (textarea, select, hidden, text, file, etc.)
 * @param {string} [field.label] - The label text for the field
 * @param {boolean} [field.required] - Whether the field is required
 * @param {Array} [field.options] - Options for select fields
 * @param {string} [field.value] - Default value for the field
 * @param {string} [field.placeholder] - Placeholder text for input fields
 * @param {string} [field.help] - Help text for the field
 * @param {Object} [cookies] - Object containing saved user preferences
 * @returns {HTMLElement|null} The created form field element or null if creation fails
 * 
 * @throws {Error} If field configuration is invalid or required elements are missing
 * @note Supports field types: text, textarea, select, hidden, file, and other HTML input types
 * @note Handles dynamic field loading for models (fetchModels) and prompts (fetchExpPrompts)
 * @note Integrates with global languagesData for language selection with flag icons
 * @note Applies saved user preferences from cookies for model and language fields
 * @note Creates proper HTML structure with labels, inputs, and help text
 * @note Provides loading states for dynamically populated select fields
 * @note Includes error handling for failed dynamic loading operations
 * @see fetchModels() - Called for dynamic model loading from API
 * @see fetchExpPrompts() - Called for dynamic prompt loading from API
 * @see displayToolForm() - Calls this function for each field in tool configuration
 * @see languagesData - Global object containing language data with flag icons
 * @example
 * // Create a text input field
 * const textField = createFormField({
 *     name: 'title',
 *     type: 'text',
 *     label: 'Document Title',
 *     required: true,
 *     placeholder: 'Enter document title'
 * });
 * 
 * // Create a dynamic model select field
 * const modelField = createFormField({
 *     name: 'model',
 *     type: 'select',
 *     label: 'AI Model',
 *     required: true
 * }, savedPreferences);
 */
function createFormField(field, cookies = {}) {
    // Create container div
    const container = document.createElement('div');
    container.className = 'form-field';

    // Create label if not hidden field
    if (field.type !== 'hidden') {
        const label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = field.label || field.name;
        label.htmlFor = field.name;
        container.appendChild(label);
    } else {
        // For hidden fields add hidden-field class
        container.className = 'hidden-field';
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
                fetchExpPrompts(input);
            }
            // If options are empty and field name is 'model', fetch models from API
            else if ((!field.options || field.options.length === 0) && field.name === 'model') {
                // Add a loading option
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading models...';
                input.appendChild(loadingOption);
                // Fetch models from API
                fetchModels(input, cookies);
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
async function fetchModels(selectElement, cookies = {}) {
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
async function fetchExpPrompts(selectElement) {
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
 * Handle form submission with comprehensive error handling, user feedback, and loading states
 * 
 * This function manages the complete form submission lifecycle including data collection,
 * API communication, user preference saving, loading states, and proper error handling. 
 * It provides visual feedback during processing and handles various response formats appropriately.
 * 
 * @param {Event} event - The form submission event
 * @returns {Promise<void>}
 * 
 * @throws {Error} If form validation fails or API communication encounters errors
 * @note Prevents default form submission and uses FormData API for data collection
 * @note Shows global loading overlay during API processing
 * @note Shows button loading state during API processing
 * @note Makes POST request to docmind.php with Accept: application/json header
 * @note Validates response format and handles both JSON and non-JSON responses
 * @note Saves user preferences (model, language, max_image_size) to localStorage
 * @note Handles network errors, HTTP errors, and API errors with appropriate messages
 * @note Calls displayResults() for successful API responses
 * @note Calls showError() for validation errors and API errors
 * @note Restores submit button state after processing completes
 * @see displayResults() - Called to render successful API responses
 * @see showError() - Called to display error messages to users
 * @see Document.addEventListener('DOMContentLoaded') - Sets up this handler on form elements
 * @example
 * // Form submission is automatically handled when form is submitted
 * // No direct calling needed - event handler is set up in DOMContentLoaded
 */
async function handleFormSubmit(event) {
    // Prevent default form submission
    event.preventDefault();
    
    // Get form data
    const form = event.target;
    const formData = new FormData(form);

    // Show loading states
    showGlobalLoading('Processing your request...');
    const submitBtn = document.getElementById('submitBtn');
    showLoadingState(submitBtn, 'Processing...');

    // Save preferences to localStorage
    const model = formData.get('model');
    if (model) {
        localStorage.setItem('docmind-model', model);
    }
    const language = formData.get('language');
    if (language) {
        localStorage.setItem('docmind-language', language);
    }
    const maxImageSize = formData.get('max_image_size');
    if (maxImageSize) {
        localStorage.setItem('docmind-max_image_size', maxImageSize);
    }

    try {
        const response = await fetch('docmind.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json'
            }
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
    } catch (error) {
        showError('Failed to submit form: ' + error.message);
    } finally {
        // Restore button state and hide loading
        hideLoadingState(submitBtn);
        hideGlobalLoading();
    }
}

/**
 * Display API results with comprehensive content processing and rendering
 * 
 * This function processes and renders API responses in the results area with support for
 * multiple content types and display formats. It handles complex content detection,
 * conversion, and rendering with proper error handling and user feedback.
 * 
 * @param {Object} results - The results object from the API containing response data
 * @param {boolean} [fromHistory=false] - Flag indicating if results are from history
 * @returns {void}
 * 
 * @throws {Error} If no valid response content is found or content processing fails
 * @note Extracts response content from multiple possible locations in the API response
 * @note Supports HTML, JSON, and LLM chat completion response formats
 * @note Updates results title and subtitle based on tool configuration
 * @note Detects content type using extractCodeFenceInfo() for proper processing
 * @note Renders content based on tool display preferences (html, markdown, json)
 * @note Uses marked.js for markdown to HTML conversion
 * @note Uses highlight.js for syntax highlighting of code blocks
 * @note Calls jsonToMarkdown() for JSON content conversion to markdown
 * @note Stores original raw response data for copy functionality
 * @note Automatically saves results to history unless fromHistory flag is true
 * @note Scrolls results into view with smooth scrolling for better UX
 * @see handleFormSubmit() - Calls this function on successful form submission
 * @see applySyntaxHighlighting() - Called to highlight code blocks
 * @see showError() - Called if no valid response content is found
 * @see saveResultToHistory() - Called to save results unless fromHistory
 * @example
 * // Display fresh API results
 * displayResults(apiResponse);
 * 
 * // Display results from history
 * displayResults(savedResult, true);
 */
function displayResults(results, fromHistory = false) {
    // Get results area and title elements
    const resultsArea = document.getElementById('resultsArea');
    const resultsContent = document.getElementById('resultsContent');
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsSubtitle = document.getElementById('resultsSubtitle');
    const detailsPrompt = document.getElementById('detailsPrompt');
    const detailsResponse = document.getElementById('detailsResponse');

    // Extract the actual response content from the API response
    let responseContent = '';
    
    // Check if backend returned the prompt in debug/prompt
    if (results.debug && results.debug.prompt && detailsPrompt) {
        detailsPrompt.innerHTML = `<code>${results.debug.prompt}</code>`;
    }
    
    if (results.error) {
        // If results contain an error, display it
        console.error('Error found:\n', results.error);
        showError(results.error);
        return;
    } else if (results.html) {
        // If results contain HTML, return it directly
        console.log('HTML content found:\n', results.html);
        responseContent = results.html;
    } else if (results.response && results.response.choices && results.response.choices[0] && results.response.choices[0].message && results.response.choices[0].message.content) {
        // Extract content from LLM chat completion response
        responseContent = results.response.choices[0].message.content;
        console.log('LLM response found:\n', responseContent);
    } else {
        console.error('No valid response content found');
        showError('No response content available');
        return;
    }

    // Get the current tool from results.tool
    const toolId = results.tool || '';
    const tool = toolsData[toolId] || null;

    // Update results title and subtitle
    updateResultsTitle(tool, resultsTitle, resultsSubtitle);

    // Check if the result contains markdown code fences
    const resultsInfo = extractCodeFenceInfo(responseContent, 'markdown');
    console.log('Code fence info:\n', resultsInfo);

    // Check the desired display format from tool
    const displayFormat = tool && tool.display ? tool.display.toLowerCase() : resultsInfo.type;
    console.log('Display format requested: ', displayFormat);

    // Render content based on type and format
    renderContent(resultsContent, resultsInfo, displayFormat, tool);

    // Check if resultsContent is not empty
    if (resultsContent.innerHTML.trim() !== '') {
        // Show results area
        resultsArea.style.display = 'block';
        // Switch to results view
        switchView('results');
        // Apply syntax highlighting
        applySyntaxHighlighting(resultsArea);
    } else {
        console.error('Results content is empty');
    }

    // Store the original raw response data (for copy functionality)
    resultsContent.dataset.raw = responseContent;
    if (detailsResponse) {
        detailsResponse.innerHTML = `<code>${responseContent}</code>`;
    }

    // Scroll to results
    resultsArea.scrollIntoView({ behavior: 'smooth' });

    // Save result to history only if it's not from history
    if (!fromHistory) {
        saveResultToHistory(results);
    }
}

/**
 * Update results title and subtitle based on tool
 *
 * This function updates the results title and subtitle based on the tool configuration.
 *
 * @param {Object} tool - The tool configuration object
 * @param {HTMLElement} resultsTitle - The results title element
 * @param {HTMLElement} resultsSubtitle - The results subtitle element
 * @return {void}
 *
 * @note Updates title and subtitle based on tool.form.title and tool.form.description
 * @note Called by displayResults() when rendering results
 */
function updateResultsTitle(tool, resultsTitle, resultsSubtitle) {
    if (!resultsTitle || !resultsSubtitle) return;

    if (tool && tool.form && tool.form.title) {
        resultsTitle.textContent = tool.form.title;
    } else {
        resultsTitle.textContent = '📝 Results';
    }
    if (tool && tool.form && tool.form.description) {
        resultsSubtitle.textContent = tool.form.description;
    } else {
        resultsSubtitle.textContent = 'Review the AI-generated results below. You can copy the content or download it as a file.';
    }
}

/**
 * Render content based on type and format
 *
 * This function renders content based on the content type and display format.
 *
 * @param {HTMLElement} resultsContent - The results content element
 * @param {Object} resultsInfo - The extracted code fence info
 * @param {string} displayFormat - The desired display format
 * @param {Object} tool - The tool configuration object
 * @return {void}
 *
 * @note Handles JSON, markdown, and plain text content
 * @note Uses marked.js for markdown to HTML conversion
 * @note Uses highlight.js for syntax highlighting
 * @note Calls jsonToMarkdown() for JSON content conversion
 * @see displayResults() - Calls this function for content rendering
 */
function renderContent(resultsContent, resultsInfo, displayFormat, tool) {
    if (resultsInfo.type === 'json') {
        // If the result is JSON, parse it
        try {
            const jsonData = JSON.parse(resultsInfo.text);
            console.log('Parsed JSON data:\n', jsonData);

            // HTML display format with Handlebars template
            if (displayFormat === 'html') {
                if (tool && tool.template && typeof Handlebars !== 'undefined') {
                    try {
                        // Handle both string and array templates
                        const templateContent = Array.isArray(tool.template) ? 
                            tool.template.join('\n') : 
                            tool.template;
                        const template = Handlebars.compile(templateContent);
                        resultsContent.innerHTML = template(jsonData);
                    } catch (error) {
                        console.error('Handlebars template error:', error);
                        // Fallback to markdown rendering
                        const markdownContent = jsonToMarkdown(jsonData);
                        resultsContent.innerHTML = `<div class="article">${marked.parse(markdownContent)}</div>`;
                    }
                } else {
                    // Convert JSON to HTML via markdown
                    const markdownContent = jsonToMarkdown(jsonData);
                    resultsContent.innerHTML = `<div class="article">${marked.parse(markdownContent)}</div>`;
                }
            } else if (displayFormat === 'markdown') {
                // Convert JSON to markdown
                const markdownContent = jsonToMarkdown(jsonData);
                console.log('JSON to Markdown conversion:\n', markdownContent);
                resultsContent.innerHTML = `<pre><code class="${displayFormat}">${markdownContent}</code></pre>`;
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
            resultsContent.innerHTML = `<div class="article">${marked.parse(resultsInfo.text)}</div>`;
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
}

/**
 * Show a notification message
 *
 * This function displays a notification message to the user. It supports
 * different types of notifications (success, info, warning, error).
 *
 * @param {string} message - The notification message to display
 * @param {string} type - The type of notification (success, info, warning, error)
 * @return {void}
 *
 * @note Creates a notification element and adds it to the notification container
 * @note Automatically removes the notification after 5 seconds
 * @note Uses different colors and icons based on notification type
 * @see copyResultsToClipboard() - Calls this function on successful copy
 */
function showNotification(message, type = 'info') {
    const notificationContainer = document.getElementById('notificationContainer');
    if (!notificationContainer) {
        console.error('Notification container not found');
        return;
    }

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}-notification`;

    // Set notification icon based on type
    const icons = {
        'info': 'ℹ️',
        'success': '✅',
        'warning': '⚠️',
        'error': '❌'
    };
    const icon = icons[type] || 'ℹ️';

    // Set notification content
    notification.innerHTML = `
        <div class="notification-icon">${icon}</div>
        <div class="notification-content">${message}</div>
        <button class="notification-close">×</button>
    `;

    // Add close button functionality
    const closeButton = notification.querySelector('.notification-close');
    closeButton.addEventListener('click', () => {
        notification.remove();
    });

    // Add notification to container
    notificationContainer.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification && notification.parentNode) {
            notification.remove();
        }
    }, 5000);
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
 * @see displayTools() - Calls this function if tools data is unavailable
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

    // Show results area and switch to results view
    showResults();
}

/**
 * Show the results area and switch to results view
 *
 * This function shows the results area and switches to the results view.
 *
 * @return {void}
 *
 * @note Shows the results area
 * @note Switches to results view
 * @note Scrolls to results
 * @see showError() - Calls this function when showing errors
 * @see displayResults() - Calls this function when displaying results
 */
function showResults() {
    const resultsArea = document.getElementById('resultsArea');
    if (resultsArea && resultsArea.style) {
        resultsArea.style.display = 'block';
        switchView('results');
        resultsArea.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Convert data to Markdown format with comprehensive type handling
 * 
 * This function serves as the central entry point for converting various data types
 * into Markdown format. It intelligently delegates to specialized helper functions
 * based on the data type, ensuring proper formatting for objects, arrays, and
 * primitive values. The function maintains consistent formatting across different
 * data structures.
 * 
 * @param {Object|Array|string|number|boolean} data - The data to convert to Markdown
 * @param {number} [level=3] - The heading level for objects (1-6), used for nested structures
 * @returns {string} Markdown representation of the data with proper formatting
 * 
 * @note Handles complex nested structures including objects with nested objects and arrays
 * @note Uses appropriate heading levels for nested content (automatically increments for deeper nesting)
 * @note Preserves data types and converts primitives to string representation
 * @note Delegates to specialized functions: objectToMarkdown(), arrayToMarkdown()
 * @note Maintains consistent formatting across different data structures
 * @note Skips null and undefined values to avoid empty content
 * @see objectToMarkdown() - Handles object conversion with proper heading structure
 * @see arrayToMarkdown() - Handles array conversion with list or table formatting
 * @example
 * // Convert simple object to markdown
 * const obj = { title: 'Document', pages: 10 };
 * const markdown = dataToMarkdown(obj);
 * // Returns: "### Title\n\nDocument\n\n### Pages\n\n10"
 * 
 * // Convert array to markdown list
 * const arr = ['Item 1', 'Item 2'];
 * const markdown = dataToMarkdown(arr);
 * // Returns: "- Item 1\n- Item 2"
 */
function dataToMarkdown(data, level = 3) {
    if (typeof data === 'string') {
        return data;
    } else if (Array.isArray(data)) {
        return arrayToMarkdown(data);
    } else if (typeof data === 'object' && data !== null) {
        return objectToMarkdown(data, level);
    } else {
        return String(data);
    }
}

/**
 * Convert an array to Markdown format
 * 
 * This function converts an array into a Markdown list or table, depending
 * on whether the array contains objects or primitive values.
 * 
 * @param {Array} arr - The array to convert
 * @return {string} Markdown representation of the array
 */
function arrayToMarkdown(arr) {
    if (arr.length === 0) {
        return '';
    } else if (typeof arr[0] === 'object' && arr[0] !== null) {
        return arrayOfObjectsToTable(arr);
    } else {
        return arrayToList(arr);
    }
}

/**
 * Convert JavaScript object to structured Markdown with proper heading hierarchy
 * 
 * This function transforms JavaScript objects into well-structured Markdown documents
 * with appropriate heading levels and content organization. It handles complex nested
 * structures, skips empty values, and maintains consistent formatting throughout.
 * 
 * @param {Object} obj - The JavaScript object to convert to Markdown
 * @param {number} [level=3] - The starting heading level (1-6), automatically increments for nested objects
 * @returns {string} Structured Markdown representation with proper headings and content
 * 
 * @throws {Error} If object contains circular references (handled by recursion limit)
 * @note Skips null and undefined values to avoid empty content sections
 * @note Capitalizes the first letter of each key for better readability
 * @note Uses markdown headings (#) with automatic level adjustment for nested content
 * @note Handles nested objects recursively with incremented heading levels
 * @note Handles arrays by delegating to jsonToMarkdown() for consistent formatting
 * @note Converts primitive values (numbers, booleans) to string representation
 * @note Maintains proper spacing and formatting for readability
 * @note Limits heading levels to maximum 6 (######) as per Markdown specification
 * @see jsonToMarkdown() - Used for array handling within objects
 * @see dataToMarkdown() - Central conversion function that delegates to this function
 * @example
 * // Convert nested object to markdown
 * const data = { 
 *     title: 'Analysis Report',
 *     summary: 'This is a summary',
 *     details: { 
 *         items: ['Item 1', 'Item 2'],
 *         count: 2 
 *     } 
 * };
 * const markdown = objectToMarkdown(data);
 * // Returns structured markdown with proper heading hierarchy
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
 * Switch between application views with comprehensive state management
 *
 * This function manages the complete view switching lifecycle including hiding all inactive views,
 * showing the selected view, updating navigation states, and handling special view-specific
 * actions. It provides a centralized way to manage application state and user navigation.
 *
 * @param {string} viewName - The name of the view to show (e.g., 'home', 'tools', 'history', 'form', 'results')
 * @returns {void}
 *
 * @throws {Error} If viewName is invalid or target view element is not found
 * @note Hides all views with class 'view' and shows only the selected view
 * @note Updates active state of navigation buttons based on data-view attribute
 * @note Updates page title and subtitle using updatePageTitle() function
 * @note Special handling for history view that calls displayHistory() automatically
 * @note Handles view-specific actions like loading history data when switching to history view
 * @note Maintains proper view state for mobile navigation and responsive design
 * @note Logs errors to console if view element is not found
 * @see Document.addEventListener('DOMContentLoaded') - Sets up view switching handlers on navigation buttons
 * @see updatePageTitle() - Updates page title and subtitle based on current view
 * @see displayHistory() - Called automatically when switching to history view
 * @example
 * // Switch to home view
 * switchView('home');
 * 
 * // Switch to results view (typically called after form submission)
 * switchView('results');
 * 
 * // Switch to history view (automatically loads history data)
 * switchView('history');
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
    } else {
        console.error(`View not found: ${viewName}`);
        return;
    }

    // Update active state of navigation buttons
    const navButtons = document.querySelectorAll('.nav-item');
    navButtons.forEach(button => {
        button.classList.remove('active');
        if (button.dataset.view === viewName) {
            button.classList.add('active');
        }
    });

    // Update page title and subtitle based on view
    updatePageTitle(viewName);

    // Special handling for history view
    if (viewName === 'history') {
        displayHistory();
    }
}

/**
 * Update page title and subtitle based on view
 *
 * This function updates the page title and subtitle based on the current view.
 *
 * @param {string} viewName - The name of the current view
 * @return {void}
 *
 * @note Updates pageTitle and pageSubtitle elements
 * @note Called by switchView() when changing views
 */
function updatePageTitle(viewName) {
    const pageTitle = document.getElementById('pageTitle');
    const pageSubtitle = document.getElementById('pageSubtitle');

    if (!pageTitle || !pageSubtitle) return;

    const titles = {
        'home': {
            title: '🏠 Home',
            subtitle: 'Welcome to DocMind AI - Intelligent Document Processing'
        },
        'history': {
            title: '⏳ History',
            subtitle: 'View your previous analysis sessions and results'
        },
        'settings': {
            title: '⚙️ Settings',
            subtitle: 'Configure your preferences and account settings'
        }
    };

    const viewTitle = titles[viewName];
    if (viewTitle) {
        pageTitle.textContent = viewTitle.title;
        pageSubtitle.textContent = viewTitle.subtitle;
    }
}

/**
 * Show the form view and hide results
 *
 * This function shows the form view and hides the results area.
 *
 * @return {void}
 *
 * @note Shows the tool form
 * @note Hides the results area
 * @note Called by displayToolForm() when showing a form
 */
function showForm() {
    const toolForm = document.getElementById('toolForm');
    const resultsArea = document.getElementById('resultsArea');

    if (toolForm) {
        toolForm.style.display = 'block';
    }
    if (resultsArea) {
        resultsArea.style.display = 'none';
    }

    switchView('form');
}

/**
 * Save result to localStorage
 *
 * This function saves a result to localStorage with a timestamp.
 * It maintains a maximum of 10 results, removing the oldest ones if necessary.
 *
 * @param {Object} result - The result object to save
 * @return {void}
 *
 * @note Uses localStorage to persist results between sessions
 * @note Stores results as JSON strings
 * @note Maintains a maximum of 10 results
 * @note Each result is stored with a timestamp for sorting
 * @note Stores form data, prompt, and API response for detailed history
 * @see displayResults() - Calls this function after displaying results
 */
function saveResultToHistory(result) {
    try {
        // Get existing results from localStorage
        const existingResults = JSON.parse(localStorage.getItem('docmind-results')) || [];

        // Get form data
        const form = document.getElementById('apiForm');
        const formData = form ? new FormData(form) : new FormData();

        // Create result object with timestamp and additional data
        const resultToSave = {
            id: Date.now().toString(),
            timestamp: Date.now(),
            title: result.tool ? toolsData[result.tool]?.name || 'Analysis Result' : 'Analysis Result',
            tool: result.tool || '',
            content: result,
            formData: Object.fromEntries(formData),
            prompt: result.debug?.prompt || '',
            response: result.response?.choices?.[0]?.message?.content || ''
        };

        // Add new result to the beginning of the array
        existingResults.unshift(resultToSave);

        // Keep only the 10 most recent results
        if (existingResults.length > 10) {
            existingResults.length = 10;
        }

        // Save back to localStorage
        localStorage.setItem('docmind-results', JSON.stringify(existingResults));
    } catch (error) {
        console.error('Failed to save result to history:', error);
    }
}

/**
 * Load results from localStorage with pagination and error handling
 *
 * This function retrieves saved results from localStorage with configurable pagination
 * and robust error handling. It ensures data integrity and provides sorted results
 * with the most recent items first.
 *
 * @param {number} [maxItems=10] - Maximum number of results to return (default: 10)
 * @returns {Array} Array of saved results, sorted by timestamp (newest first)
 *
 * @throws {Error} If localStorage access fails or data parsing encounters errors
 * @note Returns empty array if no results are found, data is invalid, or errors occur
 * @note Sorts results by timestamp in descending order (newest first)
 * @note Limits returned results to maxItems for performance and display purposes
 * @note Handles JSON parsing errors gracefully with try-catch blocks
 * @note Uses localStorage key 'docmind-results' for data persistence
 * @see displayHistory() - Uses this function to populate history view
 * @see saveResultToHistory() - Complementary function that saves results to localStorage
 * @example
 * // Load default number of results (10)
 * const results = loadResultsFromHistory();
 * 
 * // Load specific number of results
 * const recentResults = loadResultsFromHistory(5);
 * 
 * // Load all results (unlimited)
 * const allResults = loadResultsFromHistory(Infinity);
 */
function loadResultsFromHistory(maxItems = 10) {
    try {
        const results = JSON.parse(localStorage.getItem('docmind-results')) || [];
        // Sort by timestamp (newest first)
        const sortedResults = results.sort((a, b) => b.timestamp - a.timestamp);
        // Return only the specified number of items
        return sortedResults.slice(0, maxItems);
    } catch (error) {
        console.error('Failed to load results from history:', error);
        return [];
    }
}

/**
 * Display a specific result from history
 *
 * This function loads and displays a specific result from the history.
 *
 * @param {string} resultId - The ID of the result to display
 * @return {void}
 *
 * @note Finds the result by ID in localStorage
 * @note Displays the result using the displayResults function
 * @note Shows error if result is not found
 * @see displayHistory() - Calls this function when a history item is clicked
 */
function displayHistoryResult(resultId) {
    try {
        const results = loadResultsFromHistory();
        const result = results.find(r => r.id === resultId);

        if (result) {
            // Display the saved content with fromHistory flag
            displayResults(result.content, true);
            // Switch to results view
            switchView('results');
        } else {
            showError('Result not found in history');
        }
    } catch (error) {
        showError('Failed to load result from history: ' + error.message);
    }
}

/**
 * Load and populate form with history data
 *
 * This function loads a saved result from history and populates the form
 * with the saved form data, then displays the form.
 *
 * @param {string} resultId - The ID of the result to load
 * @return {void}
 *
 * @note Finds the result by ID in localStorage
 * @note Populates the form with saved formData
 * @note Displays the tool form
 * @note Shows error if result is not found
 * @see displayHistory() - Calls this function when "Show Form" is clicked
 */
function loadHistoryForm(resultId) {
    try {
        const results = loadResultsFromHistory();
        const result = results.find(r => r.id === resultId);

        if (result) {
            // Get the tool ID from the saved result
            const toolId = result.tool;
            if (!toolId) {
                showError('Tool ID not found in saved result');
                return;
            }

            // Display the tool form first
            displayToolForm(toolId);

            // Populate the form fields with saved data
            const savedFormData = result.formData;
            if (savedFormData && typeof savedFormData === 'object') {
                // Wait for form to be populated, then set values
                setTimeout(() => {
                    const form = document.getElementById('apiForm');
                    if (form) {
                        // Set form values from saved data
                        for (const [key, value] of Object.entries(savedFormData)) {
                            const input = form.querySelector(`[name="${key}"]`);
                            if (input) {
                                if (input.type === 'checkbox' || input.type === 'radio') {
                                    input.checked = value;
                                } else {
                                    input.value = value;
                                }
                            }
                        }
                    }
                }, 100); // Small delay to ensure form is populated
            }
        } else {
            showError('Result not found in history');
        }
    } catch (error) {
        showError('Failed to load form from history: ' + error.message);
    }
}

/**
 * Clear all saved results from history
 *
 * This function removes all saved results from localStorage and refreshes the history view.
 *
 * @return {void}
 *
 * @note Shows a confirmation dialog before clearing
 * @note Removes the 'docmind-results' item from localStorage
 * @note Refreshes the history view to show empty state
 * @see displayHistory() - Called after clearing to refresh the view
 */
function clearHistory() {
    if (confirm('Are you sure you want to clear all history? This cannot be undone.')) {
        try {
            localStorage.removeItem('docmind-results');
            // Refresh the history view
            displayHistory();
            showNotification('History cleared successfully', 'success');
        } catch (error) {
            showError('Failed to clear history: ' + error.message);
        }
    }
}

/**
 * Display history of saved results with comprehensive item rendering, interaction, and loading states
 *
 * This function populates the history view with saved results from localStorage,
 * providing a rich interactive interface for users to browse and restore previous
 * analysis sessions. It handles empty states, item rendering, user interactions,
 * and provides loading feedback during data processing.
 *
 * @param {number} [maxItems=10] - Maximum number of history items to display
 * @returns {Promise<void>}
 *
 * @throws {Error} If history content element is not found or template loading fails
 * @note Shows loading overlay during history data processing
 * @note Loads results from localStorage using loadResultsFromHistory() with pagination
 * @note Uses historyItemTemplate for consistent item rendering across the application
 * @note Populates items with tool icons, names, timestamps, and content previews
 * @note Sets up click handlers on entire items for viewing results and "Show Form" buttons
 * @note Handles missing tool data gracefully with fallback icons and names
 * @note Shows empty state message when no history is available using historyEmptyTemplate
 * @note Truncates preview text to 200 characters with ellipsis for better display
 * @note Integrates with toolsData to display accurate tool information and icons
 * @see switchView() - Calls this function when history view is selected
 * @see loadResultsFromHistory() - Loads paginated results from localStorage
 * @see displayHistoryResult() - Called when history items are clicked
 * @see loadHistoryForm() - Called when "Show Form" buttons are clicked
 * @see historyItemTemplate - Template used for rendering individual history items
 * @see historyEmptyTemplate - Template used for empty state display
 * @example
 * // Display default number of history items (10)
 * await displayHistory();
 * 
 * // Display specific number of history items
 * await displayHistory(5);
 */
async function displayHistory(maxItems = 10) {
    const historyContent = document.getElementById('historyContent');
    if (!historyContent) {
        console.error('History content element not found');
        return;
    }

    // Show loading overlay
    showGlobalLoading('Loading history...');

    try {
        // Clear existing history items
        historyContent.innerHTML = '';

        // Load results from history
        const results = loadResultsFromHistory(maxItems = maxItems);

        if (results.length === 0) {
            // Show empty state using template
            const emptyTemplate = document.getElementById('historyEmptyTemplate');
            if (emptyTemplate) {
                const emptyState = emptyTemplate.content.cloneNode(true);
                historyContent.appendChild(emptyState);
            } else {
                historyContent.innerHTML = "<p>No history yet.</p>";
            }
            return;
        }

        // Get history item template
        const template = document.getElementById('historyItemTemplate');
        if (!template) {
            console.error('History item template not found');
            return;
        }

        // Create history items using template
        results.forEach(result => {
            const clone = template.content.cloneNode(true);
            const historyItem = clone.querySelector('.history-item');
            historyItem.dataset.resultId = result.id;

            // Populate template elements
            const titleElement = clone.querySelector('.history-title-text');
            const iconElement = clone.querySelector('.history-icon');
            const dateElement = clone.querySelector('.history-date');
            const previewElement = clone.querySelector('.history-preview');

            // Format date
            const date = new Date(result.timestamp);
            const formattedDate = date.toLocaleString();
            if (dateElement) dateElement.textContent = formattedDate;
            
            if (result.tool) {
                // Get tool data from toolsData
                const tool = toolsData[result.tool];
                if (tool) {
                    iconElement.textContent = tool.icon || '📄';
                    titleElement.textContent = tool.name || result.title;
                } else {
                    iconElement.textContent = '📄';
                    titleElement.textContent = result.title;
                }
            } else {
                iconElement.textContent = '📄';
                titleElement.textContent = result.title;
            }

            // Add preview text
            if (previewElement) {
                let previewText = '';
                
                // Try to get preview from different sources
                if (result.response && result.response.choices && result.response.choices[0] && result.response.choices[0].message && result.response.choices[0].message.content) {
                    previewText = result.response.choices[0].message.content;
                } else if (result.content && result.content.response && result.content.response.choices && result.content.response.choices[0] && result.content.response.choices[0].message && result.content.response.choices[0].message.content) {
                    previewText = result.content.response.choices[0].message.content;
                } else if (result.content) {
                    previewText = String(result.content);
                } else {
                    previewText = String(result);
                }
                
                // Take first 200 characters and add ellipsis if truncated
                if (previewText.length > 200) {
                    previewText = previewText.substring(0, 200) + '...';
                }
                
                previewElement.textContent = previewText;
            }

            // Add click handler to entire item
            historyItem.addEventListener('click', () => {
                displayHistoryResult(result.id);
            });

            historyContent.appendChild(clone);
        });
    } catch (error) {
        showError('Failed to load history: ' + error.message);
    } finally {
        // Hide loading overlay
        hideGlobalLoading();
    }
}

/**
 * DocMind-specific JavaScript initialization
 *
 * This function initializes the DocMind AI application when the DOM is fully loaded.
 * It performs the following tasks:
 * 1. Loads configuration data (categories, tools, languages)
 * 2. Creates category views
 * 3. Displays available tools in the UI
 * 4. Sets up the form submission handler
 * 5. Sets up the theme toggle button
 * 6. Sets up view switching for sidebar navigation
 * 7. Sets up category buttons in the sidebar
 * 8. Applies the user's theme preference
 *
 * @note This is the main entry point for the application
 * @note Uses async/await for sequential data loading
 * @note Sets up global variables: categoriesData, toolsData, languagesData
 * @note Calls createCategoryViews() to create category view sections
 * @note Calls displayTools() to render tool cards
 * @note Attaches form submission handler to the API form
 * @note Sets up theme toggle button click handler
 * @note Sets up sidebar navigation click handlers
 * @note Sets up category button click handlers
 * @note Applies theme preference on page load
 * @see loadJSONResource() - Used to load configuration data
 * @see createCategoryViews() - Creates category view sections
 * @see displayTools() - Renders tool cards
 * @see handleFormSubmit() - Handles form submissions
 * @see toggleTheme() - Handles theme toggling
 * @see switchView() - Handles view switching
 * @see applyTheme() - Applies theme preference
 */
document.addEventListener('DOMContentLoaded', async function() {
    // Apply theme preference
    applyTheme();
    // Load config data
    configData = await loadJSONResource('config.json');
    // Load categories data
    categoriesData = configData.categories || {};
    // Load languages data
    languagesData = configData.languages || {};
    // Load tools from individual files in their categories
    toolsData = {};
    commonData = configData.common || {};

    // Load all tools from individual files
    const toolCategories = configData.tools || {};
    let totalTools = 0;
    let successfulTools = 0;

    for (const [toolId, categoryId] of Object.entries(configData.tools)) {
        totalTools++;
        const toolPath = `tools/${categoryId}/${toolId}.json`;
        try {
            const tool = await loadJSONResource(toolPath);

            // Verify tool has required properties
            if (!tool.id) {
                tool.id = toolId;
            }
            if (!tool.category) {
                tool.category = categoryId;
            }

            toolsData[toolId] = tool;
            successfulTools++;
            console.log(`✓ Loaded tool: ${toolId} (${tool.name}) from ${toolPath}`);
        } catch (error) {
            console.error(`✗ Failed to load tool ${toolId} from ${toolPath}:`, error);
        }
    }

    console.log(`Tool loading summary: ${successfulTools}/${totalTools} tools loaded successfully`);

    // Create category views and buttons after loading data
    if (categoriesData) {
        const toolSelect = document.getElementById('toolSelect');
        populateToolSelect(toolSelect);
        populateCategoryCards();
        createCategoriesViews(categoriesData);
        populateCategoriesMenu();
    }

    // Set up form submission
    document.getElementById('apiForm')?.addEventListener('submit', handleFormSubmit);
    // Set up theme toggle button
    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
    // Set up menu toggle button
    document.getElementById('menuToggle')?.addEventListener('click', toggleMenu);
    // Set up copy results button
    document.getElementById('copyResultsBtn')?.addEventListener('click', copyResultsToClipboard);
    // Set up download results button
    document.getElementById('downloadResultsBtn')?.addEventListener('click', downloadResults);
    // Set up show form button
    document.getElementById('showFormBtn')?.addEventListener('click', () => {
        switchView('form');
        document.getElementById('toolForm').scrollIntoView({ behavior: 'smooth' });
    });
    // Set up clear history button (only if it exists)
    const clearHistoryBtn = document.getElementById('clearHistoryBtn');
    if (clearHistoryBtn) {
        clearHistoryBtn.addEventListener('click', clearHistory);
    }
    // Set up view switching for sidebar navigation
    const navButtons = document.querySelectorAll('.nav-item');
    navButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Check if this is a category button with submenu
            const submenu = this.querySelector('.nav-submenu');
            if (submenu && submenu.children.length > 0) {
                e.stopPropagation();
                // Toggle the active state for this button only
                this.classList.toggle('active');

                // Close other open submenus
                navButtons.forEach(otherButton => {
                    if (otherButton !== this && otherButton.classList.contains('active')) {
                        otherButton.classList.remove('active');
                    }
                });
                return;
            }
        });
    });

    // Handle tool selection from submenus
    const subNavButtons = document.querySelectorAll('.sub-nav-item');
    subNavButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const toolId = this.dataset.toolId;
            if (toolId) {
                displayToolForm(toolId);

                // Close the parent submenu
                const parentNavItem = this.closest('.nav-item');
                if (parentNavItem) {
                    parentNavItem.classList.remove('active');
                }

                // Close sidebar on mobile after selecting a tool
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

    // Load last 3 items from history on page load
    displayHistory(3);
});
