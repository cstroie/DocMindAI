<?php
require_once 'common.php';

// Configuration - Load from config.php if available, otherwise use defaults
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Safe defaults
    $LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
    $LLM_API_KEY = '';
    $DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
    $LLM_API_FILTER = '/free/';
    $CHAT_HISTORY_LENGTH = 10;
}

// Create chat endpoint URL
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Fetch available models from API, filtering with configured filter
$AVAILABLE_MODELS = getAvailableModels($LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER);

// If API call fails, use default models
if (empty($AVAILABLE_MODELS)) {
    $AVAILABLE_MODELS = [
        'gemma3:1b' => 'Gemma 3 (1B)',
        'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
    ];
}

// Set default model if not defined in config
if (!isset($DEFAULT_TEXT_MODEL)) {
    $DEFAULT_TEXT_MODEL = !empty($AVAILABLE_MODELS) ? array_keys($AVAILABLE_MODELS)[0] : 'qwen2.5:1.5b';
}

// Set default history length if not defined in config
if (!isset($CHAT_HISTORY_LENGTH)) {
    $CHAT_HISTORY_LENGTH = 10;
}

// Define available AI personalities
$AVAILABLE_PERSONALITIES = [
    'medical_assistant' => 'Medical Assistant',
    'general_practitioner' => 'General Practitioner',
    'specialist' => 'Medical Specialist',
    'medical_researcher' => 'Medical Researcher',
    'skippy' => 'Skippy the Magnificent'
];

// Configuration
$max_history = $CHAT_HISTORY_LENGTH;
$model = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['chat_model']) ? $_COOKIE['chat_model'] : $DEFAULT_TEXT_MODEL));
$language = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['chat_language']) ? $_COOKIE['chat_language'] : 'en'));
$personality = isset($_POST['personality']) ? $_POST['personality'] : (isset($_GET['personality']) ? $_GET['personality'] : (isset($_COOKIE['chat_personality']) ? $_COOKIE['chat_personality'] : 'medical_assistant'));

// Validate model selection
if (!array_key_exists($model, $AVAILABLE_MODELS)) {
    $model = $DEFAULT_TEXT_MODEL; // Default to a valid model
}

// Validate language selection
if (!array_key_exists($language, $AVAILABLE_LANGUAGES)) {
    $language = 'en'; // Default to English
}

// Validate personality selection
if (!array_key_exists($personality, $AVAILABLE_PERSONALITIES)) {
    $personality = 'medical_assistant'; // Default to medical assistant
}

