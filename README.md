# DocMind AI

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

A comprehensive collection of PHP web applications that use AI to process documents and extract key information in structured formats.

## 🚀 Quick Start

### Installation
```bash
git clone https://github.com/cstroie/DocMindAI.git
cd docmind-ai
cp config.php.example config.php
# Edit config.php with your AI API settings
```

### Prerequisites
- PHP 7.0+
- cURL extension
- JSON extension
- ImageMagick or GraphicsMagick (for OCR functionality)
- Access to compatible AI API (e.g., Ollama, MedGemma)

### Setup
1. Clone this repository or download the files
2. Create a `config.php` file from the example:
   ```bash
   cp config.php.example config.php
   ```
3. Edit `config.php` with your AI API endpoint and settings
4. Ensure all required PHP extensions are installed and enabled
5. Make sure your web server has write access to the system's temporary directory

## 📋 Configuration

Create a `config.php` file with your AI API settings:

```php
<?php
/**
 * Configuration file for DocMind AI
 *
 * IMPORTANT: This file should be renamed to config.php and placed in the same directory
 * as the application files. Do not commit config.php to version control as it may contain
 * sensitive information.
 *
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @license GPL 3
 */

/**
 * AI API Configuration
 *
 * @var string $LLM_API_ENDPOINT The base URL for your AI API endpoint
 * @var string $LLM_API_KEY Your API key if required (leave empty if not needed)
 * @var string $DEFAULT_TEXT_MODEL Default model for text processing
 * @var string $DEFAULT_VISION_MODEL Default model for vision/image processing
 * @var string $LLM_API_FILTER Regular expression to filter available models
 */
$LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1'; // Your AI API endpoint
$LLM_API_KEY = ''; // API key if required
$DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b'; // Default text model
$DEFAULT_VISION_MODEL = 'gemma3:4b'; // Default vision model
$LLM_API_FILTER = '/free/'; // Regular expression to filter models

/**
 * Application Configuration
 *
 * @var int $CHAT_HISTORY_LENGTH Maximum number of messages to keep in chat history
 */
$CHAT_HISTORY_LENGTH = 10; // Maximum chat history length

/**
 * Security Configuration
 *
 * @var bool $DEBUG_MODE Enable debug mode for development
 * @var array $ALLOWED_ORIGINS List of allowed origins for CORS
 */
$DEBUG_MODE = false; // Set to true for development/debugging
$ALLOWED_ORIGINS = ['*']; // List of allowed origins for CORS

/**
 * File Upload Configuration
 *
 * @var int $MAX_FILE_SIZE Maximum file size for uploads in bytes
 * @var array $ALLOWED_FILE_TYPES Allowed file types for uploads
 */
$MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
$ALLOWED_FILE_TYPES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'text/plain', 'text/markdown',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.oasis.opendocument.text'
];
?>
```

## 🛠️ Tools Overview

DocMind AI provides a comprehensive suite of AI-powered tools organized into categories for different use cases:

### 🏥 Clinical Tools
- **Radiology Report Analyzer (rra.php)** - Analyzes radiology reports and extracts key medical information
- **Radiology Differential Diagnosis (rdd.php)** - Generates differential diagnoses with supporting information
- **Discharge Paper Analyzer (dpa.php)** - Analyzes patient discharge papers for radiology use
- **Patient Education Content (pec.php)** - Converts complex medical information into patient-friendly content
- **Clinical SOAP Notes (soap.php)** - Creates structured SOAP notes from medical content
- **Diagnosis Summary (dsn.php)** - Summarizes key diagnostic information
- **Clinical Notes (cn.php)** - Generates clinical notes from medical data
- **Pre-operative Assessment (pre.php)** - Analyzes pre-operative assessments

### 🩻 Radiology Tools
- **Radiology Report Extractor (rex.php)** - Extracts specific information from radiology reports
- **Radiology Report Summary (rrs.php)** - Summarizes radiology reports
- **Radiology Differential Diagnosis (rdd.php)** - Generates differential diagnoses
- **Medical Report Summary (mrs.php)** - Summarizes medical reports

### 📋 Medical Administration
- **Discharge Paper Analyzer (dpa.php)** - Analyzes patient discharge papers
- **Hospital Discharge Analyzer (hda.php)** - Analyzes hospital discharge documents
- **Radiology Report Parser (rpd.php)** - Parses radiology reports for administrative use

### 📚 Research & Academia
- **Academic Paper Analyzer (apa.php)** - Analyzes academic papers and extracts key information
- **Evidence-based Template (etp.php)** - Creates evidence-based medical templates
- **Summarize This Paper (stp.php)** - AI-powered academic paper summarization
- **Structured Analysis (sta.php)** - Provides structured analysis of academic content
- **Search Medical Literature (sml.php)** - Searches medical literature databases
- **Paper Literature Database (pld.php)** - Manages paper literature databases

### 🧠 Content Processing
- **Simple Content Parser (scp.php)** - Scrapes web pages and converts to clean Markdown
- **Web Page Summarizer (wps.php)** - Returns structured summaries of web articles
- **Content Transformer (cta.php)** - Transforms content between different formats
- **Radiology Differential Diagnosis (rdd.php)** - Processes radiology content

### ✍️ Content Creation
- **Email Composer (eml.php)** - Composes emails with AI assistance
- **Patient Education Content (pec.php)** - Creates patient-friendly educational content

### 🧪 Development & Testing
- **Web Page Content (wpc.php)** - Tests web page content extraction
- **Experimentation (exp.php)** - AI model testing and experimentation
- **Image OCR Tool (ocr.php)** - AI-powered OCR for images and PDFs

