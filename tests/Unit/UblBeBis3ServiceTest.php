<?php

use Darvis\UblPeppol\UblBeBis3Service;

describe('UblBeBis3Service', function () {
    
    beforeEach(function () {
        $this->service = new UblBeBis3Service();
    });

    it('can be instantiated', function () {
        expect($this->service)->toBeInstanceOf(UblBeBis3Service::class);
    });

    it('creates a document with proper XML structure', function () {
        $this->service->createDocument();
        $xml = $this->service->generateXml();
        
        expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
        expect($xml)->toContain('<Invoice');
        expect($xml)->toContain('xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"');
        expect($xml)->toContain('xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"');
        expect($xml)->toContain('xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"');
    });

    it('throws exception when trying to initialize document twice', function () {
        $this->service->createDocument();
        
        expect(fn() => $this->service->createDocument())
            ->toThrow(RuntimeException::class, 'Document is already initialized');
    });

    it('generates valid XML output', function () {
        $this->service->createDocument();
        $xml = $this->service->generateXml();
        
        // Test that XML is valid
        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        
        expect($result)->toBeTrue();
        expect($dom->documentElement->tagName)->toBe('Invoice');
    });

    it('has correct namespace URIs', function () {
        $reflection = new ReflectionClass($this->service);
        
        $cacUri = $reflection->getProperty('ns_cac_uri');
        $cacUri->setAccessible(true);
        expect($cacUri->getValue($this->service))->toBe('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        
        $cbcUri = $reflection->getProperty('ns_cbc_uri');
        $cbcUri->setAccessible(true);
        expect($cbcUri->getValue($this->service))->toBe('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        
        $invoiceUri = $reflection->getProperty('ns_invoice_uri');
        $invoiceUri->setAccessible(true);
        expect($invoiceUri->getValue($this->service))->toBe('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
    });

    it('formats XML output properly', function () {
        $this->service->createDocument();
        $xml = $this->service->generateXml();
        
        // Check that XML is formatted (contains newlines and indentation)
        expect($xml)->toContain("\n");
        expect($xml)->toMatch('/\s+</'); // Contains whitespace before closing tags
    });

    it('creates DOM document with UTF-8 encoding', function () {
        $reflection = new ReflectionClass($this->service);
        $domProperty = $reflection->getProperty('dom');
        $domProperty->setAccessible(true);
        $dom = $domProperty->getValue($this->service);
        
        expect($dom->encoding)->toBe('UTF-8');
        expect($dom->version)->toBe('1.0');
        expect($dom->formatOutput)->toBeTrue();
    });

});

describe('UblBeBis3Service Helper Methods', function () {
    
    beforeEach(function () {
        $this->service = new UblBeBis3Service();
        $this->service->createDocument();
        
        // Get access to protected methods for testing
        $this->reflection = new ReflectionClass($this->service);
    });

    it('can add child elements with addChildElement method', function () {
        $addChildMethod = $this->reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $this->reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        $childElement = $addChildMethod->invoke(
            $this->service, 
            $rootElement, 
            'cbc', 
            'ID', 
            'BE-001'
        );
        
        expect($childElement->tagName)->toBe('cbc:ID');
        expect($childElement->nodeValue)->toBe('BE-001');
        expect($rootElement->hasChildNodes())->toBeTrue();
    });

    it('can add child elements with attributes', function () {
        $addChildMethod = $this->reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $this->reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        $childElement = $addChildMethod->invoke(
            $this->service, 
            $rootElement, 
            'cbc', 
            'TaxAmount', 
            '21.00',
            ['currencyID' => 'EUR']
        );
        
        expect($childElement->tagName)->toBe('cbc:TaxAmount');
        expect($childElement->nodeValue)->toBe('21.00');
        expect($childElement->getAttribute('currencyID'))->toBe('EUR');
    });

    it('can create elements with createElement method', function () {
        $createElementMethod = $this->reflection->getMethod('createElement');
        $createElementMethod->setAccessible(true);
        
        $element = $createElementMethod->invoke(
            $this->service, 
            'cac', 
            'Party', 
            null,
            ['schemeID' => '0088']
        );
        
        expect($element->tagName)->toBe('cac:Party');
        expect($element->getAttribute('schemeID'))->toBe('0088');
    });

    it('can format amounts correctly', function () {
        $formatAmountMethod = $this->reflection->getMethod('formatAmount');
        $formatAmountMethod->setAccessible(true);
        
        expect($formatAmountMethod->invoke($this->service, 100))->toBe('100.00');
        expect($formatAmountMethod->invoke($this->service, 100.5))->toBe('100.50');
        expect($formatAmountMethod->invoke($this->service, 100.123))->toBe('100.12');
    });

});