// Check if this is an API request
$is_api_request = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || 
                  (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
$is_post_request = $_SERVER['REQUEST_METHOD'] === 'POST';

// Handle POST requests (chat messages)
if ($is_post_request) {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $message = isset($data['message']) ? trim($data['message']) : '';
    $history = isset($data['history']) ? $data['history'] : [];
    
    // Validate input
    if (empty($message)) {
        sendJsonResponse(['error' => 'Message is required'], true);
        exit;
    }
    
    // Process input
    $processed = processInput($message);
    if (!$processed['valid']) {
        sendJsonResponse(['error' => $processed['error']], true);
        exit;
    }
    
    // Limit history to max_history
    if (count($history) > $max_history * 2) { // *2 because each exchange has user + assistant
        $history = array_slice($history, -($max_history * 2));
    }
    
    // Add user message to history
    $history[] = ['role' => 'user', 'content' => $message];
    
    // Prepare API call
    $api_key = $LLM_API_KEY;
    $api_endpoint = $LLM_API_ENDPOINT;
    
    // Get personality instruction
    $personality_instruction = getPersonalityInstruction($personality, $language);
    
    // Add context instruction
    $context_instruction = $personality_instruction . " " . getLanguageInstruction($language);
    
    // Prepare messages for API
    $api_messages = [
        ['role' => 'system', 'content' => $context_instruction]
    ];
    
    // Add history
    foreach ($history as $msg) {
        $api_messages[] = $msg;
    }
    
    // Prepare API data
    $api_data = [
        'model' => $model,
        'messages' => $api_messages,
        'temperature' => 0.7
    ];
    
    // Call LLM API
    $response = callLLMApi($api_endpoint . '/chat/completions', $api_data, $api_key);
    
    if (isset($response['error'])) {
        sendJsonResponse(['error' => $response['error']], true);
        exit;
    }
    
    // Extract response content
    $reply = '';
    if (isset($response['choices'][0]['message']['content'])) {
        $reply = $response['choices'][0]['message']['content'];
    }
    
    // Add assistant response to history
    $history[] = ['role' => 'assistant', 'content' => $reply];
    
    // Return response
    sendJsonResponse([
        'reply' => $reply,
        'history' => $history,
        'model' => $model,
        'language' => $language,
        'personality' => $personality
    ], true);
    exit;
}

// For GET requests, show the chat interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Chat Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <article class="chat-container">
        <hgroup>
            <h1>Medical Chat Assistant</h1>
            <p>This assistant provides medical information but should not replace professional medical advice.</p>
        </hgroup>
        
        <main>
            <section class="config-panel">
                <header>
                    <h2>Configuration</h2>
                </header>
                <fieldset>
                    <label>
                        Model:
                        <select id="model-selector" name="model">
                            <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($model === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Personality:
                        <select id="personality-selector" name="personality">
                            <?php foreach ($AVAILABLE_PERSONALITIES as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($personality === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Language:
                        <select id="language-selector" name="language">
                            <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($language === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </fieldset>
                <button onclick="saveConfig()">Save Settings</button>
            </section>
            
            <section class="chat-history" id="chat-history">
                <div class="message assistant-message">
                    <div class="message-header">Assistant</div>
                    <div class="message-content">Hello! I'm your medical assistant. How can I help you today?</div>
                </div>
            </section>
            
            <div class="typing-indicator" id="typing-indicator">
                Assistant is typing...
            </div>
            
            <footer>
                <div class="input-area">
                    <input type="text" id="message-input" placeholder="Type your medical question here..." onkeypress="handleKeyPress(event)">
                    <button id="send-button" onclick="sendMessage()">Send</button>
                </div>
            </footer>
        </main>
    </article>

    <script>
        let chatHistory = [];
        
        function handleKeyPress(event) {
            if (event.key === 'Enter') {
                sendMessage();
            }
        }
        
        function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            
            if (!message) return;
            
            // Add user message to UI
            addMessageToUI('user', message);
            
            // Clear input
            input.value = '';
            
            // Show typing indicator
            document.getElementById('typing-indicator').style.display = 'block';
            document.getElementById('send-button').disabled = true;
            
            // Send message to server
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    history: chatHistory
                })
            })
            .then(response => response.json())
            .then(data => {
                // Hide typing indicator
                document.getElementById('typing-indicator').style.display = 'none';
                document.getElementById('send-button').disabled = false;
                
                if (data.error) {
                    addMessageToUI('assistant', 'Error: ' + data.error);
                } else {
                    // Add assistant response to UI
                    addMessageToUI('assistant', data.reply);
                    
                    // Update chat history
                    chatHistory = data.history;
                }
            })
            .catch(error => {
                // Hide typing indicator
                document.getElementById('typing-indicator').style.display = 'none';
                document.getElementById('send-button').disabled = false;
                
                addMessageToUI('assistant', 'Error: ' + error.message);
            });
        }
        
        function addMessageToUI(role, content) {
            const historyDiv = document.getElementById('chat-history');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${role}-message`;
            
            const roleLabel = role === 'user' ? 'You' : 'Assistant';
            
            // For assistant messages, we'll process markdown
            let processedContent = content;
            if (role === 'assistant') {
                // Escape HTML entities first
                processedContent = content
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
                
                // Apply markdown parsing
                processedContent = processedContent
                    .replace(/\n/g, '<br>')
                    .replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/___(.*?)___/g, '<strong><em>$1</em></strong>')
                    .replace(/__(.*?)__/g, '<strong>$1</strong>')
                    .replace(/_(.*?)_/g, '<em>$1</em>')
                    .replace(/`(.*?)`/g, '<code>$1</code>')
                    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
            }
            
            messageDiv.innerHTML = `
                <div class="message-header">${roleLabel}</div>
                <div class="message-content">${processedContent}</div>
            `;
            
            historyDiv.appendChild(messageDiv);
            
            // Scroll to bottom
            historyDiv.scrollTop = historyDiv.scrollHeight;
        }
        
        function saveConfig() {
            const model = document.getElementById('model-selector').value;
            const personality = document.getElementById('personality-selector').value;
            const language = document.getElementById('language-selector').value;
            
            // Save to cookies
            document.cookie = `chat_model=${model}; path=/`;
            document.cookie = `chat_personality=${personality}; path=/`;
            document.cookie = `chat_language=${language}; path=/`;
            
            alert('Settings saved! Refresh the page to apply changes.');
        }
        
        // Update form submission to include model, personality, and language selection
        function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            const model = document.getElementById('model-selector').value;
            const personality = document.getElementById('personality-selector').value;
            const language = document.getElementById('language-selector').value;
            
            if (!message) return;
            
            // Add user message to UI
            addMessageToUI('user', message);
            
            // Clear input
            input.value = '';
            
            // Show typing indicator
            document.getElementById('typing-indicator').style.display = 'block';
            document.getElementById('send-button').disabled = true;
            
            // Send message to server
            fetch('chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    history: chatHistory,
                    model: model,
                    personality: personality,
                    language: language
                })
            })
            .then(response => response.json())
            .then(data => {
                // Hide typing indicator
                document.getElementById('typing-indicator').style.display = 'none';
                document.getElementById('send-button').disabled = false;
                
                if (data.error) {
                    addMessageToUI('assistant', 'Error: ' + data.error);
                } else {
                    // Add assistant response to UI
                    addMessageToUI('assistant', data.reply);
                    
                    // Update chat history
                    chatHistory = data.history;
                    
                    // Update model, personality, and language selectors if changed
                    if (data.model) {
                        document.getElementById('model-selector').value = data.model;
                    }
                    if (data.personality) {
                        document.getElementById('personality-selector').value = data.personality;
                    }
                    if (data.language) {
                        document.getElementById('language-selector').value = data.language;
                    }
                }
            })
            .catch(error => {
                // Hide typing indicator
                document.getElementById('typing-indicator').style.display = 'none';
                document.getElementById('send-button').disabled = false;
                
                addMessageToUI('assistant', 'Error: ' + error.message);
            });
        }
    </script>
</body>
</html>
