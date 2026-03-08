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
 * Apply theme based on user preference
 *
 * This function applies the theme based on user preference stored in cookies
 * or system preference. It updates the theme toggle button icon accordingly
 * and sets the data-theme attribute on the HTML element.
 *
 * @return {void}
 *
 * @note Reads theme preference from docmind-theme cookie
 * @note Falls back to system preference if no cookie is set
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
 * Load JSON resource from file
 * 
 * This function fetches and parses a JSON configuration file from the server.
 * It handles errors gracefully and can extract a specific root key from the
 * JSON data if provided.
 * 
 * @param {string} filename - The JSON file to load (e.g., 'config.json')
 * @param {string} rootKey - The root key to extract from the JSON data (e.g., 'tools')
 * @returns {Promise<Object|null>} Promise resolving to the extracted data or null on error
 * 
 * @note Uses the Fetch API to retrieve the JSON file
 * @note Handles network errors and JSON parsing errors
 * @note If rootKey is provided, returns data[rootKey] if it exists, otherwise returns entire data
 * @note Logs errors to console for debugging
 * @note Used for loading tools, languages, categories, and prompts
 * @see displayTools() - Uses this to load tools data
 * @see populateToolSelect() - Uses this to load tools data
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
    // Get the category tools grid
    const toolsGrid = document.getElementById(`${category}ToolsGrid`);
    if (!toolsGrid) {
        console.error(`Category tools grid not found for category: ${category}`);
        return;
    }

    toolsGrid.innerHTML = '';

    // Check if toolsData is available
    if (!toolsData) {
        console.error('No tools data available');
        return;
    }

    // Filter tools by category
    const categoryTools = [];
    for (const [tool_id, tool_data] of Object.entries(toolsData)) {
        if (tool_data.category === category) {
            categoryTools.push({
                'id': tool_id,
                'name': tool_data.name,
                'description': tool_data.description,
                'icon': tool_data.icon
            });
        }
    }

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

        if (iconElement) iconElement.textContent = tool.icon || '📄';
        if (titleElement) titleElement.textContent = tool.name;
        if (descriptionElement) descriptionElement.textContent = tool.description || '';

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
 * Display a tool form with the given configuration
 * 
 * This function renders the form for a specific tool. It:
 * 1. Updates the top title and description with tool information
 * 2. Sets up the form action input with the tool ID
 * 3. Retrieves saved preferences from cookies
 * 4. Creates form fields based on the tool configuration
 * 5. Shows the form and hides the results area
 * 6. Scrolls to the form for better UX
 * 
 * @param {Object} tool - The tool configuration object
 * @return {void}
 * 
 * @note Uses the tool's form.fields array to create input elements
 * @note Retrieves saved model and language preferences from cookies
 * @note Hides the results area when displaying a new form
 * @note Uses smooth scrolling to bring the form into view
 * @see createFormField() - Called to create each form field
 */
