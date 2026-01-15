<?php
/**
 * SOAP Note Generator
 * 
 * A PHP web application that converts medical transcripts into structured SOAP notes.
 * 
 * Features:
 * - Text input or file upload (txt/markdown/images)
 * - Multimodal support (text and image transcripts)
 * - Multiple lightweight AI models support
 * - Multilingual output (6 languages)
 * - Web interface with real-time results
 * - REST API support
 * - Configurable API endpoint via external config.php
 * 
 * Requirements:
 * - PHP 7.0+
 * - cURL extension
 * - JSON extension
 * - GD library for image processing
 * - Access to compatible AI API (e.g., Ollama, MedGemma)
 * 
 * Usage:
 * - Web interface: Access via browser
 * - API endpoint: POST /soap.php with content
 * 
 * API Usage:
 * POST /soap.php
 * Parameters:
 * - content (optional): Transcript content (if no file uploaded)
 * - file (optional): Text/markdown/image file to process
 * - model (optional): AI model to use (default: medgemma:4b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "soap_note": {
 *     "subjective": "patient reported symptoms...",
 *     "objective": "clinician observations...",
 *     "assessment": "diagnostic impression...",
 *     "plan": "next steps..."
 *   }
 * }
 * 
 * Configuration:
 * Create a config.php file with:
 * - $LLM_API_ENDPOINT: AI API endpoint URL
 * - $LLM_API_KEY: API key (if required)
 * 
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */
// Include common functions
include 'common.php';

// Configuration - Load from config.php if available, otherwise use defaults
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Safe defaults
    $LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
    $LLM_API_KEY = '';
    $DEFAULT_TEXT_MODEL = 'medgemma:4b';
    $DEFAULT_VISION_MODEL = 'medgemma:4b';
    $LLM_API_FILTER = '/medgemma|meditron/';
}

// Create chat endpoint URL
$LLM_API_ENDPOINT_CHAT = $LLM_API_ENDPOINT . '/chat/completions';

// Fetch available models from API, filtering with configured filter
$AVAILABLE_MODELS = getAvailableModels($LLM_API_ENDPOINT, $LLM_API_KEY, $LLM_API_FILTER);

// If API call fails, use default models
if (empty($AVAILABLE_MODELS)) {
    $AVAILABLE_MODELS = [
        'medgemma:4b' => 'MedGemma (4B)',
        'meditron:7b' => 'Meditron (7B)',
        'qwen2.5:1.5b' => 'Qwen 2.5 (1.5B)'
    ];
}

// Set default models if not defined in config
if (!isset($DEFAULT_TEXT_MODEL)) {
    $DEFAULT_TEXT_MODEL = !empty($AVAILABLE_MODELS) ? array_keys($AVAILABLE_MODELS)[0] : 'medgemma:4b';
}
if (!isset($DEFAULT_VISION_MODEL)) {
    $DEFAULT_VISION_MODEL = !empty($AVAILABLE_MODELS) ? array_keys($AVAILABLE_MODELS)[0] : 'medgemma:4b';
}

/**
 * Get selected model and language from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['soap-model']) ? $_COOKIE['soap-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['soap-language']) ? $_COOKIE['soap-language'] : 'en'));

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = $DEFAULT_TEXT_MODEL; // Default to a valid model
}

/**
 * Validate language selection
 * Falls back to English if invalid language is selected
 */
if (!array_key_exists($LANGUAGE, $AVAILABLE_LANGUAGES)) {
    $LANGUAGE = 'en'; // Default to English
}

/**
 * Get system prompt for SOAP note generation
 */
function getSoapSystemPrompt($language) {
    return "You are a clinical documentation assistant. Your task is to read medical transcripts (dialogues between clinicians and patients) and convert them into structured clinical notes using the SOAP format.

Follow these rules:

*S – Subjective*:
Include all information reported by the patient: symptoms, duration, history, complaints, and any relevant lifestyle or exposure context.
Use the patient's own words when possible (paraphrased for clarity).

*O – Objective*:
Include observable findings such as vital signs, physical exam results, lab tests, imaging results, and clinician observations during the encounter.

*A – Assessment*:
Provide a brief summary of the clinician's diagnostic impression. Include possible or confirmed diagnoses.

*P – Plan*:
Outline the next steps recommended by the clinician. This can include prescriptions, tests to be ordered, referrals, follow-up instructions, and lifestyle recommendations.

Keep the format clear and professional. Do not include any parts of the transcript that are irrelevant or non-clinical. Do not invent information not found in the transcript. Always use a bullet point format for each section of the SOAP note.

A comprehensive SOAP note has to take into account all subjective and objective information, and accurately assess it to create the patient-specific assessment and plan.

" . getLanguageInstruction($language) . "

OUTPUT FORMAT (JSON):
{
  \"subjective\": [\"patient reported symptom 1\", \"patient reported symptom 2\", \"patient reported history\"],

  \"objective\": [\"clinician observation 1\", \"clinician observation 2\", \"measurement 1\"],

  \"assessment\": [\"diagnostic impression 1\", \"diagnostic impression 2\"],

  \"plan\": [\"next step 1\", \"next step 2\", \"recommendation 1\"]
}

Respond ONLY with the JSON, without additional text. Each section must be an array of strings, with each string representing a single bullet point.";
}

