# DocMind AI

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

A comprehensive collection of PHP web applications that use AI to process documents and extract key information in structured formats.

## Tools Included

### 1. Radiology Report Analyzer (rra.php)
Analyzes radiology reports and extracts key medical information in a structured JSON format.

**Features:**
- AI-powered analysis of radiology reports
- Multiple lightweight AI models support (filtered for free models)
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /rra.php
Content-Type: application/x-www-form-urlencoded

report=Hazy opacity in the left mid lung field...&model=qwen2.5:1.5b&language=en
```

**Response:**
```json
{
  "pathologic": "yes/no",
  "severity": 0-10,
  "summary": "summary text",
  "diagnoses": ["diagnosis1", "diagnosis2", "diagnosis3"]
}
```

### 2. Radiology Differential Diagnosis (rdd.php)
Generates differential diagnoses with supporting information from radiology reports.

**Features:**
- AI-powered differential diagnosis generation
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /rdd.php
Content-Type: application/x-www-form-urlencoded

report=Hazy opacity in the left mid lung field...&model=qwen2.5:1.5b&language=en
```

**Response:**
```json
{
  "diagnoses": [
    {
      "condition": "diagnosis name",
      "probability": 0-100,
      "description": "detailed explanation",
      "supporting_features": ["feature1", "feature2"],
      "references": ["reference1", "reference2"]
    }
  ]
}
```

### 3. Discharge Paper Analyzer (dpa.php)
Analyzes patient discharge papers and summarizes key medical information for radiology use.

**Features:**
- AI-powered analysis of patient discharge papers
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /dpa.php
Content-Type: application/x-www-form-urlencoded

report=Patient discharged after treatment for pneumonia...&model=qwen2.5:1.5b&language=ro
```

**Response:**
```json
{
  "pathologic": "yes/no",
  "severity": 0-10,
  "summary": "summary paragraph",
  "keywords": ["keyword1", "keyword2", "keyword3"]
}
```

### 4. Image OCR Tool (ocr.php)
Uses AI to perform OCR on uploaded images and extract text in Markdown format.

**Features:**
- AI-powered OCR of images and PDFs
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support
- PDF processing capabilities

**API Usage:**
```http
POST /ocr.php
Content-Type: multipart/form-data

file=@document.png&model=gemma3:4b&language=en
```

**Response:**
```json
{
  "text": "extracted text in markdown format"
}
```

### 5. Simple Content Parser (scp.php)
Scrapes web pages and converts them to clean Markdown or DokuWiki format using AI processing.

**Features:**
- Web scraping with Chrome browser simulation
- Cookie handling for session management
- AI-powered content detection and formatting conversion
- Web interface with real-time results
- REST API support
- Multiple output formats (Markdown, DokuWiki)

**API Usage:**
```http
POST /scp.php
Content-Type: application/x-www-form-urlencoded

url=https://example.com/article&model=qwen2.5:1.5b&language=en&format=markdown
```

**Response:**
```json
{
  "markdown": "content in markdown format"
}
```

### 6. Web Page Summarizer (wps.php)
Scrapes web pages and returns a structured summary of the most important points in the article.

**Features:**
- Web scraping with Chrome browser simulation
- AI-powered content summarization
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /wps.php
Content-Type: application/x-www-form-urlencoded

url=https://example.com/article&model=qwen2.5:1.5b&language=en
```

**Response:**
```json
{
  "title": "article title",
  "summary": "main summary",
  "key_points": ["point 1", "point 2", ...],
  "keywords": ["keyword1", "keyword2", ...]
}
```

### 7. Summarize This Paper (stp.php)
AI-powered academic paper summarization with structured approaches.

**Features:**
- Text input or file upload (txt/markdown)
- Predefined prompt templates (Three-Pass Summary, Problem–Idea–Evidence)
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /stp.php
Content-Type: application/x-www-form-urlencoded

content=Research paper content...&model=qwen2.5:1.5b&language=en&prompt_type=three_pass
```

**Response:**
```json
{
  "summary": "structured summary based on selected prompt"
}
```

### 8. Patient Education Content (pec.php)
Converts complex medical information into patient-friendly educational content.

**Features:**
- AI-powered simplification of medical content
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /pec.php
Content-Type: application/x-www-form-urlencoded

content=Complex medical content...&model=qwen2.5:1.5b&language=en
```

**Response:**
```json
{
  "education": "simplified patient-friendly content"
}
```

### 9. Search Medical Literature (sml.php)
Searches medical literature databases and gets AI-summarized research papers.

**Features:**
- PubMed database search
- AI-powered summarization of research papers
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /sml.php
Content-Type: application/x-www-form-urlencoded

query=diabetes treatment&model=qwen2.5:1.5b&language=en
```

**Response:**
```json
{
  "results": [
    {
      "title": "paper title",
      "authors": ["author1", "author2"],
      "journal": "journal name",
      "year": 2023,
      "summary": "structured summary",
      "key_findings": ["finding1", "finding2"],
      "methodology": "research methodology",
      "pmid": "pubmed id"
    }
  ]
}
```

### 10. Medical Chat Assistant (chat.php)
Interact directly with an AI medical assistant for real-time medical queries.

**Features:**
- Real-time chat interface with medical AI
- Multiple AI personalities (Medical Assistant, GP, Specialist, Researcher, Skippy)
- Multiple lightweight AI models support
- Multilingual output (6 languages)
- Web interface with real-time results
- REST API support

**API Usage:**
```http
POST /chat.php
Content-Type: application/json

{
  "message": "What are the symptoms of diabetes?",
  "history": [],
  "model": "qwen2.5:1.5b",
  "personality": "medical_assistant",
  "language": "en"
}
```

**Response:**
```json
{
  "reply": "AI response",
  "history": [chat history array],
  "model": "model used",
  "language": "language used",
  "personality": "personality used"
}
```

## 📋 Requirements

- PHP 7.0+
- cURL extension
- JSON extension
- ImageMagick or GraphicsMagick PHP extensions (for OCR PDF processing)
- Access to compatible AI API (e.g., Ollama, MedGemma)

## 🔧 Configuration

Create a `config.php` file from the example:
```bash
cp config.php.example config.php
```

Then edit `config.php` with your AI API settings:
```php
<?php
$LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
$LLM_API_KEY = '';
$DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
$DEFAULT_VISION_MODEL = 'gemma3:4b';
$LLM_API_FILTER = '/free/';
$CHAT_HISTORY_LENGTH = 10;
?>
- ImageMagick or GraphicsMagick PHP extensions (for OCR PDF processing)

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

## Configuration

Create a `config.php` file with your AI API settings:

```php
<?php
$LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
$LLM_API_KEY = '';
$DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
$DEFAULT_VISION_MODEL = 'gemma3:4b';
$LLM_API_FILTER = '/free/';
$CHAT_HISTORY_LENGTH = 10;
?>
```

## 📖 Usage

### Web Interface
Access any of the tools directly through a web browser:
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

### API Endpoints
All tools support REST API calls as described in their respective sections above.

## 📝 Supported Languages

All tools support these languages:
- Romanian (ro)
- English (en)
- Spanish (es)
- French (fr)
- German (de)
- Italian (it)

## Supported Languages

All tools support these languages:
- Romanian (ro)
- English (en)
- Spanish (es)
- French (fr)
- German (de)
- Italian (it)

## 📄 License

This project is licensed under the GPL 3 License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:
- Fork the repository
- Create a feature branch
- Submit a pull request
- Follow the existing code style

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
