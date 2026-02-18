# Copilot Best Practices (Code Generation and Context)

These guidelines help you get accurate, safe, and maintainable code suggestions from GitHub Copilot when working in this repository. The focus is on prompt quality and project context.

## 1) Start with the right context

- Open the most relevant file before you prompt (service class, validator, or test).
- Keep the related spec or documentation open (see docs/ for country rules).
- Mention the target country and document type early (BE/NL, invoice/credit note).
- If rules are involved, reference the authoritative PEPPOL BIS 3.0 source.

## 2) Write prompts that anchor intent

Prefer prompts that define scope, inputs, outputs, and constraints:

Good

- "Add a method to build Dutch invoice header fields with schemeID rules. Use the existing helper methods and return the DOM element."
- "Generate unit tests for credit note validation. Include BR-55 billing reference and positive amounts."

Less effective

- "Make this better"
- "Add validation"

## 3) Tie suggestions to project structure

Copilot performs best when you mention the exact class or namespace:

- "In UblBeBis3Service, add a helper to set supplier VAT with schemeID logic."
- "In Validation/UblValidator, add a rule for invoice currency presence."

Include existing method names if possible (e.g., `createDocument()`, `addInvoiceHeader()`, `generateXml()`).

## 4) Be explicit about data shape

This package relies on array inputs. Define the expected keys and types in the prompt:

- "Assume invoice data has keys: invoice_number, invoice_date, due_date, currency, supplier, customer, invoice_lines."
- "Each line has: id, quantity, unit_code, unit_price, vat_rate, description."

If the change touches validation, specify whether missing values should throw, default, or log.

## 5) Use incremental prompts

Avoid asking for large refactors in one step. Instead:

1. "Add a helper method to normalize country code inputs."
2. "Update the existing calls to use the helper."
3. "Add a test that covers invalid country codes."

This keeps changes small and easier to review.

## 6) Emphasize compliance constraints

When dealing with PEPPOL rules, include the exact constraint in the prompt:

- "All credit note amounts must be positive (document type 381)."
- "BR-55 requires a billing reference on credit notes."

If unsure, direct Copilot to the PEPPOL BIS docs as the source of truth.

## 7) Request changes that match style

This repository favors clear PHP with explicit method names. Ask Copilot to:

- Keep method signatures consistent with existing services.
- Reuse helpers and constants (e.g., unit codes, validator helpers).
- Avoid introducing new dependencies without justification.

## 8) Ask for tests with expected failures

Prompt for tests that include both valid and invalid cases:

- "Add tests for a missing VAT number and ensure validation fails with a clear message."
- "Add tests for BE and NL schemeID behaviors."

Mention the existing test style (Pest) if needed.

## 9) Review generated code with a checklist

Even good suggestions can be off by one rule. Verify:

- Country-specific behaviors (BE vs NL)
- Rule references and error messages
- XML output structure and required nodes
- Validation logic order (required before optional)

## 10) Ask for change summaries

After a larger change, ask Copilot:

- "Summarize what changed and list any behavior differences."
- "Point out any places where I should update docs or examples."

## 11) Example prompt templates

Add a new helper
"In UblNlBis3Service, add a private helper `normalizeKvKNumber()` that strips spaces and validates length. Use it in the customer party builder. Add a Pest test for valid and invalid input."

Add validation rule
"In Validation/UblValidator, add a rule that rejects missing currency. The error should mention the PEPPOL rule name. Add unit tests for empty and null values."

Add documentation update
"Update docs/netherlands-implementation.md with the new schemeID behavior and add a short note to the README."

## 12) Keep prompts aligned with repo rules

- Use PEPPOL BIS 3.0 documentation as authoritative source.
- Keep invoice and credit note behavior aligned with docs/credit-notes.md.
- Ensure examples under examples/ remain valid after changes.

---

If you want this expanded to include review, testing, or security best practices, let me know.