## 🌍 Supported Languages

All tools support these languages:
- Romanian (ro) 🇷🇴
- English (en) 🇬🇧
- Spanish (es) 🇪🇸
- French (fr) 🇫🇷
- German (de) 🇩🇪
- Italian (it) 🇮🇹
- Greek (el) 🇬🇷
- Hungarian (hu) 🇭🇺
- Russian (ru) 🇷🇺

## 🎨 Features

### Web Interface
- Modern, responsive design with dark/light theme support
- Category-based organization of tools
- Real-time processing with loading indicators
- Syntax highlighting for code and structured data
- History tracking with pagination
- Form validation and error handling
- Toast notifications for user feedback

### API Support
- RESTful API endpoints for all tools
- JSON response format
- File upload support for documents and images
- Multilingual output support
- Error handling and validation
- CORS support for cross-origin requests

### Technical Features
- AI-powered document processing
- OCR capabilities for images and PDFs
- Web scraping with cookie handling
- Markdown and HTML content processing
- JSON data extraction and transformation
- Handlebars templating for dynamic content
- LocalStorage for history persistence
- Modern JavaScript with async/await

## 📖 Usage

### Web Interface
Access any tool directly through your web browser:
- `http://your-server/rra.php` - Radiology Report Analyzer
- `http://your-server/rdd.php` - Radiology Differential Diagnosis
- `http://your-server/dpa.php` - Discharge Paper Analyzer
- `http://your-server/ocr.php` - Image OCR Tool
- `http://your-server/scp.php` - Simple Content Parser
- `http://your-server/wps.php` - Web Page Summarizer
- `http://your-server/stp.php` - Summarize This Paper
- `http://your-server/pec.php` - Patient Education Content
- `http://your-server/sml.php` - Search Medical Literature
- `http://your-server/chat.php` - Medical Chat Assistant

### API Usage Example
```http
POST /rra.php
Content-Type: application/x-www-form-urlencoded

report=Hazy opacity in the left mid lung field...&model=qwen2.5:1.5b&language=en
```

### Response Example
```json
{
  "pathologic": "yes",
  "severity": 5,
  "summary": "Moderate opacity detected in left mid lung field",
  "diagnoses": ["Pneumonia", "Pleural effusion", "Atelectasis"]
}
```

## 🎨 Syntax Highlighting

The application uses [highlight.js](https://highlightjs.org/) for syntax highlighting. The library is included in the repository as `highlight.min.js`. To customize the highlighting style, you can:

1. Replace the CSS file in the HTML head section
2. Choose from available styles at https://highlightjs.org/static/demo/
3. Or create your own custom theme

The current configuration uses the GitHub theme:
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github.min.css">
```

To change the theme, simply replace the URL with another style from the highlight.js collection.

## 📋 Requirements

- PHP 7.0+
- cURL extension
- JSON extension
- ImageMagick or GraphicsMagick PHP extensions (for OCR PDF processing)
- Access to compatible AI API (e.g., Ollama, MedGemma)
- [highlight.js](https://highlightjs.org/) for syntax highlighting (included in the repository)

## 🛠️ Development

### Project Structure
```
docmind-ai/
├── config.php.example          # Configuration template
├── config.json                # Tool and language configuration
├── index.html                 # Main web interface
├── scripts.js                 # Frontend JavaScript
├── styles.css                 # Application styles
├── handlebars.min.js          # Template engine
├── marked.js                  # Markdown parser
├── highlight.min.js           # Syntax highlighting
├── tools/                     # Tool configurations
│   ├── cli/                   # Clinical tools
│   ├── rad/                   # Radiology tools
│   ├── adm/                   # Medical administration
│   ├── res/                   # Research & academia
│   ├── cpr/                   # Content processing
│   ├── dev/                   # Development & testing
│   ├── rad/                   # Radiology (duplicate)
│   └── ccr/                   # Content creation
└── [various PHP tool files]   # Individual tool implementations
```

### Adding New Tools
1. Create a tool configuration file in the appropriate category folder
2. Add the tool to `config.json` under the appropriate category
3. Implement the tool logic in a PHP file
4. Test the tool through both web interface and API

### Configuration
- Tools are configured in `config.json` with metadata and form fields
- Categories organize tools by functionality
- Languages define supported output languages
- Common form fields are defined for consistency

### Frontend Architecture
- Modern JavaScript with ES6+ features
- Async/await for API calls
- State management with AppState class
- Template-based rendering with Handlebars
- Responsive design with CSS Grid and Flexbox
- Dark/light theme support
- Loading states and error handling

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:
- Fork the repository
- Create a feature branch
- Submit a pull request
- Follow the existing code style
- Update documentation for new features
- Test your changes thoroughly

## 📋 Agent Guidance

For developers extending or modifying DocMind AI, see [AGENTS.md](AGENTS.md) for architecture documentation and common development patterns.

## 📄 License

This project is licensed under the GPL 3 License - see the [LICENSE](LICENSE) file for details.

## 📬 Contact

Costin Stroie - costinstroie@eridu.eu.org

Project Link: [https://github.com/cstroie/DocMindAI](https://github.com/cstroie/DocMindAI)

## 📝 Acknowledgments

- Thanks to all contributors
- Inspired by medical AI research
- Built with PHP and modern AI technologies

## 📋 Changelog

See the [commit history](https://github.com/cstroie/DocMindAI/commits/main) for detailed changes.

## 🔒 Security

For security issues, please contact costinstroie@eridu.eu.org directly.
