/*
 * PEPPOL validator of the docs site. Runs the official EN 16931 and PEPPOL BIS Billing 3.0 rules,
 * compiled by examples/validate/build-browser-validator.sh, with SaxonJS in the browser. The file
 * is read locally and never sent anywhere.
 */
(function () {
    'use strict';

    var BASE = 'assets/validator/';
    var SVRL = 'http://purl.oclc.org/dsdl/svrl';
    var CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    var CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    var ROOTS = {
        'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2': 'Invoice',
        'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2': 'CreditNote'
    };
    var RULESETS = [
        { file: 'CEN-EN16931-UBL.sef.json', name: 'EN 16931' },
        { file: 'PEPPOL-EN16931-UBL.sef.json', name: 'PEPPOL BIS Billing 3.0' }
    ];

    /* The national rule sets inside the PEPPOL rules, and when they run */
    var COUNTRIES = {
        NL: { name: 'the Netherlands', prefix: 'NL-R', when: 'seller', page: 'netherlands.html' },
        BE: { name: 'Belgium', prefix: null, page: 'belgium.html' },
        DE: { name: 'Germany', prefix: 'DE-R', when: 'both' },
        DK: { name: 'Denmark', prefix: 'DK-R', when: 'seller' },
        GR: { name: 'Greece', prefix: 'GR-R', when: 'seller' },
        IS: { name: 'Iceland', prefix: 'IS-R', when: 'seller' },
        IT: { name: 'Italy', prefix: 'IT-R', when: 'seller' },
        NO: { name: 'Norway', prefix: 'NO-R', when: 'seller' },
        SE: { name: 'Sweden', prefix: 'SE-R', when: 'seller' },
        XX: { name: 'another country', prefix: null }
    };

    var stylesheets = {};
    var form = document.getElementById('pv-form');

    if (!form) {
        return;
    }

    var fileInput = document.getElementById('pv-file');
    var textInput = document.getElementById('pv-text');
    var countryInput = document.getElementById('pv-country');
    var statusBox = document.getElementById('pv-status');
    var resultBox = document.getElementById('pv-result');

    function escape(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function status(message) {
        statusBox.textContent = message;
    }

    function ruleUrl(id) {
        var set = /^(PEPPOL-|[A-Z]{2}-R-)/.test(id) ? 'ubl-peppol' : 'ubl-tc434';

        return 'https://docs.peppol.eu/poacc/billing/3.0/rules/' + set + '/' + encodeURIComponent(id) + '/';
    }

    /* Where this documentation explains the rule, with what to do in darvis/ubl-peppol */
    function packageHint(id) {
        if (/^BR-(S|Z|E|AE|IC|G|O|AF|AG)-\d+$/.test(id)) {
            return { href: 'vat-categories.html', text: 'VAT categories' };
        }
        if (/^NL-R-/.test(id)) {
            return { href: 'netherlands.html', text: 'Dutch invoices' };
        }
        if (id === 'BR-55' || /^BR-CN-/.test(id)) {
            return { href: 'credit-notes.html', text: 'Credit notes' };
        }
        if (/^BR-CO-/.test(id)) {
            return { href: 'validation.html', text: 'Validation' };
        }

        return null;
    }

    function loadStylesheet(file) {
        if (!stylesheets[file]) {
            stylesheets[file] = fetch(BASE + file).then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load the rules (' + file + '): HTTP ' + response.status);
                }

                return response.text();
            });
        }

        return stylesheets[file];
    }

    function readInput() {
        if (fileInput.files && fileInput.files.length > 0) {
            return fileInput.files[0].text();
        }

        return Promise.resolve(textInput.value);
    }

    function partyCountry(doc, party) {
        var parties = doc.getElementsByTagNameNS(CAC, party);

        if (parties.length === 0) {
            return '';
        }

        var codes = parties[0].getElementsByTagNameNS(CAC, 'PostalAddress');
        if (codes.length === 0) {
            return '';
        }

        var id = codes[0].getElementsByTagNameNS(CBC, 'IdentificationCode');

        return id.length > 0 ? id[0].textContent.trim().toUpperCase() : '';
    }

    function failures(svrlText, ruleset) {
        var svrl = new DOMParser().parseFromString(svrlText, 'application/xml');
        var found = [];
        var asserts = svrl.getElementsByTagNameNS(SVRL, 'failed-assert');

        for (var i = 0; i < asserts.length; i++) {
            var node = asserts[i];
            var text = node.getElementsByTagNameNS(SVRL, 'text');

            found.push({
                id: node.getAttribute('id') || '',
                flag: node.getAttribute('flag') || 'fatal',
                location: node.getAttribute('location') || '',
                text: text.length > 0 ? text[0].textContent.replace(/\s+/g, ' ').trim().replace(/^\[[^\]]+\]\s*-?\s*/, '') : '',
                ruleset: ruleset
            });
        }

        return found;
    }

    /* "/*:Invoice[namespace-uri()='...'][1]/*:InvoiceLine[...][2]" becomes "/InvoiceLine[2]" */
    function shortLocation(location) {
        var path = location
            .replace(/\*:([A-Za-z]+)\[namespace-uri\(\)='[^']*'\]/g, '$1')
            .replace(/\[1\]/g, '')
            .replace(/^\/(Invoice|CreditNote)/, '');

        return path === '' ? 'whole document' : path;
    }

    function countryNotice(choice, seller, buyer) {
        var country = COUNTRIES[choice];
        var sellerText = seller || 'unknown';

        if (country.prefix === null) {
            if (choice === 'BE') {
                return 'Belgium has no national rules of its own in PEPPOL BIS Billing 3.0: a Belgian receiver checks the common rules, which ran in full. The seller is in ' + sellerText + ', the buyer in ' + (buyer || 'unknown') + '.';
            }

            return 'The common rules ran in full. National rules run by themselves when the seller is in a country that has them.';
        }

        var applies = country.when === 'both' ? seller === choice && buyer === choice : seller === choice;

        if (applies) {
            return 'The national rules of ' + country.name + ' (' + country.prefix + ') ran, because the seller' + (country.when === 'both' ? ' and the buyer are' : ' is') + ' in ' + country.name + '.';
        }

        return 'The national rules of ' + country.name + ' (' + country.prefix + ') did not run: they apply only when the seller' + (country.when === 'both' ? ' and the buyer are' : ' is') + ' in ' + country.name + ', and this seller is in ' + sellerText + '. Check the seller\'s address, or choose the seller\'s country.';
    }

    function render(found, choice, seller, buyer, release) {
        var fatal = found.filter(function (f) { return f.flag !== 'warning'; });
        var warnings = found.filter(function (f) { return f.flag === 'warning'; });
        var html = '';

        if (fatal.length === 0) {
            html += '<p class="text-green-300"><strong>Valid.</strong> No fatal errors' + (warnings.length ? ', ' + warnings.length + ' warning' + (warnings.length === 1 ? '' : 's') : '') + '. A receiver that runs the official rules accepts this document.</p>';
        } else {
            html += '<p class="text-red-300"><strong>Rejected.</strong> ' + fatal.length + ' fatal error' + (fatal.length === 1 ? '' : 's') + (warnings.length ? ' and ' + warnings.length + ' warning' + (warnings.length === 1 ? '' : 's') : '') + '. A receiver refuses this document.</p>';
        }

        html += '<p>' + escape(countryNotice(choice, seller, buyer)) + '</p>';

        if (found.length > 0) {
            html += '<div class="table-wrapper"><table><thead><tr><th>Rule</th><th>Severity</th><th>What is wrong</th><th>Where</th></tr></thead><tbody>';

            found.forEach(function (f) {
                var hint = packageHint(f.id);

                html += '<tr><td><a href="' + ruleUrl(f.id) + '" target="_blank" rel="noopener">' + escape(f.id) + '</a>' +
                    (hint ? '<br><a href="' + hint.href + '">' + escape(hint.text) + '</a>' : '') + '</td>' +
                    '<td>' + escape(f.flag) + '</td>' +
                    '<td>' + escape(f.text) + '</td>' +
                    '<td>' + (shortLocation(f.location) === 'whole document' ? 'whole document' : '<code>' + escape(shortLocation(f.location)) + '</code>') + '</td></tr>';
            });

            html += '</tbody></table></div>';
        }

        html += '<p class="fs-2">Checked with the official ' + RULESETS.map(function (r) { return r.name; }).join(' and ') + ' rules, release ' + escape(release) + '. The UBL schema (XSD) is not checked here.</p>';

        resultBox.innerHTML = html;
    }

    function validate(event) {
        event.preventDefault();
        resultBox.innerHTML = '';
        status('');

        readInput().then(function (xml) {
            xml = String(xml || '').trim();

            if (xml === '') {
                status('Choose a file or paste the XML first.');

                return;
            }

            var doc = new DOMParser().parseFromString(xml, 'application/xml');
            var parseError = doc.getElementsByTagName('parsererror');

            if (parseError.length > 0) {
                var detail = parseError[0].textContent.replace(/\s+/g, ' ').trim();
                var chrome = detail.match(/error on line \d+ at column \d+: [^.]*?(?= Below is|$)/);

                status('This is not well formed XML: ' + (chrome ? chrome[0] : detail.slice(0, 200)));

                return;
            }

            var root = doc.documentElement;

            if (!ROOTS[root.namespaceURI] || ROOTS[root.namespaceURI] !== root.localName) {
                status('This is not a UBL invoice or credit note: the root element is ' + root.nodeName + '. PEPPOL BIS Billing 3.0 expects <Invoice> or <CreditNote> in the UBL 2.1 namespace.');

                return;
            }

            var seller = partyCountry(doc, 'AccountingSupplierParty');
            var buyer = partyCountry(doc, 'AccountingCustomerParty');
            var found = [];
            var chain = Promise.resolve();

            RULESETS.forEach(function (ruleset) {
                chain = chain.then(function () {
                    status('Checking the ' + ruleset.name + ' rules...');

                    return loadStylesheet(ruleset.file);
                }).then(function (stylesheetText) {
                    return window.SaxonJS.transform({
                        stylesheetText: stylesheetText,
                        sourceText: xml,
                        destination: 'serialized'
                    }, 'async');
                }).then(function (output) {
                    found = found.concat(failures(output.principalResult, ruleset.name));
                });
            });

            return chain.then(function () {
                return fetch(BASE + 'release.json').then(function (r) { return r.json(); }).catch(function () { return { release: 'unknown' }; });
            }).then(function (info) {
                status('');
                render(found, countryInput.value, seller, buyer, info.release);
            });
        }).catch(function (error) {
            status('The check could not run: ' + error.message);
        });
    }

    /* Preselect the seller's country; the visitor can still change it */
    function chooseSellerCountry(xml) {
        var doc = new DOMParser().parseFromString(xml, 'application/xml');

        if (doc.getElementsByTagName('parsererror').length > 0) {
            return;
        }

        var seller = partyCountry(doc, 'AccountingSupplierParty');

        if (seller !== '') {
            countryInput.value = COUNTRIES[seller] ? seller : 'XX';
        }
    }

    function loadSample(event) {
        event.preventDefault();
        var file = event.currentTarget.getAttribute('data-sample');

        fetch(BASE + 'samples/' + file).then(function (r) { return r.text(); }).then(function (xml) {
            fileInput.value = '';
            textInput.value = xml;
            chooseSellerCountry(xml);
            status('Example loaded. Press Validate.');
            resultBox.innerHTML = '';
        });
    }

    form.addEventListener('submit', validate);
    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
            textInput.value = '';
            status(fileInput.files[0].name + ' chosen.');
            fileInput.files[0].text().then(chooseSellerCountry);
        }
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-sample]'), function (link) {
        link.addEventListener('click', loadSample);
    });
})();
