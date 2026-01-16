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
    <title>AI Document Processing Suite</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8F%A5%3C/text%3E%3C/svg%3E">
</head>
<body>
    <div class="container">
        <hgroup>
            <h1>📄 AI Document Processing Suite</h1>
            <p>A collection of specialized tools designed to process, analyze, and extract key information from various documents using artificial intelligence.</p>
        </hgroup>

        <main>
            <section class="tool-section">
                <h2>🏥 Medical Document Processing</h2>
                <p>Specialized tools for healthcare professionals to process and analyze medical documents.</p>

                <div class="tools-grid">
                    <a href="rra.php" class="tool-card">
                        <div class="tool-icon">🔍</div>
                        <h3>Radiology Report Analyzer</h3>
                        <p>Analyze radiology reports and extract key medical information in a structured JSON format.</p>
                    </a>

                    <a href="rdd.php" class="tool-card">
                        <div class="tool-icon">🩺</div>
                        <h3>Radiology Differential Diagnosis</h3>
                        <p>Generate differential diagnoses with supporting information from radiology reports.</p>
                    </a>

                    <a href="dpa.php" class="tool-card">
                        <div class="tool-icon">📋</div>
                        <h3>Discharge Paper Analyzer</h3>
                        <p>Analyze patient discharge papers and summarize key medical information for radiology use.</p>
                    </a>

                    <a href="soap.php" class="tool-card">
                        <div class="tool-icon">📋</div>
                        <h3>SOAP Note Generator</h3>
                        <p>Convert medical transcripts into structured SOAP notes using AI.</p>
                    </a>

                    <a href="pec.php" class="tool-card">
                        <div class="tool-icon">🤓</div>
                        <h3>Patient Education Content</h3>
                        <p>Convert complex medical information into patient-friendly educational content.</p>
                    </a>

                    <a href="chat.php" class="tool-card">
                        <div class="tool-icon">💬</div>
                        <h3>Medical Chat Assistant</h3>
                        <p>Interact directly with an AI medical assistant for real-time medical queries.</p>
                    </a>
                </div>
            </section>

            <section class="tool-section">
                <h2>📄 General Document Processing</h2>
                <p>Tools for processing various types of documents beyond medical content.</p>

                <div class="tools-grid">
                    <a href="ocr.php" class="tool-card">
                        <div class="tool-icon">📷</div>
                        <h3>Image OCR Tool</h3>
                        <p>Perform OCR on uploaded images and extract text in Markdown format.</p>
                    </a>

                    <a href="wpc.php" class="tool-card">
                        <div class="tool-icon">🔗</div>
                        <h3>Web Page Converter</h3>
                        <p>Scrape web pages and convert them to clean Markdown format using AI processing.</p>
                    </a>

                    <a href="wps.php" class="tool-card">
                        <div class="tool-icon">📝</div>
                        <h3>Web Page Summarizer</h3>
                        <p>Scrape web pages and return a structured summary of the most important points.</p>
                    </a>

                    <a href="sde.php" class="tool-card">
                        <div class="tool-icon">📊</div>
                        <h3>Structured Data Extractor</h3>
                        <p>AI-powered extraction of structured data from unstructured text.</p>
                    </a>
                </div>
            </section>

            <section class="tool-section">
                <h2>📚 Research & Academic Tools</h2>
                <p>Specialized tools for academic research and literature analysis.</p>

                <div class="tools-grid">
                    <a href="stp.php" class="tool-card">
                        <div class="tool-icon">📄</div>
                        <h3>Summarize This Paper</h3>
                        <p>AI-powered academic paper summarization with structured approaches.</p>
                    </a>

                    <a href="sml.php" class="tool-card">
                        <div class="tool-icon">📚</div>
                        <h3>Search Medical Literature</h3>
                        <p>Search medical literature databases and get AI-summarized research papers.</p>
                    </a>
                </div>
            </section>

            <section class="tool-section">
                <h2>🧪 Development & Testing</h2>
                <p>Tools for testing AI models and experimenting with prompts.</p>

                <div class="tools-grid">
                    <a href="exp.php" class="tool-card">
                        <div class="tool-icon">🧪</div>
                        <h3>Experiment Tool</h3>
                        <p>Run predefined prompts through AI models for testing and experimentation.</p>
                    </a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
