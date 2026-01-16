<?php
/**
 * Search Medical Literature
 * 
 * A PHP web application that searches medical literature databases and provides
 * structured summaries of relevant research papers.
 * 
 * Features:
 * - PubMed database search
 * - AI-powered summarization of research papers
 * - Multiple lightweight AI models support (filtered for free models)
 * - Multilingual output (6 languages)
 * - Web interface with real-time results
 * - REST API support
 * - Configurable API endpoint via external config.php
 * 
 * Requirements:
 * - PHP 7.0+
 * - cURL extension
 * - JSON extension
 * - Access to compatible AI API (e.g., Ollama)
 * 
 * Usage:
 * - Web interface: Access via browser
 * - API endpoint: POST /sml.php with search query
 * 
 * API Usage:
 * POST /sml.php
 * Parameters:
 * - query (required): Medical literature search query
 * - model (optional): AI model to use (default: qwen2.5:1.5b)
 * - language (optional): Output language (default: en)
 * 
 * Response:
 * {
 *   "results": [
 *     {
 *       "title": "paper title",
 *       "authors": ["author1", "author2"],
 *       "journal": "journal name",
 *       "year": 2023,
 *       "summary": "structured summary",
 *       "key_findings": ["finding1", "finding2"],
 *       "methodology": "research methodology",
 *       "pmid": "pubmed id"
 *     }
 *   ]
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

/**
 * Get selected model and language from POST/GET data, cookies, or use defaults
 */
$MODEL = isset($_POST['model']) ? $_POST['model'] : (isset($_GET['model']) ? $_GET['model'] : (isset($_COOKIE['sml-model']) ? $_COOKIE['sml-model'] : $DEFAULT_TEXT_MODEL));
$LANGUAGE = isset($_POST['language']) ? $_POST['language'] : (isset($_GET['language']) ? $_GET['language'] : (isset($_COOKIE['sml-language']) ? $_COOKIE['sml-language'] : 'en'));

/**
 * Validate model selection
 * Falls back to default model if invalid model is selected
 */
if (!array_key_exists($MODEL, $AVAILABLE_MODELS)) {
    $MODEL = 'qwen2.5:1.5b'; // Default to a valid model
}

/**
 * Validate language selection
 * Falls back to English if invalid language is selected
 */
if (!array_key_exists($LANGUAGE, $AVAILABLE_LANGUAGES)) {
    $LANGUAGE = 'en'; // Default to English
}

/**
 * System prompt for the AI model
 * Contains instructions for summarizing medical literature
 */
$SYSTEM_PROMPT = "You are a medical research assistant specializing in summarizing scientific literature. Your task is to analyze research papers and create structured summaries of their key components.

CRITICAL INSTRUCTION: " . getLanguageInstruction($LANGUAGE) . "

If you find a direct answer to the search query in the article content, include it in the summary section.

OUTPUT FORMAT (JSON):
{
  \"title\": \"paper title\",
  \"authors\": [\"author1\", \"author2\", \"author3\"],
  \"journal\": \"journal name\",
  \"year\": 2023,
  \"summary\": \"2-3 sentence overview of the study, including direct answer to query if found\",
  \"key_findings\": [\"finding 1\", \"finding 2\", \"finding 3\"],
  \"methodology\": \"brief description of research methods\",
  \"pmid\": \"pubmed id\"
}

RULES:
- \"title\": Extract the full title of the paper
- \"authors\": Extract all authors (up to 5, then \"et al.\")
- \"journal\": Extract the journal name
- \"year\": Extract the publication year
- \"summary\": Create a 2-3 sentence overview focusing on objectives, methods, and main conclusions. If the article directly answers the search query, include that answer prominently.
- \"key_findings\": Extract exactly 3 key findings or results from the study
- \"methodology\": Briefly describe the research methodology (1-2 sentences)
- \"pmid\": Include the PubMed ID if available
- Focus on the most important information
- Be concise but comprehensive
- Respond ONLY with the JSON, without additional text

EXAMPLE:

Input: Research paper about a new diabetes treatment...
Response: {
  \"title\": \"Efficacy of Novel GLP-1 Receptor Agonist in Type 2 Diabetes Management\",
  \"authors\": [\"Smith J\", \"Johnson K\", \"Brown L\", \"Davis M\", \"Wilson R\"],
  \"journal\": \"Journal of Clinical Endocrinology & Metabolism\",
  \"year\": 2023,
  \"summary\": \"This randomized controlled trial evaluated the efficacy of a novel GLP-1 receptor agonist in 500 patients with type 2 diabetes over 24 weeks. The treatment group showed significantly greater reductions in HbA1c levels compared to placebo. The drug was well-tolerated with minimal side effects.\",
  \"key_findings\": [
    \"HbA1c reduced by 1.5% in treatment group vs 0.3% in placebo\",
    \"Weight loss of 3.2 kg average in treatment group\",
    \"95% of patients experienced mild gastrointestinal side effects\"
  ],
  \"methodology\": \"Double-blind, placebo-controlled, randomized clinical trial with 500 participants over 24 weeks.\",
  \"pmid\": \"12345678\"
}";

