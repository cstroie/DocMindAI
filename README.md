# Medical AI Tools Suite

A collection of PHP web applications that use AI to process medical documents and extract key information in structured formats.

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
  "summary": "summary text"
}
```

### 2. Discharge Paper Analyzer (dpa.php)
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

### 3. Optical Character Recognition Tool (ocr.php)
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

### 4. Simple Content Parser (scp.php)
Scrapes web pages and converts them to clean Markdown format using AI processing.

**Features:**
- Web scraping with Chrome browser simulation
- Cookie handling for session management
- AI-powered content detection and Markdown conversion
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

## Requirements

- PHP 7.0+
- cURL extension
- JSON extension
- Access to compatible AI API (e.g., Ollama)
- ImageMagick or GraphicsMagick PHP extensions (for OCR PDF processing)

## Installation

1. Clone or download this repository
2. Place all files on a PHP-enabled web server
3. Ensure the web server can write to the system's temporary directory
4. Install and configure an AI API service (e.g., Ollama) locally or remotely

## Configuration

Create a `config.php` file with your AI API settings:

```php
<?php
$LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
$LLM_API_KEY = '';
$DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
$DEFAULT_VISION_MODEL = 'gemma3:4b';
$LLM_API_FILTER = '/free/';
?>
```

## Usage

### Web Interface
Access any of the tools directly through a web browser:
- `http://your-server/rra.php` - Radiology Report Analyzer
- `http://your-server/dpa.php` - Discharge Paper Analyzer
- `http://your-server/ocr.php` - Optical Character Recognition Tool
- `http://your-server/scp.php` - Simple Content Parser

### API Endpoints
All tools support REST API calls as described in their respective sections above.

## Supported Languages

All tools support these languages:
- Romanian (ro)
- English (en)
- Spanish (es)
- French (fr)
- German (de)
- Italian (it)

## License

GPL 3

## Author

Costin Stroie <costinstroie@eridu.eu.org>
