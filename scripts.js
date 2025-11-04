// Save selected model to localStorage when form is submitted
function saveSelectedModel() {
    const modelSelect = document.getElementById('model');
    if (modelSelect) {
        localStorage.setItem('lastSelectedModel', modelSelect.value);
    }
}

// Restore last selected model from localStorage
function restoreSelectedModel() {
    const modelSelect = document.getElementById('model');
    if (modelSelect) {
        const lastModel = localStorage.getItem('lastSelectedModel');
        if (lastModel) {
            // Check if the saved model is still available in the dropdown
            const optionExists = Array.from(modelSelect.options).some(option => option.value === lastModel);
            if (optionExists) {
                modelSelect.value = lastModel;
            }
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    restoreSelectedModel();
    
    // Add event listener to form submit
    const form = document.getElementById('analysisForm') || document.getElementById('ocrForm');
    if (form) {
        form.addEventListener('submit', saveSelectedModel);
    }
});
