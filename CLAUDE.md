# DocMind AI — Developer Reference

## Project Overview

DocMind AI is a PHP web application providing AI-powered document processing tools, focused primarily on **medical and clinical use cases** (radiology reports, clinical notes, research papers, patient education). It acts as a gateway between a browser UI and any OpenAI-compatible LLM API.

- **Author:** Costin Stroie <costinstroie@eridu.eu.org>
- **License:** GPL v3
- **Version:** 4.1 (config.json)
- **Repo:** https://github.com/cstroie/DocMindAI

---

## Version History

### v4.1 (Current)
**UI/UX Refinements**
- ✨ Added Recent Analyses section to home page (max 3 items, clickable)
- 🎨 Flattened results card structure (removed nested boxes)
- 🎯 Consolidated header hierarchy (eliminated triple header repetition)
- 🔧 Improved form subtitle system with full-width content
- 📐 Refined button styling for consistency (flex layout, padding, border-radius)

**Form System Improvements**
- ✅ Three-level header hierarchy now properly implemented:
  - Page header: category context (name + description)
  - Form header: tool context (name + concise description)
  - Form subtitle: longer form-specific description (full context)
- ✅ All 33 tool form descriptions restored to original longer versions
- ✅ Concise tool descriptions in form kicker, fuller guidance in subtitle

### v4.0 
**Techno Redesign**
- Dropped PicoCSS, implemented standalone design system
- Implemented diagnostic styling with severity indicators and segmented bars
- Added Results View template for long-form content
- Severity box filling with color based on pathologic status
- Improved text report visual hierarchy with numbered sections
- Enhanced headers with category and tool descriptions

---

## Architecture

### Single-gateway design

All requests flow through one endpoint: `docmind.php`. It serves both the web UI (includes `index.html`) and a JSON API (detected via `?action=` or `Accept: application/json`).

```
Browser → docmind.php ──[GET]──→ index.html (web UI)
                    └──[POST action=<tool_id>]──→ LLM API → JSON response
```

### Key files

| File | Purpose |
|---|---|
| `docmind.php` | Unified PHP backend — all logic lives here |
| `index.html` | Single-page frontend shell |
| `scripts.js` | Frontend JS — view routing, form rendering, API calls |
| `styles.css` | All application styles (dark/light theme) |
| `config.json` | Tool registry, categories, languages, common form fields |
| `config.php` | **Runtime secrets** — API keys, active provider (gitignored) |
| `config.php.example` | Template for config.php — commit-safe |
| `ocr.php` | Standalone OCR endpoint (image/PDF → text via Tesseract) |
| `tools/<cat>/<id>.json` | Tool definitions (prompt, form schema, display template) |
| `prompts/` | Freeform prompt files served via `get_prompts` action |

### Frontend libraries (vendored)

- `handlebars.min.js` — Handlebars templating for rendering tool responses
- `marked.js` — Markdown → HTML rendering
- `highlight.min.js` — Syntax highlighting for code blocks

---

## Tool System

### Adding a new tool

1. Create `tools/<category>/<tool_id>.json` with the schema below.
2. Add `"<tool_id>": "<category>"` to the `"tools"` object in `config.json`.
3. No PHP changes needed — the backend auto-discovers tools via config.json.

### Tool JSON schema

```json
{
  "id": "tool_id",
  "name": "Human Name",
  "description": "Short description",
  "icon": "🔬",
  "prompt": "string or structured object with {placeholders}",
  "output": "json",         // optional — triggers JSON extraction from LLM response
  "display": "html|json",   // how to render: html via Handlebars template, or raw json
  "form": {
    "title": "Form title",
    "description": "Form description",
    "fields": [
      { "name": "report", "type": "textarea", "label": "...", "required": true },
      "model",       // shorthand — expands from config.json common.form.fields
      "language",    // shorthand
      "file",        // shorthand
      "url"          // shorthand
    ]
  },
  "template": ["<article>", "  {{#each items}}", "  ...</article>"]
}
```

- **Prompt placeholders:** `{field_name}` is replaced with submitted form values. `{language_instruction}` is replaced with the language-specific instruction from config.json.
- **Structured prompts:** If `prompt` is an object/array, `convertPromptArrayToText()` serialises it before placeholder substitution.
- **Helpers:** Set `"helper": "lynx|web_scraper|medical_literature_search"` to pre-process a URL or run a PubMed search before the LLM call.