/**
 * Application state variables
 * @var array|null $result Literature search results
 * @var string|null $error Error message if any
 * @var bool $processing Whether analysis is in progress
 * @var bool $is_api_request Whether request is API call (not web form)
 */
$result = null;
$error = null;
$processing = false;
$is_api_request = false;

/**
 * Handle POST/GET requests for literature search
 * Processes both web form submissions and API requests
 * Validates input, searches PubMed, calls AI API, and processes response
 */
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['query'])) || 
    ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['query']))) {
    $processing = true;
    $is_api_request = (!isset($_POST['submit']) && !isset($_GET['submit'])); // If no submit button, it's an API request
    
    // Sanitize and validate input
    $query = trim(isset($_POST['query']) ? $_POST['query'] : $_GET['query']);
    
    // Validate query length (prevent extremely large inputs)
    if (strlen($query) > 500) {
        $error = 'The search query is too long. Maximum 500 characters allowed.';
        $processing = false;
    } 
    // Validate query is not empty after trimming
    elseif (empty($query)) {
        $error = 'The search query cannot be empty.';
        $processing = false;
    }
    
    // Only proceed with search if validation passed
    if ($processing) {
        // Search PubMed for relevant articles
        $articles = searchPubMed($query, 5); // Get top 5 results
        
        if ($articles === false) {
            $error = 'Failed to search medical literature database. Please try again later.';
            $processing = false;
        } elseif (empty($articles)) {
            $error = 'No relevant articles found for your search query.';
            $processing = false;
        } else {
            $result = [];
            
            // Process each article
            foreach ($articles as $article) {
                // Prepare API request for summarization
                $data = [
                    'model' => $MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => $SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => "RESEARCH PAPER TO SUMMARIZE:\n" . json_encode($article)]
                    ]
                ];
                
                // Make API request using common function
                $response_data = callLLMApi($LLM_API_ENDPOINT_CHAT, $data, $LLM_API_KEY);
                
                if (isset($response_data['error'])) {
                    $error = $response_data['error'];
                    break;
                } elseif (isset($response_data['choices'][0]['message']['content'])) {
                    $content = trim($response_data['choices'][0]['message']['content']);
                    
                    // Extract JSON from response
                    // First try direct JSON parsing
                    $json_str = trim($content);

                    // If direct parsing fails, try to extract from code blocks or other patterns
                    if (json_decode($json_str) === null) {
                        $json_patterns = [
                            '/```(?:json)?\s*({.*?})\s*```/s',  // JSON in code blocks
                            '/\{.*\}/s',                        // Any JSON object
                            '/({.*?})/s'                        // Capture any braces
                        ];

                        foreach ($json_patterns as $pattern) {
                            if (preg_match($pattern, $content, $matches)) {
                                $json_str = $matches[1];
                                break;
                            }
                        }
                    }

                    if ($json_str) {
                        $json_result = json_decode($json_str, true);
                    } else {
                        $json_result = null;
                    }
                    
                    if ($json_result) {
                        $result[] = $json_result;
                    } else {
                        $error = 'Invalid response format from AI model.';
                        break;
                    }
                } else {
                    $error = 'Invalid API response format';
                    break;
                }
            }
        }
        
        // Set cookies with the selected model and language only for web requests
        if (!$is_api_request) {
            setcookie('sml-model', $MODEL, time() + (30 * 24 * 60 * 60), '/'); // 30 days
            setcookie('sml-language', $LANGUAGE, time() + (30 * 24 * 60 * 60), '/'); // 30 days
        }
        
        // Return JSON if it's an API request
        if ($is_api_request) {
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            if ($error) {
                echo json_encode(['error' => $error]);
            } else {
                echo json_encode(['results' => $result]);
            }
            exit;
        }
    }
}

/**
 * Search PubMed for articles matching the query
 * 
 * @param string $query Search query
 * @param int $max_results Maximum number of results to return
 * @return array|false Array of articles or false on error
 */
function searchPubMed($query, $max_results = 5) {
    // PubMed API endpoint
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi';
    
    // Prepare search parameters
    $params = [
        'db' => 'pubmed',
        'term' => $query,
        'retmax' => $max_results,
        'retmode' => 'json',
        'sort' => 'relevance'
    ];
    
    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);
    
    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['esearchresult']['idlist'])) {
        return false;
    }
    
    $ids = $response_data['esearchresult']['idlist'];
    
    if (empty($ids)) {
        return [];
    }
    
    // Fetch details for each article
    return fetchArticleDetails($ids);
}