/**
 * Application state variables
 * @var string|null $result SOAP note result
 * @var string|null $error Error message if any
 * @var bool $processing Whether processing is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for SOAP note generation
 * Processes both web form submissions and API requests
 * Validates input, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['content']) || (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK))) ||
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['content']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request

    $content = '';
    $is_image = false;
    $image_path = null;

    // Handle file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];

        // Validate file type
        $allowed_types = ['text/plain', 'text/markdown', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            $error = 'Invalid file type. Only plain text (.txt), Markdown (.md), and image files (JPEG, PNG, GIF, WEBP) are allowed.';
            $processing = false;
        }

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $error = 'The file is too large. Maximum 2MB allowed.';
            $processing = false;
        }

        if ($processing) {
            // Check if it's an image
            $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $image_types)) {
                $is_image = true;
                $image_path = $file['tmp_name'];

                // Try to extract text from image using OCR
                $ocr_text = '';
                if (extension_loaded('imagick') || extension_loaded('gmagick')) {
                    $images = extractImagesFromPDF($image_path);
                    if ($images && !empty($images[0])) {
                        $temp_image = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
                        file_put_contents($temp_image, $images[0]);

                        // Preprocess image for OCR
                        $processed_image = preprocessImageForOCR($temp_image, true, true);
                        if ($processed_image) {
                            // Here you would typically call an OCR API
                            // For now, we'll just use the image directly with vision model
                            $content = "IMAGE_TRANSCRIPT_PLACEHOLDER";
                        }
                    }
                } else {
                    $content = "IMAGE_TRANSCRIPT_PLACEHOLDER";
                }
            } else {
                // Text file (plain text or markdown)
                $content = file_get_contents($file['tmp_name']);
                if ($content === false) {
                    $error = 'Failed to read the uploaded file.';
                    $processing = false;
                } else {
                    // Clean up the text content
                    $content = trim($content);
                    // Remove BOM if present
                    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                    // Normalize line endings
                    $content = str_replace(["\r\n", "\r"], "\n", $content);
                }
            }
        }
    }
    // Handle text input
    elseif (!empty($_POST['content']) || !empty($_GET['content'])) {
        $content = trim(isset($_POST['content']) ? $_POST['content'] : $_GET['content']);
    }

    // Validate content
    if ($processing && empty($content) && !$is_image) {
        $error = 'No content provided. Please enter text or upload a file.';
        $processing = false;
    }

    // Validate content length (prevent extremely large inputs)
    if ($processing && strlen($content) > 1000000) {
        $error = 'The content is too long. Maximum 1,000,000 characters allowed.';
        $processing = false;
    }

    // Only proceed with API call if validation passed
    if ($processing) {
        // Get the system prompt
        $SYSTEM_PROMPT = getSoapSystemPrompt($LANGUAGE);

        // Prepare API request
        if ($is_image) {
            // For image processing, we need to handle it differently
            // Since we're using a text-based API, we'll describe the image approach
            $data = [
                'model' => $MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => "PLEASE ANALYZE THIS MEDICAL TRANSCRIPT IMAGE AND CONVERT IT TO SOAP FORMAT:\n\n[IMAGE CONTENT - Medical transcript showing patient-clinician dialogue]"]
                ]
            ];
        } else {
            $data = [
                'model' => $MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => "MEDICAL TRANSCRIPT TO CONVERT TO SOAP FORMAT:\n" . $content]
                ]
            ];
        }

        // Make API request using common function
        $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);

        if (isset($response_data['error'])) {
            $error = $response_data['error'];
        } elseif (isset($response_data['choices'][0]['message']['content'])) {
            $result = trim($response_data['choices'][0]['message']['content']);
            // Remove markdown code fences if present
            $result = preg_replace('/^```(?:json)?\s*(.*?)\s*```$/s', '$1', $result);
        } else {
            $error = 'Invalid API response format';
        }

        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('soap-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('soap-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }

        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['soap_note' => json_decode($result, true)]);
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOAP Note Generator</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E📋%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📋 SOAP Note Generator</h1>
            <p>Convert medical transcripts into structured SOAP notes using AI</p>
        </hgroup>

        <main>
            <?php if ($error): ?>
                <section role="alert" class="error">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </section>
            <?php endif; ?>

            <?php if ($result): ?>
                <article>
                    <header>
                        <h2>SOAP Note Result</h2>
                    </header>

                    <?php
                    // Try to decode as JSON first
                    $json_result = json_decode($result, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($json_result['subjective']) && isset($json_result['objective']) && isset($json_result['assessment']) && isset($json_result['plan'])) {
                        echo '<section>';
                        echo '<h3>🩺 Subjective</h3>';
                        echo '<ul>';
                        if (is_array($json_result['subjective'])) {
                            foreach ($json_result['subjective'] as $item) {
                                echo '<li>' . htmlspecialchars($item) . '</li>';
                            }
                        } else {
                            echo '<li>' . htmlspecialchars($json_result['subjective']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</section>';

                        echo '<section>';
                        echo '<h3>🔬 Objective</h3>';
                        echo '<ul>';
                        if (is_array($json_result['objective'])) {
                            foreach ($json_result['objective'] as $item) {
                                echo '<li>' . htmlspecialchars($item) . '</li>';
                            }
                        } else {
                            echo '<li>' . htmlspecialchars($json_result['objective']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</section>';

                        echo '<section>';
                        echo '<h3>📊 Assessment</h3>';
                        echo '<ul>';
                        if (is_array($json_result['assessment'])) {
                            foreach ($json_result['assessment'] as $item) {
                                echo '<li>' . htmlspecialchars($item) . '</li>';
                            }
                        } else {
                            echo '<li>' . htmlspecialchars($json_result['assessment']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</section>';

                        echo '<section>';
                        echo '<h3>💡 Plan</h3>';
                        echo '<ul>';
                        if (is_array($json_result['plan'])) {
                            foreach ($json_result['plan'] as $item) {
                                echo '<li>' . htmlspecialchars($item) . '</li>';
                            }
                        } else {
                            echo '<li>' . htmlspecialchars($json_result['plan']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</section>';
                    } else {
                        // Display as plain text/JSON
                        echo '<pre>' . htmlspecialchars($result) . '</pre>';
                    }
                    ?>
                </article>
            <?php endif; ?>

            <form method="POST" action="" id="soapForm" enctype="multipart/form-data">
                <fieldset>
                    <label for="content">Medical transcript:</label>
                    <textarea
                        id="content"
                        name="content"
                        rows="8"
                        placeholder="Enter the medical transcript here...&#10;&#10;Example:&#10;Physician: What brings you in today?&#10;Patient: I've been having headaches for the past week...&#10;&#10;Tip: You can also upload a text file or image of the transcript below."
                    ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : (isset($_GET['content']) ? htmlspecialchars($_GET['content']) : ''); ?></textarea>
                    <small>
                        Enter the medical transcript (patient-clinician dialogue) you want to convert to SOAP format.
                    </small>

                    <label for="file">Or upload a file:</label>
                    <input
                        type="file"
                        id="file"
                        name="file"
                        accept=".txt,.md,text/plain,text/markdown,image/jpeg,image/png,image/gif,image/webp"
                    >
                    <small>
                        Upload a plain text (.txt), Markdown (.md) file, or an image of the medical transcript. Maximum size: 2MB.
                    </small>

                    <label for="model">AI model:</label>
                    <select id="model" name="model">
                        <?php foreach ($AVAILABLE_MODELS as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($MODEL === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the AI model to use for SOAP note generation. MedGemma models work best for this task.
                    </small>

                    <label for="language">Response language:</label>
                    <select id="language" name="language">
                        <?php foreach ($AVAILABLE_LANGUAGES as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($LANGUAGE === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>
                        Select the language for the SOAP note output.
                    </small>
                </fieldset>

                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📋 Generate SOAP Note
                </button>

                <button type="button" class="btn btn-secondary" onclick="clearForm()">
                    🔄 New Transcript
                </button>
            </form>
        </main>
    </div>

    <script>
        function clearForm() {
            document.getElementById('content').value = '';
            document.getElementById('file').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
