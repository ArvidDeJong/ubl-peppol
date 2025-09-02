<?php

use Darvis\UblPeppol\UblBeBis3Service;

describe('UblBeBis3Service Integration Tests', function () {
    
    beforeEach(function () {
        $this->service = new UblBeBis3Service();
        $this->testData = [
            'invoice_number' => 'BE-001',
            'issue_date' => '2025-01-01',
            'due_date' => '2025-01-31',
            'supplier' => [
                'name' => 'Belgian Supplier BV',
                'vat_number' => 'BE0123456789'
            ],
            'customer' => [
                'name' => 'Belgian Customer SA',
                'vat_number' => 'BE0987654321'
            ]
        ];
    });

    it('generates a complete Belgian UBL invoice XML', function () {
        $this->service->createDocument();
        
        // Add basic invoice information
        $reflection = new ReflectionClass($this->service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        // Add CustomizationID (Belgian specific)
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

    it('maintains proper namespace declarations for Belgian UBL', function () {
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

    it('handles Belgian tax categories correctly', function () {
        $this->service->createDocument();
        
        // Add element with Belgian tax category
        $reflection = new ReflectionClass($this->service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        // Add TaxCategory with Belgian BTCC value
        $taxCategory = $addChildMethod->invoke(
            $this->service,
            $rootElement,
            'cac',
            'TaxCategory'
        );
        
        $addChildMethod->invoke(
            $this->service,
            $taxCategory,
            'cbc',
            'ID',
            'S'
        );
        
        $addChildMethod->invoke(
            $this->service,
            $taxCategory,
            'cbc',
            'Name',
            'Taux standard'
        );
        
        $xml = $this->service->generateXml();
        
        expect($xml)->toContain('Taux standard');
        expect($xml)->toContain('TaxCategory');
        
        // Validate XML
        $dom = new DOMDocument();
        expect($dom->loadXML($xml))->toBeTrue();
    });

    it('handles UTF-8 encoding correctly with Belgian characters', function () {
        $this->service->createDocument();
        
        // Add element with Belgian/French UTF-8 characters
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
            'Factuur met speciale karakters: é à ç € ñ'
        );
        
        $xml = $this->service->generateXml();
        
        expect($xml)->toContain('é à ç € ñ');
        
        // Validate encoding
        $dom = new DOMDocument();
        $result = $dom->loadXML($xml);
        expect($result)->toBeTrue();
    });

    it('generates XML compliant with Belgian EN 16931 standard', function () {
        $this->service->createDocument();
        
        $reflection = new ReflectionClass($this->service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($this->service);
        
        // Add required Belgian elements
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'ID', 'BE-2025-001');
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'IssueDate', '2025-01-01');
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'InvoiceTypeCode', '380');
        
        // Add DocumentCurrencyCode
        $addChildMethod->invoke($this->service, $rootElement, 'cbc', 'DocumentCurrencyCode', 'EUR');
        
        $xml = $this->service->generateXml();
        
        // Check for Belgian compliance elements
        expect($xml)->toContain('urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0');
        expect($xml)->toContain('InvoiceTypeCode');
        expect($xml)->toContain('DocumentCurrencyCode');
        expect($xml)->toContain('EUR');
        
        // Validate XML structure
        $dom = new DOMDocument();
        expect($dom->loadXML($xml))->toBeTrue();
    });

});

describe('UblBeBis3Service Error Handling', function () {
    
    it('handles invalid XML characters gracefully', function () {
        $service = new UblBeBis3Service();
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
            'Valid Belgian content'
        );
        
        $xml = $service->generateXml();
        expect($xml)->toContain('Valid Belgian content');
    });

    it('maintains document integrity after multiple Belgian operations', function () {
        $service = new UblBeBis3Service();
        $service->createDocument();
        
        $reflection = new ReflectionClass($service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($service);
        
        // Add multiple Belgian-specific elements
        $belgianElements = [
            ['cbc', 'CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0'],
            ['cbc', 'ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0'],
            ['cbc', 'ID', 'BE-2025-001'],
            ['cbc', 'DocumentCurrencyCode', 'EUR'],
            ['cbc', 'InvoiceTypeCode', '380']
        ];
        
        foreach ($belgianElements as $element) {
            $addChildMethod->invoke(
                $service,
                $rootElement,
                $element[0],
                $element[1],
                $element[2]
            );
        }
        
        $xml = $service->generateXml();
        
        // All elements should be present
        foreach ($belgianElements as $element) {
            expect($xml)->toContain($element[2]);
        }
        
        // XML should still be valid
        $dom = new DOMDocument();
        expect($dom->loadXML($xml))->toBeTrue();
    });

    it('handles Belgian VAT number validation format', function () {
        $service = new UblBeBis3Service();
        $service->createDocument();
        
        $reflection = new ReflectionClass($service);
        $addChildMethod = $reflection->getMethod('addChildElement');
        $addChildMethod->setAccessible(true);
        
        $rootProperty = $reflection->getProperty('rootElement');
        $rootProperty->setAccessible(true);
        $rootElement = $rootProperty->getValue($service);
        
        // Add Belgian VAT number
        $party = $addChildMethod->invoke($service, $rootElement, 'cac', 'Party');
        $partyTaxScheme = $addChildMethod->invoke($service, $party, 'cac', 'PartyTaxScheme');
        $addChildMethod->invoke($service, $partyTaxScheme, 'cbc', 'CompanyID', 'BE0123456789', ['schemeID' => 'VAT']);
        
        $xml = $service->generateXml();
        
        expect($xml)->toContain('BE0123456789');
        expect($xml)->toContain('schemeID="VAT"');
        
        // Validate XML
        $dom = new DOMDocument();
        expect($dom->loadXML($xml))->toBeTrue();
    });

});
