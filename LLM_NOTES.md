# LLM Reference & Coding Standards

## Project Overview
DocMind AI is a unified gateway for AI tool operations, serving both as an API and web interface. It processes documents, images, and text through various AI profiles and tools.

## Architecture

### Core Components
1. **docmind.php** - Main entry point and API router
2. **common.php** - Shared utility functions
3. **config.php** - Configuration settings (user-created)
4. **profiles.json** - Profile definitions
5. **languages.json** - Language configurations
6. **prompts/** - Directory containing prompt templates

### Request Flow
```
HTTP Request
    ↓
docmind.php (entry point)
    ↓
API Request Detection (action parameter or JSON Accept header)
    ↓
handleApiRequest() (router)
    ↓
Route to specific handler:
- handleGetModels() - Get available AI models
- handleGetPrompts() - Get prompt templates
- handleProfileAction() - Process profile-specific requests
```

## Coding Standards

### PHP Documentation (PHPDoc)
All functions must include comprehensive PHPDoc blocks with:
- `@param` - Parameter descriptions with types
- `@return` - Return value description with types
- `@global` - Global variable usage
- `@see` - Related functions
- `@note` - Important implementation notes

Example:
```php
/**
 * Function description
 * 
 * @param string $param1 Description
 * @param array $param2 Description
 * @return string Description
 * @see relatedFunction() - Related functionality
 * @note Important note about behavior
 */
function exampleFunction($param1, $param2) {
    // Implementation
}
```

### Inline Comments
- Use `//` for single-line comments
- Explain complex logic and business decisions
- Comment on non-obvious code behavior
- Document TODO items with `// TODO:`

### Naming Conventions
- Functions: `camelCase()`
- Variables: `$camelCase`
- Constants: `UPPER_CASE`
- Files: `lowercase.php` or `lowercase.html`

### Error Handling
- Always validate input parameters
- Use `sendJsonResponse()` for API errors
- Provide meaningful error messages
- Include fallback mechanisms where appropriate

### Security
- Validate all user input
- Use `escapeshellarg()` for shell commands
- Sanitize file paths
- Validate URLs with `filter_var()`
- Check file upload errors

## Profile Configuration

### profiles.json Structure
```json
{
  "profiles": {
    "profile_id": {
      "name": "Profile Name",
      "description": "Profile description",
      "icon": "emoji",
      "form": {
        "fields": [
          {
            "name": "field_name",
            "type": "text|textarea|select|file",
            "label": "Field Label",
            "required": true,
            "options": ["option1", "option2"]
          }
        ]
      },
      "prompt": "Prompt template",
      "prompts": {
        "key1": "Prompt 1",
        "key2": "Prompt 2"
      },
      "tool": "tool_name",
      "output": "json|text"
    }
  }
}
```

### Placeholder Replacement
Supported placeholders in prompts:
- `{language_instruction}` - Language-specific instruction
- `{language}` - Selected language code
- `{field_name}` - Any form field value

## API Endpoints

### Available Actions
- `get_models` - Retrieve available AI models
- `get_prompts` - Retrieve prompt templates
- `get_form` - Get form configuration
- `get_profiles` - Get available profiles
- `{profile_id}` - Execute profile-specific processing

### Response Format
```json
{
  "profile": "profile_id",
  "response": {
    "choices": [...],
    "usage": {...}
  },
  "json": {...}, // Only if profile.output = "json"
  "debug": {
    "form_data": {...},
    "api_data": {...}
  }
}
```

## Tool Integration

### Available Tools
1. **web_scraper** - Scrapes content from URLs
   - Requires: `url` field
   - Validates: URL format

2. **medical_literature_search** - Searches PubMed
   - Requires: `query` field
   - Validates: Query length (max 500 chars)

### Adding New Tools
1. Add tool case to `executeTool()` function
2. Implement validation logic
3. Return string result or `false` on error
4. Update documentation

## File Processing

### Image Processing
- Supported formats: JPEG, PNG, GIF, WEBP
- Maximum size: Configurable (default 500KB)
- Processing: `processUploadedImage()`
- Output: Base64 encoded for API

### Document Processing
- Supported formats: DOC, DOCX, PDF, ODT, TXT
- Tools: antiword, catdoc, pdftotext, odt2txt, pandoc
- Processing: `extractTextFromDocument()`
- Output: Plain text with BOM removal

## API Integration

### LLM API Requirements
- Endpoint: `$LLM_API_ENDPOINT` (config.php)
- Chat endpoint: `$LLM_API_ENDPOINT . '/chat/completions'`
- Authentication: `Authorization: Bearer $LLM_API_KEY`
- Request format: OpenAI-compatible

