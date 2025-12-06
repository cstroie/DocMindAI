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

// Configuration
$max_history = isset($_COOKIE['chat_history_limit']) ? intval($_COOKIE['chat_history_limit']) : 10;
$model = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['chat_model']) ? $_COOKIE['chat_model'] : $DEFAULT_TEXT_MODEL));

// Validate model selection
if (!array_key_exists($model, $AVAILABLE_MODELS)) {
    $model = $DEFAULT_TEXT_MODEL; // Default to a valid model
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
    
    // Add medical context instruction
    $medical_instruction = "You are a medical assistant. Provide accurate, helpful medical information while being cautious about giving specific medical advice. Always recommend consulting with healthcare professionals for personal medical concerns.";
    
    // Prepare messages for API
    $api_messages = [
        ['role' => 'system', 'content' => $medical_instruction]
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
        'history' => $history
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
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .chat-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .chat-history {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #fafafa;
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        .user-message {
            background-color: #e3f2fd;
            text-align: right;
        }
        .assistant-message {
            background-color: #f5f5f5;
        }
        .message-header {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        .user-message .message-header {
            color: #1976d2;
        }
        .assistant-message .message-header {
            color: #388e3c;
        }
        .input-area {
            display: flex;
            gap: 10px;
        }
        #message-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        #send-button {
            padding: 10px 20px;
            background-color: #1976d2;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        #send-button:disabled {
            background-color: #bdbdbd;
            cursor: not-allowed;
        }
        .typing-indicator {
            display: none;
            color: #1976d2;
            font-style: italic;
        }
        .config-panel {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .config-panel label {
            display: block;
            margin-bottom: 10px;
        }
        .config-panel input, .config-panel select {
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <h1>Medical Chat Assistant</h1>
        <p>This assistant provides medical information but should not replace professional medical advice.</p>
        
        <div class="config-panel">
            <label>
                History Length:
                <input type="number" id="history-limit" min="1" max="50" value="<?php echo $max_history; ?>">
            </label>
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
            <button onclick="saveConfig()">Save Settings</button>
        </div>
        
        <div class="chat-history" id="chat-history">
            <div class="message assistant-message">
                <div class="message-header">Assistant</div>
                <div class="message-content">Hello! I'm your medical assistant. How can I help you today?</div>
            </div>
        </div>
        
        <div class="typing-indicator" id="typing-indicator">
            Assistant is typing...
        </div>
        
        <div class="input-area">
            <input type="text" id="message-input" placeholder="Type your medical question here..." onkeypress="handleKeyPress(event)">
            <button id="send-button" onclick="sendMessage()">Send</button>
        </div>
    </div>

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
                // Convert common markdown to HTML
                processedContent = content
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')  // Bold
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')              // Italic
                    .replace(/`(.*?)`/g, '<code>$1</code>')            // Inline code
                    .replace(/\n/g, '<br>');                           // Line breaks
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
            const historyLimit = document.getElementById('history-limit').value;
            const model = document.getElementById('model-selector').value;
            
            // Save to cookies
            document.cookie = `chat_history_limit=${historyLimit}; path=/`;
            document.cookie = `chat_model=${model}; path=/`;
            
            alert('Settings saved! Refresh the page to apply changes.');
        }
        
        // Update form submission to include model selection
        function sendMessage() {
            const input = document.getElementById('message-input');
            const message = input.value.trim();
            const model = document.getElementById('model-selector').value;
            
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
                    model: model
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
    </script>
</body>
</html>
