# Security policy

This package turns your data into an XML document that leaves your application and goes to a tax-relevant recipient, so anything that lets a third party change that document counts as a security issue.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/ubl-peppol/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, your PHP version, and the input that produces the problem. Never include real customer data: invent the names, numbers and amounts, exactly as the examples in the repository do.

Things worth reporting: invoice data that escapes its XML element and adds or changes other elements; a document that passes `validate()` while it violates a rule the package claims to enforce; credentials from the config leaking into a log line, an exception message or the generated document; and an XML payload that makes the parser read a local file or hang.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

## Out of scope

`validate()` checks the business rules this package implements, not the receiver's full Schematron, so a document it accepts can still be rejected. That is a bug worth an issue, not a vulnerability.

A VIES lookup that returns "valid" says the VAT number exists at that moment; it does not prove the number belongs to the party you are invoicing. `CompanyRegistrationService` checks the format and the check digit of a registration number, not whether the company exists. Neither is an identity check, and treating them as one is a limit of the data, not a flaw in the package.

Credentials for your access point provider live in your own config and environment. The package reads them; keeping them out of version control is your side of the line.
