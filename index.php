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
                
                <a href="ocr.php" class="tool-card">
                    <div class="tool-icon">📷</div>
                    <h3>Image OCR Tool</h3>
                    <p>Perform OCR on uploaded images and extract text in Markdown format.</p>
                </a>
                
                <a href="scp.php" class="tool-card">
                    <div class="tool-icon">🔗</div>
                    <h3>Simple Content Parser</h3>
                    <p>Scrape web pages and convert them to clean Markdown format using AI processing.</p>
                </a>
                
                <a href="sum.php" class="tool-card">
                    <div class="tool-icon">📝</div>
                    <h3>Web Page Summarizer</h3>
                    <p>Scrape web pages and return a structured summary of the most important points.</p>
                </a>
                
                <a href="pec.php" class="tool-card">
                    <div class="tool-icon">🤓</div>
                    <h3>Patient Education Content</h3>
                    <p>Convert complex medical information into patient-friendly educational content.</p>
                </a>
                
                <a href="sml.php" class="tool-card">
                    <div class="tool-icon">📚</div>
                    <h3>Search Medical Literature</h3>
                    <p>Search medical literature databases and get AI-summarized research papers.</p>
                </a>
            </section>
        </main>
    </div>
</body>
</html>