### Tool categories

| ID | Name | Description |
|---|---|---|
| `rad` | Radiology | Report analysis, differential diagnosis, MRS |
| `cli` | Clinical | SOAP notes, SBAR, discharge summaries, pre-op |
| `adm` | Medical Administration | Discharge papers, hospital docs |
| `pac` | Patient Education | Patient-friendly content, questionnaires |
| `res` | Research & Academia | Paper analysis, AGREE II, literature search |
| `cpr` | Content Processing | Web scraping, document rewriting, extraction |
| `ccr` | Content Creation | Email composition |
| `dev` | Development & Testing | OCR, model experimentation, web content fetch |

### Current tools (32 total)

`dqc, rex, rrs, rdd, soap, dsn, pec, sbar, mex, anm, cpn, sde, rdg, wpc, wps, cta, apa, etp, stp, sta, sml, pld, exp, eml, ocr, mrs, dps, hda, pre, ade, agr` + `mrs` (in cli/)

---

## LLM Provider System

### Configuration (config.php)

Set `$LLM_ACTIVE_PROVIDER` to one of the keys in `$LLM_PROVIDERS`.

```php
$LLM_ACTIVE_PROVIDER = 'groq'; // switch providers here
```

### Supported providers (built-in examples in config.php.example)

| Key | Provider | Notes |
|---|---|---|
| `ollama` | Ollama (local) | Default; no API key needed |
| `local` | llama.cpp (local) | No `/models` endpoint |
| `openai` | OpenAI | Requires key |
| `openrouter` | OpenRouter | Aggregator |
| `cerebras` | Cerebras | Fast inference |
| `together` | Together.xyz | |
| `anthropic` | Anthropic (via proxy) | No `/models` endpoint; `driver: openai` |
| `groq` | Groq | Free tier; fastest |
| `gemini` | Google AI Studio | Free tier; 1M context |
| `mistral` | Mistral La Plateforme | EU-hosted; free tier |
| `ddg` | DuckDuckGo AI Chat | Zero-config; unofficial; `driver: ddg` |

### Provider fields

- `endpoint` — Base URL (no trailing slash, no `/chat/completions`)
- `key` — Bearer token; empty string for local/keyless providers
- `filter` — PCRE regex applied to model IDs from `/models`
- `default_model` — Used when no model submitted or no `/models` API
- `models_api` — `true` = fetch `/models`; `false` = expose only `default_model`
- `driver` — `openai` (default) or `ddg`

### DDG driver

`callDDGApi()` handles DuckDuckGo's unofficial duck.ai API. Key behaviours:
- Fetches a rotating token pair (`X-Vqd-4` + `x-vqd-hash-1`) from the status endpoint.
- Parses SSE stream and wraps in an OpenAI-compatible envelope.
- Retries once on 401 with a fresh token.
- Only the last user message is sent (no multi-turn memory).
- **Not suitable for production** — unofficial, undocumented rate limits.

Available DDG models: `gpt-4o-mini`, `claude-3-haiku-20240307`, `meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo`, `mistralai/Mixtral-8x7B-Instruct-v0.1`

---

## Security Notes

The codebase had 15 bugs fixed (see commit `89c78da`). Current security posture:

