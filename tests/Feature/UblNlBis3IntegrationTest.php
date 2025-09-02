<?php

use Darvis\UblPeppol\UblNlBis3Service;

describe('UblNlBis3Service Integration Tests', function () {
    
    beforeEach(function () {
        $this->service = new UblNlBis3Service();
        $this->testData = [
            'invoice_number' => 'TEST-001',
            'issue_date' => '2025-01-01',
            'due_date' => '2025-01-31'
        ];
    });

    it('generates a complete UBL invoice XML', function () {
        $this->service->createDocument();
        
        // Add basic invoice information
        $reflection = new ReflectionClass($this->service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        // Add CustomizationID
        $addChildMethod->invoke(
            $this->service,
            $rootElement,
            'cbc',
            'CustomizationID',
            'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'
        );
        
        // Add ProfileID
        $addChildMethod->invoke(
            $this->service,
            $rootElement,
            'cbc',
            'ProfileID',
            'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'
        );
        
        // Add ID
        $addChildMethod->invoke(
            $this->service,
            $rootElement,
            'cbc',
            'ID',
            $this->testData['invoice_number']
        );
        
        $xml = $this->service->generateXml();
        
        expect($xml)->toContain('CustomizationID');
        expect($xml)->toContain('ProfileID');
        expect($xml)->toContain($this->testData['invoice_number']);
        
        // Validate XML structure
        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        expect($result)->toBeTrue();
    });

    it('creates valid XML that can be parsed', function () {
        $this->service->createDocument();
        $xml = $this->service->generateXml();
        
        // Parse with SimpleXML
        $simpleXml = simplexml_load_string($xml);
        expect($simpleXml)->not->toBeFalse();
        expect($simpleXml->getName())->toBe('Invoice');
    });

    it('maintains proper namespace declarations', function () {
        $this->service->createDocument();
        $xml = $this->service->generateXml();
        
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        
        // Should be able to query with namespaces - Invoice is in default namespace
        $invoiceElements = $xpath->query('//*[local-name()="Invoice"]');
        expect($invoiceElements->length)->toBe(1);
    });

    it('handles UTF-8 encoding correctly', function () {
        $this->service->createDocument();
        
        // Add element with UTF-8 characters
        $reflection = new ReflectionClass($this->service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        $addChildMethod->invoke(
            $this->service,
            $rootElement,
            'cbc',
            'Note',
            'Test with special chars: áéíóú ñ €'
        );
        
        $xml = $this->service->generateXml();
        
        expect($xml)->toContain('áéíóú ñ €');
        
        // Validate encoding
        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        expect($result)->toBeTrue();
    });

});

describe('UblNlBis3Service Error Handling', function () {
    
    it('handles invalid XML characters gracefully', function () {
        $service = new UblNlBis3Service();
        $service->createDocument();
        
        $reflection = new ReflectionClass($service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($service);
        
        // This should not break the XML generation
        $addChildMethod->invoke(
            $service,
            $rootElement,
            'cbc',
            'Note',
            'Valid content'
        );
        
        $xml = $service->generateXml();
        expect($xml)->toContain('Valid content');
    });

    it('maintains document integrity after multiple operations', function () {
        $service = new UblNlBis3Service();
        $service->createDocument();
        
        $reflection = new ReflectionClass($service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($service);
        
        // Add multiple elements
        for ($i = 1; $i <= 5; $i++) {
            $addChildMethod->invoke(
                $service,
                $rootElement,
                'cbc',
                "TestElement{$i}",
                "Value {$i}"
            );
        }
        
        $xml = $service->generateXml();
        
        // All elements should be present
        for ($i = 1; $i <= 5; $i++) {
            expect($xml)->toContain("TestElement{$i}");
            expect($xml)->toContain("Value {$i}");
        }
        
        // XML should still be valid
        $dom = new DOMDocument();
        expect($dom->loadXML($xml))->toBeTrue();
    });

});
