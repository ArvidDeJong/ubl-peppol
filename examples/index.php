<?php
/**
 * UBL/PEPPOL Examples Index
 * 
 * This file provides an overview of all available UBL/PEPPOL examples.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UBL/PEPPOL Examples</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .example {
            background: #f9f9f9;
            border-left: 4px solid #3498db;
            margin: 20px 0;
            padding: 15px;
            border-radius: 0 4px 4px 0;
        }
        .example h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        .example p {
            margin-bottom: 15px;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-view {
            background: #3498db;
        }
        .btn-download {
            background: #2ecc71;
            margin-left: 10px;
        }
        .btn-download:hover {
            background: #27ae60;
        }
        .btn-source {
            background: #95a5a6;
            margin-left: 10px;
        }
        .btn-source:hover {
            background: #7f8c8d;
        }
        .btn-group {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>UBL/PEPPOL Examples</h1>
    
    <p>Welcome to the UBL/PEPPOL examples page. Below you'll find various examples demonstrating how to use the UBL/PEPPOL library.</p>

    <div class="example">
        <h2>1. Generate Basic Invoice</h2>
        <p>A simple example showing how to generate a basic UBL invoice with multiple line items and automatic tax calculation.</p>
        <div class="btn-group">
            <a href="generate_invoice.php" class="btn btn-view">View Example</a>
            <a href="generate_invoice.php?download" class="btn btn-download">Download XML</a>
            <a href="view-source:generate_invoice.php" class="btn btn-source">View Source</a>
        </div>
    </div>

    <div class="example">
        <h2>2. Complete Invoice Example</h2>
        <p>A comprehensive example showing all available UBL invoice components including supplier/customer information, delivery details, payment terms, and more.</p>
        <div class="btn-group">
            <a href="CompleteInvoiceExample.php" class="btn btn-view">View Example</a>
            <a href="CompleteInvoiceExample.php?download" class="btn btn-download">Download XML</a>
            <a href="view-source:CompleteInvoiceExample.php" class="btn btn-source">View Source</a>
        </div>
    </div>

    <div class="example">
        <h2>3. View Base Example XML</h2>
        <p>View the base example XML file that this implementation is based on.</p>
        <div class="btn-group">
            <a href="base-example.xml" class="btn btn-view">View XML</a>
        </div>
    </div>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee;">
        <h3>Documentation</h3>
        <p>For more information about this library, please refer to the <a href="../README.md">README</a> file or the project's <a href="https://github.com/ArvidDeJong/ubl-peppol" target="_blank">GitHub repository</a>.</p>
    </div>
</body>
</html>