/**
 * Fetch detailed information for PubMed articles
 * 
 * @param array $ids PubMed IDs
 * @return array|false Array of article details or false on error
 */
function fetchArticleDetails($ids) {
    // PubMed API endpoint for fetching details
    $pubmed_url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi';
    
    // Prepare fetch parameters
    $params = [
        'db' => 'pubmed',
        'id' => implode(',', $ids),
        'retmode' => 'xml'
    ];
    
    // Build URL with parameters
    $url = $pubmed_url . '?' . http_build_query($params);
    
    // Make API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MedicalLiteratureSearch/1.0 (costinstroie@eridu.eu.org)');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $http_code !== 200) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Parse XML response
    $xml = simplexml_load_string($response);
    
    if ($xml === false) {
        return false;
    }
    
    $articles = [];
    
    // Process each article
    foreach ($xml->PubmedArticle as $article) {
        $parsed_article = [];
        
        // Extract PMID
        $parsed_article['pmid'] = (string)$article->MedlineCitation->PMID;
        
        // Extract title
        $parsed_article['title'] = (string)$article->MedlineCitation->Article->ArticleTitle;
        
        // Extract authors
        $authors = [];
        foreach ($article->MedlineCitation->Article->AuthorList->Author as $author) {
            $author_name = (string)$author->LastName;
            if (!empty($author->Initials)) {
                $author_name .= ' ' . (string)$author->Initials;
            }
            $authors[] = $author_name;
        }
        $parsed_article['authors'] = array_slice($authors, 0, 5);
        if (count($authors) > 5) {
            $parsed_article['authors'][] = 'et al.';
        }
        
        // Extract journal
        $parsed_article['journal'] = (string)$article->MedlineCitation->Article->Journal->Title;
        
        // Extract year
        $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->Year;
        if (empty($parsed_article['year'])) {
            $parsed_article['year'] = (string)$article->MedlineCitation->Article->Journal->JournalIssue->PubDate->MedlineDate;
        }
        
        // Extract abstract
        $parsed_article['abstract'] = (string)$article->MedlineCitation->Article->Abstract->AbstractText;
        
        $articles[] = $parsed_article;
    }
    
    return $articles;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocMind AI - Literature Search</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%9A%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📚 DocMind AI - Literature Search</h1>
            <p>AI-powered search and summarization of research papers</p>
        </hgroup>

        <main>
            <?php if ($error): ?>
                <section role="alert" class="error">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                </section>
            <?php endif; ?>
            
            <?php if ($result): ?>
                    <?php foreach ($result as $index => $article): ?>
                <article class="diagnosis-item">
                    <header>
                        <h3>
                            <?php echo ($index + 1) . '. ' . htmlspecialchars($article['title']); ?>
                        </h3>
                            <p><strong>Authors:</strong> <?php echo htmlspecialchars(implode(', ', $article['authors'])); ?></p>
                            <p><strong>Journal:</strong> <?php echo htmlspecialchars($article['journal']); ?> (<?php echo htmlspecialchars($article['year']); ?>)</p>
                            <p><strong>PMID:</strong> <?php echo htmlspecialchars($article['pmid']); ?></p>
                    </header>
                    <main>
                        <p><?php echo htmlspecialchars($article['summary']); ?></p>
                        
                        <div>
                            <h4>Key Findings:</h4>
                            <ul>
                                <?php foreach ($article['key_findings'] as $finding): ?>
                                    <li><?php echo htmlspecialchars($finding); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div>
                            <h4>Methodology:</h4>
                            <p><?php echo htmlspecialchars($article['methodology']); ?></p>
                        </div>
                    </main>
                </article>
                    <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="" id="literatureForm">
                <fieldset>
                    <label for="query">Search query:</label>
                    <input 
                        type="text" 
                        id="query" 
                        name="query" 
                        value="<?php echo isset($_POST['query']) ? htmlspecialchars($_POST['query']) : (isset($_GET['query']) ? htmlspecialchars($_GET['query']) : ''); ?>"
                        placeholder="Enter medical topic or research question..."
                        required
                    >
                    <small>
                        Enter a medical topic, research question, or keywords to search for relevant literature.
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
                        Select the AI model to use for summarization.
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
                        Select the language for the summary output.
                    </small>
                </fieldset>
                
                <button type="submit" name="submit" value="1" class="btn btn-primary">
                    <?php if ($processing && !$result && !$error): ?>
                        <span class="loading"></span>
                    <?php endif; ?>
                    📚 Search Literature
                </button>
                
                <div class="button-grid">
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        🔄 New Search
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                        🏠 Back to Main Menu
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('query').value = '';
            document.getElementById('model').selectedIndex = 0;
            document.getElementById('language').selectedIndex = 0;
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