- **File upload MIME detection:** Uses `finfo` (reads actual bytes), not `$_FILES['type']` (client-supplied, untrusted).
- **CORS:** Origin validated against `$ALLOWED_ORIGINS` allowlist; never reflected verbatim.
- **CSRF:** Session-based token generated with `random_bytes(32)`; session only regenerated on fresh session creation (not every request).
- **Shell calls:** All file paths passed to external tools (`antiword`, `pdftotext`, `lynx`, etc.) are escaped with `escapeshellarg()`.
- **JSON output:** `json_encode()` called with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP` to prevent XSS.
- **Security headers:** `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Content-Security-Policy: default-src 'none'`.
- **Action sanitisation:** `preg_replace('/[^a-zA-Z0-9_.]/', '', ...)` before routing.
- **File size limit:** `MAX_FILE_SIZE = 10MB` enforced before GD processing.

---

## Image Processing Pipeline

`processUploadedImage()` → validates MIME via finfo → creates GD resource → optionally resizes (default 500px max dimension) → returns JPEG binary + mime type for base64 encoding.

`preprocessImageForOCR()` → resize → grayscale → optional Otsu binarisation → optional 3×3 dilation → saves as PNG temp file.

`extractImagesFromPDF()` → tries Imagick first, then Gmagick → rasterises first page at 200 DPI → returns PNG blob.

---

## Document Text Extraction

`extractTextFromDocument()` dispatches to CLI tools by MIME type:

| MIME | Tool |
|---|---|
| `application/msword` | `antiword` or `catdoc` |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | `docx2txt` |
| `application/pdf` | `pdftotext -layout` |
| `application/vnd.oasis.opendocument.text` | `odt2txt` |
| `text/plain`, `text/markdown` | `file_get_contents()` |

Searched in `/usr/bin/` then `/usr/local/bin/`. stderr redirected to `/dev/null`.

---

## API Endpoints

All via `docmind.php` with `?action=<value>` or `POST action=<value>`:

| Action | Handler | Description |
|---|---|---|
| `get_models` | `handleGetModels()` | Returns available models + active provider name |
| `get_prompts` | `handleGetPrompts()` | Returns prompt templates from `prompts/` directory |
| `<tool_id>` | `handleToolAction()` | Runs a tool: build prompt → call LLM → process response |

### Tool action pipeline

1. Resolve active provider via `getActiveProvider()`
2. Load tool config via `getToolConfig($tool_id)`
3. Process file upload (image → GD resize, doc → text extraction)
4. Validate required form fields
5. Run helper if `tool['helper']` set
6. Build prompt via `buildToolPrompt()` (placeholder substitution)
7. Dispatch to `callLLMApi()` or `callDDGApi()`
8. Process response via `processToolResponse()` (JSON extraction if `output/display = json`)
9. Return JSON with `tool`, `response`, optional `json`, and `debug.prompt`

---

## Frontend Architecture

- **Single page app** — `switchView()` in `scripts.js` handles navigation without page reload.
- **Category menu** — Built dynamically from `config.json` categories.
- **Tool forms** — Rendered from the tool's `form.fields` JSON schema.
- **Response rendering** — Handlebars template from tool JSON; falls back to markdown/raw text.
- **History** — Stored in `localStorage` with pagination.
- **Theme** — Cycles light → dark → system; stored in `localStorage`.
- **State** — `AppState` class manages current tool, model, language selections.

---

## Development Notes

### Environment

- **Minimum: PHP 7.3** — `str_starts_with` and `str_contains` are polyfilled at the top of `docmind.php`; `array_key_first` requires 7.3.
- PHP 8.x works without changes; no 8-only syntax is actually used in the code.
- Required extensions: `curl`, `json`, `gd` (image processing), `fileinfo`.
- Optional: `imagick` or `gmagick` (PDF→image), external CLI tools for doc extraction.

### Configuration precedence

1. `$LLM_PROVIDERS` + `$LLM_ACTIVE_PROVIDER` (new style) — takes priority if set.
2. Legacy flat `$LLM_API_ENDPOINT` / `$LLM_API_KEY` / `$LLM_API_FILTER` — fallback for existing installs.

### Debug mode

Set `$DEBUG_MODE = true` in `config.php` to include `prompt`, `api_data`, `provider`, and `file_info` in every JSON response.

### Prompt files in `prompts/`

Files with `.txt`, `.md`, or `.xml` extensions are served as raw prompt templates via `get_prompts`. `.dis` extension is ignored (disabled). The `PROMPT_STRUCTURE` file (no extension) is also skipped.

### JSON extraction from LLM responses

`extractJsonFromResponse()` tries in order:
1. Content inside a ` ```json … ``` ` fence.
2. First balanced `{…}` block (depth-aware scan, not greedy regex).
3. Light cleanup: trailing commas, unquoted keys, single-quoted values.

### Gemini model ID normalisation

Gemini's OpenAI-compat layer prefixes IDs with `models/` — `getAvailableModels()` strips this prefix so bare names like `gemini-2.5-flash` are used in API calls.