function displayToolForm(toolId) {
    // Clear the page
    //document.querySelector('.tool-selector').style.display = 'none';
    // Use the global toolsData if available
    if (!toolsData) {
        showError('No tools data available');
        return;
    }

    // Get the selected tool and category information
    let tool = toolsData[toolId];
    const category = categoriesData[tool.category];

    // If tool is not found, it might not be loaded yet
    if (!tool) {
        showError(`Tool ${toolId} not found. Please try again.`);
        return;
    }

    // Set the tool ID in the tool object
    tool.id = toolId;

    // Update language field if present and hidden
    if (tool.form.fields) {
        tool.form.fields.forEach(field => {
            if (field.name === 'language' && field.type === 'hidden') {
                field.value = 'en';
            }
        });
    }

    // Update form title and description
    const formTitle = document.getElementById('formTitle');
    const formSubtitle = document.getElementById('formSubtitle');
    if (formTitle) formTitle.textContent = tool.icon + ' ' + tool.name;
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
    actionInput.value = tool.id;

    formFields.innerHTML = '';

    // Get preferences from localStorage
    const savedModel = localStorage.getItem('docmind-model');
    const savedLanguage = localStorage.getItem('docmind-language');
    const savedMaxImageSize = localStorage.getItem('docmind-max_image_size');

    // Create cookies object for compatibility (TODO: Remove after full transition)
    const cookies = {
        'docmind-model': savedModel,
        'docmind-language': savedLanguage,
        'docmind-max_image_size': savedMaxImageSize
    };

    // Create form fields based on formConfig                                                                         
    if (tool.form && tool.form.fields) {                                                                          
        tool.form.fields.forEach(field => {                                                                       
            // If field is a string, get the field definition from common/form/fields                             
            if (typeof field === 'string') {                                                                      
                if (!commonData || !commonData.form || !commonData.form.fields) { 
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

            const fieldElement = createFormField(field, cookies);                                                 
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
    try {
        if (toolForm.style) toolForm.style.display = 'block';
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
    if (toolForm.scrollIntoView) {
        toolForm.scrollIntoView({ behavior: 'smooth' });
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
 * @see fetchModels() - Called for dynamic model loading
 * @see fetchExpPrompts() - Called for dynamic prompt loading
 * @see displayToolForm() - Calls this function for each field
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
    // Prevent default form submission
    event.preventDefault();
    // Get form data
    const form = event.target;
    const formData = new FormData(form);

    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading"></span> Processing...';

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
 * 2. Updates the results title and description based on the tool
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
 * @note Supports tool-specific display formats (markdown, html, json)
 * @note Uses marked.js for markdown to HTML conversion
 * @note Uses highlight.js for syntax highlighting
 * @note Calls jsonToMarkdown() for JSON content conversion
 * @see handleFormSubmit() - Calls this function on successful form submission
 * @see applySyntaxHighlighting() - Called to highlight code blocks
 * @see showError() - Called if no valid response content is found
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
    
    // Store the original form data for details view
    let currentResponse = '';
    
    // Check if backend returned the prompt in debug/prompt
    if (results.debug && results.debug.prompt && detailsPrompt) {
        detailsPrompt.textContent = results.debug.prompt;
    }
    
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

    // Update page title and subtitle if tool has form.title and form.description
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

    // Check if the result contains markdown code fences
    const resultsInfo = extractCodeFenceInfo(responseContent, 'markdown');
    console.log('Code fence info:\n', resultsInfo);

    // Check the desired display format from tool
    const displayFormat = tool && tool.display ? tool.display.toLowerCase() : resultsInfo.type;
    console.log('Display format requested: ', displayFormat);

    // Check if the response need conversion based on resultsInfo format and display format
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

    // Store the original raw response data (for copy functionality)
    resultsContent.dataset.raw = responseContent;
    if (detailsResponse) {
        detailsResponse.textContent = responseContent;
    }

    // Scroll to results
    resultsArea.scrollIntoView({ behavior: 'smooth' });

    // Save result to history only if it's not from history
    if (!fromHistory) {
        saveResultToHistory(results);
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
    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    else if (type === 'warning') icon = '⚠️';
    else if (type === 'error') icon = '❌';

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
 * Convert data to Markdown format
 * 
 * This function acts as a central handler for converting various data types
 * (objects, arrays, primitives) into Markdown format. It delegates specific
 * conversions to helper functions based on the data type.
 * 
 * @param {Object|Array|string} data - The data to convert
 * @param {number} [level=3] - The heading level for objects (1-6)
 * @return {string} Markdown representation of the data
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
 * Convert an object to Markdown headings and paragraphs
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
 * Switch between different views in the application
 *
 * This function handles view switching by hiding all views and showing
 * the selected view. It also updates the active state of navigation buttons.
 *
 * @param {string} viewName - The name of the view to show (e.g., 'home', 'tools', 'history')
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
    const pageTitle = document.getElementById('pageTitle');
    const pageSubtitle = document.getElementById('pageSubtitle');

    if (pageTitle && pageSubtitle) {
        switch (viewName) {
            case 'home':
                pageTitle.textContent = '🏠 Home';
                pageSubtitle.textContent = 'Welcome to DocMind AI - Intelligent Document Processing';
                break;
            case 'history':
                pageTitle.textContent = '⏳ History';
                pageSubtitle.textContent = 'View your previous analysis sessions and results';
                // Load and display history when switching to history view
                displayHistory();
                break;
            case 'settings':
                pageTitle.textContent = '⚙️ Settings';
                pageSubtitle.textContent = 'Configure your preferences and account settings';
                break;
            default:
                // Don't change title for other views (tools, form, results)
                break;
        }
    }
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
 * @see displayResults() - Calls this function after displaying results
 */
function saveResultToHistory(result) {
    try {
        // Get existing results from localStorage
        const existingResults = JSON.parse(localStorage.getItem('docmind-results')) || [];

        // Create result object with timestamp
        const resultToSave = {
            id: Date.now().toString(),
            timestamp: Date.now(),
            title: result.tool ? toolsData[result.tool]?.name || 'Analysis Result' : 'Analysis Result',
            tool: result.tool || '',
            content: result
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
 * Load results from localStorage
 *
 * This function loads saved results from localStorage and returns them.
 *
 * @return {Array} Array of saved results, sorted by timestamp (newest first)
 *
 * @note Returns empty array if no results are found or on error
 * @note Sorts results by timestamp (newest first)
 * @see displayHistory() - Uses this function to show history
 */
function loadResultsFromHistory() {
    try {
        const results = JSON.parse(localStorage.getItem('docmind-results')) || [];
        // Sort by timestamp (newest first)
        return results.sort((a, b) => b.timestamp - a.timestamp);
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
 * Toggle details section visibility
 *
 * This function shows or hides the details section and fills it with
 * the current prompt and response data.
 *
 * @return {void}
 *
 * @note Toggles the display of detailsSection
 * @see displayResults() - Stores prompt and response data
 */
function toggleDetails() {
    const detailsSection = document.getElementById('detailsSection');
    
    if (!detailsSection) {
        console.error('Details section elements not found');
        return;
    }
    
    if (detailsSection.style.display === 'none' || !detailsSection.style.display) {
        // Show details section
        detailsSection.style.display = 'block';
        document.getElementById('detailsBtn').textContent = 'Hide Details';
    } else {
        // Hide details section
        detailsSection.style.display = 'none';
        document.getElementById('detailsBtn').textContent = 'Details';
    }
}

/**
 * Display history of saved results
 *
 * This function populates the history view with saved results from localStorage.
 * It creates clickable history items that allow users to view previous results.
 *
 * @return {void}
 *
 * @note Loads results from localStorage using loadResultsFromHistory()
 * @note Creates history items with timestamps and tool names
 * @note Sets up click handlers to display specific results
 * @note Shows a message if no history is available
 * @see switchView() - Calls this function when history view is selected
 */
function displayHistory() {
    const historyList = document.querySelector('.history-list');
    if (!historyList) {
        console.error('History list element not found');
        return;
    }

    // Clear existing history items
    historyList.innerHTML = '';

    // Load results from history
    const results = loadResultsFromHistory();

    if (results.length === 0) {
        // Show empty state using template
        const emptyTemplate = document.getElementById('historyEmptyTemplate');
        if (emptyTemplate) {
            const emptyState = emptyTemplate.content.cloneNode(true);
            historyList.appendChild(emptyState);
        } else {
            // Fallback if template not found
            const emptyState = document.createElement('section');
            emptyState.className = 'history-empty-state';
            emptyState.innerHTML = `
                <hgroup>
                    <h3 class="empty-state-title"><span class="empty-state-icon">⏳</span> No history yet</h3>
                    <p class="empty-state-description">Your analysis history will appear here</p>
                </hgroup>
            `;
            historyList.appendChild(emptyState);
        }
        return;
    }

    // Create history items
    results.forEach(result => {
        const historyItem = document.createElement('section');
        historyItem.className = 'history-item';
        historyItem.dataset.resultId = result.id;

        // Format date
        const date = new Date(result.timestamp);
        const formattedDate = date.toLocaleString();

        historyItem.innerHTML = `
            <hgroup class="history-info">
                <h3 class="history-title"><span class="history-icon">⏳</span> ${result.title}</h3>
                <p class="history-date">${formattedDate}</p>
                ${result.tool ? `<p class="history-tool">Tool: ${result.tool}</p>` : ''}
            </hgroup>
            <footer>
                <button class="btn btn-small history-view-btn">View</button>
            </footer>
        `;

        // Add click handler to view button
        const viewButton = historyItem.querySelector('.history-view-btn');
        viewButton.addEventListener('click', (e) => {
            e.stopPropagation();
            displayHistoryResult(result.id);
        });

        // Add click handler to entire item
        historyItem.addEventListener('click', () => {
            displayHistoryResult(result.id);
        });

        historyList.appendChild(historyItem);
    });
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
    // Set up details button
    const detailsBtn = document.getElementById('detailsBtn');
    if (detailsBtn) {
        detailsBtn.addEventListener('click', toggleDetails);
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
});
