// JSON syntax highlighting function
function jsonSyntaxHighlight(json) {
    if (typeof json !== 'string') {
        json = JSON.stringify(json, undefined, 2);
    }
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

// YAML syntax highlighting function
function yamlSyntaxHighlight(yaml) {
    // Escape HTML entities
    yaml = yaml.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    // Highlight string values (quoted strings)
    yaml = yaml.replace(/"([^"]*)"/g, '<span class="string">"$1"</span>');
    //yaml = yaml.replace(/'([^']*)'/g, '<span class="string">\'$1\'</span>');

    // Highlight keys (lines ending with ":")
    yaml = yaml.replace(/^(\s*[a-zA-Z0-9_-]+:)(.*)$/gm, function(match, key, rest) {
        return '<b class="key">' + key + '</b>' + rest;
    });

    // Highlight numbers
    yaml = yaml.replace(/\b(\d+(\.\d+)?)\b/g, '<span class="number">$1</span>');

    // Highlight booleans
    yaml = yaml.replace(/\b(true|false)\b/gi, '<span class="boolean">$1</span>');

    // Highlight null
    yaml = yaml.replace(/\b(null|~)\b/gi, '<span class="null">$1</span>');

    // Highlight comments
    yaml = yaml.replace(/#.*$/gm, '<i class="comment">$&</i>');

    return yaml;
}

// Markdown syntax highlighting function
function markdownSyntaxHighlight(markdown) {
    // Escape HTML entities first
    markdown = markdown.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    // Headers
    markdown = markdown.replace(/^(#{1,6})\s+(.+)$/gm, function(match, hashes, text) {
        const level = hashes.length;
        return '<h' + level + ' class="markdown-header">' + text + '</h' + level + '>';
    });

    // Bold (**text** or __text__)
    markdown = markdown.replace(/\*\*(.+?)\*\*/g, '<strong class="markdown-bold">$1</strong>');
    markdown = markdown.replace(/__(.+?)__/g, '<strong class="markdown-bold">$1</strong>');

    // Italic (*text* or _text_)
    markdown = markdown.replace(/\*(.+?)\*/g, '<em class="markdown-italic">$1</em>');
    markdown = markdown.replace(/_(.+?)_/g, '<em class="markdown-italic">$1</em>');

    // Inline code (`code`)
    markdown = markdown.replace(/`(.+?)`/g, '<code class="markdown-code">$1</code>');

    // Links [text](url)
    markdown = markdown.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" class="markdown-link">$1</a>');

    // Images ![alt](url)
    markdown = markdown.replace(/!\[(.+?)\]\((.+?)\)/g, '<img src="$2" alt="$1" class="markdown-image">');

    // Unordered lists
    markdown = markdown.replace(/^[\*\-]\s+(.+)$/gm, '<li class="markdown-list-item">$1</li>');

    // Ordered lists
    markdown = markdown.replace(/^\d+\.\s+(.+)$/gm, '<li class="markdown-list-item">$1</li>');

    // Blockquotes
    markdown = markdown.replace(/^>\s+(.+)$/gm, '<blockquote class="markdown-blockquote">$1</blockquote>');

    // Horizontal rule
    markdown = markdown.replace(/^(\*{3,}|-{3,}|_{3,})$/gm, '<hr class="markdown-hr">');

    return markdown;
}

/**
 * Apply syntax highlighting to all pre elements on the page
 * Handles JSON, YAML, and Markdown content
 */
function applySyntaxHighlighting() {
    const jsonElements = document.querySelectorAll('pre');
    jsonElements.forEach(function(element) {
        try {
            const text = element.textContent;
            if (element.classList.contains('highlight-json') && (text.trim().startsWith('{') || text.trim().startsWith('['))) {
                const json = JSON.parse(text);
                element.innerHTML = jsonSyntaxHighlight(json);
            } else if (element.classList.contains('highlight-yaml') || element.classList.contains('highlight-yml')) {
                element.innerHTML = yamlSyntaxHighlight(text);
            } else if (element.classList.contains('highlight-markdown') || element.classList.contains('highlight-md')) {
                element.innerHTML = markdownSyntaxHighlight(text);
            }
        } catch (e) {
            // Not valid JSON/YAML/Markdown, leave as is
        }
    });
}
