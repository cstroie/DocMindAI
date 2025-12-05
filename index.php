<?php
/**
 * Medical AI Tools Suite - Main Index
 * 
 * A central hub for accessing all medical AI tools in the suite.
 * 
 * @author Costin Stroie <costinstroie@eridu.eu.org>
 * @version 1.0
 * @license GPL 3
 */

// Include common functions for language support
include 'common.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical AI Tools Suite</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8F%A5%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>🏥 Medical AI Tools Suite</h1>
            <p>AI-powered medical document processing and analysis</p>
        </hgroup>

        <main>
            <section class="welcome-section">
                <h2>Welcome to the Medical AI Tools Suite</h2>
                <p>A collection of specialized tools designed to help medical professionals process, analyze, and extract key information from various medical documents using artificial intelligence.</p>
            </section>
            
            <section class="tools-grid">
                <a href="rra.php" class="tool-card">
                    <div class="tool-icon">🔍</div>
                    <h3>Radiology Report Analyzer</h3>
                    <p>Analyze radiology reports and extract key medical information in a structured JSON format.</p>
                    <div class="btn btn-primary">Access Tool</div>
                </a>
                
                <a href="dpa.php" class="tool-card">
                    <div class="tool-icon">📋</div>
                    <h3>Discharge Paper Analyzer</h3>
                    <p>Analyze patient discharge papers and summarize key medical information for radiology use.</p>
                    <div class="btn btn-primary">Access Tool</div>
                </a>
                
                <a href="ocr.php" class="tool-card">
                    <div class="tool-icon">📷</div>
                    <h3>Image OCR Tool</h3>
                    <p>Perform OCR on uploaded images and extract text in Markdown format.</p>
                    <div class="btn btn-primary">Access Tool</div>
                </a>
                
                <a href="scp.php" class="tool-card">
                    <div class="tool-icon">🔗</div>
                    <h3>Simple Content Parser</h3>
                    <p>Scrape web pages and convert them to clean Markdown format using AI processing.</p>
                    <div class="btn btn-primary">Access Tool</div>
                </a>
                
                <a href="sum.php" class="tool-card">
                    <div class="tool-icon">📝</div>
                    <h3>Web Page Summarizer</h3>
                    <p>Scrape web pages and return a structured summary of the most important points.</p>
                    <div class="btn btn-primary">Access Tool</div>
                </a>
            </section>
        </main>
        
        <footer>
            <section style="margin-top: 40px; text-align: center;">
                <h3>Configuration</h3>
                <p style="margin-top: 12px;">
                    All tools use a common configuration file. Create a <code>config.php</code> file with your AI API settings:
                </p>
                <pre style="background: #eff6ff; padding: 16px; border-radius: 8px; text-align: left; margin: 16px auto; max-width: 500px; font-size: 0.9rem;">
&lt;?php
$LLM_API_ENDPOINT = 'http://127.0.0.1:11434/v1';
$LLM_API_KEY = '';
$DEFAULT_TEXT_MODEL = 'qwen2.5:1.5b';
$DEFAULT_VISION_MODEL = 'gemma3:4b';
$LLM_API_FILTER = '/free/';
?&gt;</pre>
            </section>
        </footer>
    </div>
</body>
</html>
