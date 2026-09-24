---
title: "PEPPOL validator"
nav_order: 8
description: "Free online PEPPOL BIS Billing 3.0 validator: check a UBL invoice or credit note against the official EN 16931 and PEPPOL rules in your browser. Nothing is uploaded."
---

# PEPPOL validator

Check a UBL invoice or credit note against the official rules a receiver runs: EN 16931 and PEPPOL BIS Billing 3.0, national rules included. It works for any UBL file, whether this package made it or not.

**Your file stays on your computer.** The check runs in your browser; nothing is sent anywhere, so a real invoice with customer data is fine.

<form id="peppol-validator" markdown="0">
  <p>
    <label for="pv-country"><strong>Country of the seller</strong></label><br>
    <select id="pv-country">
      <option value="NL">Netherlands</option>
      <option value="BE">Belgium</option>
      <option value="DE">Germany</option>
      <option value="DK">Denmark</option>
      <option value="GR">Greece</option>
      <option value="IS">Iceland</option>
      <option value="IT">Italy</option>
      <option value="NO">Norway</option>
      <option value="SE">Sweden</option>
      <option value="XX">Another country</option>
    </select>
  </p>
  <p>
    <label for="pv-file"><strong>UBL file</strong></label><br>
    <input type="file" id="pv-file" accept=".xml,application/xml,text/xml">
  </p>
  <p>
    <label for="pv-text"><strong>Or paste the XML</strong></label><br>
    <textarea id="pv-text" rows="8" style="width: 100%; font-family: monospace;" spellcheck="false"></textarea>
  </p>
  <p>
    <button type="submit" class="btn btn-primary">Validate</button>
    Try an example: <a href="#" data-sample="valid-dutch-invoice.xml">a valid Dutch invoice</a> or <a href="#" data-sample="broken-intra-community.xml">an intra-community supply with three errors</a>.
  </p>
  <p id="pv-status" role="status"></p>
  <div id="pv-result" aria-live="polite"></div>
</form>

<script src="assets/validator/SaxonJS2.rt.js"></script>
<script src="assets/js/validator.js"></script>

## What the result means

- **Rejected** means at least one fatal error. A receiver refuses the document, and your access point may refuse to send it.
- **Warning** means the document goes through, but something is unusual. Fix it when you can.
- Each rule code links to its page in the [PEPPOL BIS Billing 3.0 specification](https://docs.peppol.eu/poacc/billing/3.0/). Where this documentation explains the rule, a second link takes you there, for example to [VAT categories](vat-categories.md) for `BR-IC-10`.

## The country

The national rules are part of the PEPPOL rules and run by themselves when the seller is in that country: the Dutch `NL-R` rules for a seller in the Netherlands, and likewise for Denmark, Greece, Iceland, Italy, Norway and Sweden. The German `DE-R` rules run when the seller and the buyer are both in Germany. Belgium has no national rules of its own in PEPPOL BIS Billing 3.0.

Choosing a country tells you whether its rules ran for your file. When you chose the Netherlands and the seller's address says `BE`, the Dutch rules did not run, and the page says so.

## What it does not check

- **The UBL schema (XSD).** The official rules assume a document that fits the schema: an element in the wrong place is not always reported here. A document built with this package is written in schema order.
- **Rules of a receiver or a network beyond PEPPOL BIS**, such as the German XRechnung or the Dutch government profile NLCIUS.
- **Whether the facts are true.** A VAT number in the right format can still be wrong; see [VAT numbers](vat-numbers.md).

To check documents in your own tests or CI, run the same rules offline; see [Validation](validation.md#check-a-document-with-an-official-validator). With this package, `validate()` catches most of these errors while you build; see [Validation](validation.md).

## Sources

The rules are the official validation artefacts of OpenPEPPOL and CEN (EN 16931, licensed under the EUPL 1.2), compiled for the browser without changes. They run on SaxonJS, copyright Saxonica Ltd, distributed under its [licence](assets/validator/SaxonJS-LICENSE.txt).