### Fallback Behavior
- If API fails, use default models
- If profile not found, return error
- If tool fails, return error message

## Frontend Integration

### JavaScript Functions
- `loadProfiles()` - Load and display profiles
- `loadModels()` - Populate model dropdown
- `loadPrompts()` - Populate prompt dropdown
- `submitForm()` - Submit form data
- `displayResults()` - Render results

### HTML Templates
- `#errorTemplate` - Error display
- `#loadingTemplate` - Loading indicator
- `#profileCardTemplate` - Profile cards
- `#formFieldTemplate` - Form fields
- `#resultTemplate` - Results display

## Common Patterns

### Configuration Loading
```php
$profiles_data = loadResourceFromJson('profiles.json');
if (isset($profiles_data['error'])) {
    sendJsonResponse(['error' => $profiles_data['error']], true);
}
```

### File Upload Validation
```php
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // Process file
} else {
    sendJsonResponse(['error' => 'File upload failed'], true);
}
```

### API Response Processing
```php
if (isset($response['error'])) {
    sendJsonResponse(['error' => $response['error']], true);
}
```

## Testing Commands

### View Web Interface
```bash
# Open in browser (assuming local server)
xdg-open http://localhost/docmind.php
# or
open http://localhost/docmind.php
```

### Test API Endpoints
```bash
# Get models
curl -X GET "http://localhost/docmind.php?action=get_models"

# Get prompts
curl -X GET "http://localhost/docmind.php?action=get_prompts"

# Test profile action
curl -X POST "http://localhost/docmind.php?action=medical_assistant" \
  -F "query=test query" \
  -F "model=gemma3:1b"
```

### Check PHP Syntax
```bash
php -l docmind.php
php -l common.php
```

## Maintenance

### Adding New Profiles
1. Add profile to `profiles.json`
2. Create prompt templates if needed
3. Test API endpoint
4. Update frontend if needed

### Adding New Languages
1. Add to `languages.json`
2. Update prompt templates with `{language_instruction}`
3. Test language selection

### Updating Documentation
1. Update PHPDoc blocks for modified functions
2. Add inline comments for complex logic
3. Update this notes file
4. Commit with clear message

## Version Control

### Commit Messages
- `docs: improve documentation for [function/file]`
- `feat: add [feature]`
- `fix: resolve [issue]`
- `refactor: improve [code section]`

### Git Workflow
```bash
# Make changes
git add .
git commit -m "docs: improve documentation for docmind.php"
git push
```

## Troubleshooting

### Common Issues
1. **API endpoint not configured** - Check `config.php`
2. **Profile not found** - Verify `profiles.json`
3. **File upload fails** - Check file size and type
4. **Tool execution fails** - Verify required tools are installed

### Debug Mode
- Enable debug in `config.php`
- Check `$result['debug']` in API responses
- Use `error_log()` for server-side debugging

## Dependencies

### Required PHP Extensions
- `curl` - API requests
- `json` - JSON processing
- `gd` - Image processing
- `imagick` - PDF image extraction (optional)

### Required System Tools
- `antiword` or `catdoc` - DOC files
- `pdftotext` - PDF files
- `odt2txt` - ODT files
- `pandoc` - Universal document converter

### Installation Commands
```bash
# Ubuntu/Debian
sudo apt-get install antiword catdoc poppler-utils odt2txt pandoc

# macOS
brew install antiword catdoc poppler odt2txt pandoc
```

## Security Notes

### Input Validation
- Always validate user input before processing
- Use appropriate validation functions
- Sanitize output before display

### File Uploads
- Validate file types
- Check file sizes
- Process files in temporary directory
- Clean up temporary files

### API Keys
- Store in `config.php` (not in version control)
- Use environment variables in production
- Rotate keys regularly

## Performance

### Caching
- Consider caching API responses
- Cache profile configurations
- Cache language configurations

### Optimization
- Minimize API calls
- Use efficient file processing
- Optimize image sizes
- Use appropriate timeouts

## Future Enhancements

### Planned Features
- [ ] User authentication
- [ ] Rate limiting
- [ ] Response caching
- [ ] Batch processing
- [ ] Webhook support
- [ ] Custom tool creation

### API Improvements
- [ ] Streaming responses
- [ ] Progress indicators
- [ ] Cancel functionality
- [ ] History tracking

## Contact & Support

### Author
Costin Stroie <costinstroie@eridu.eu.org>

### License
GPL 3

### Repository
Eridu.eu.org

---

*Last updated: 2026-01-22*
