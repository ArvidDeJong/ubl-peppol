---
title: "MCP server"
nav_order: 18
description: "Let Claude or another AI assistant look up PEPPOL BIS Billing 3.0 and EN 16931 rules through the local MCP server of darvis/ubl-peppol: the explain-rule tool, how to add it, and RuleCatalog in PHP."
---

# MCP server

When `laravel/mcp` is installed, the package registers a local (stdio) MCP server with one tool, `explain-rule`. Laravel Boost already requires `laravel/mcp`, so an app with Boost has it. Without `laravel/mcp` the server is simply not registered; everything else keeps working.

The server reads nothing from your application and sends nothing anywhere: the rules are in the package.

## Why

A rejection, an exception or a `validate()` result names a rule id, such as `BR-CO-15`, `NL-R-001` or `UBL-PEPPOL-CN-04`. An assistant that explains such an id from memory is often close and sometimes wrong in the way that matters. `BR-55` is the classic: it does not require a reference to the credited invoice, it only demands that a reference, once present, holds the number. The requirement is `NL-R-001`, and only for a supplier in the Netherlands. `explain-rule` quotes the rule as the specification words it.

## Start it

```bash
php artisan mcp:start ubl-peppol
```

The handle `ubl-peppol` comes from `UBL_PEPPOL_MCP_HANDLE`. Set `UBL_PEPPOL_MCP_ENABLED=false` to leave the server out.

## Add it to your assistant

For Claude Code, add it to `.mcp.json` in the root of the Laravel app:

```json
{
    "mcpServers": {
        "ubl-peppol": {
            "command": "php",
            "args": ["artisan", "mcp:start", "ubl-peppol"]
        }
    }
}
```

For the Claude desktop app and other clients, use the same command with the absolute path to `artisan`, for example `/Users/you/Sites/my-app/artisan`.

## The tool

### explain-rule

Read only. One argument:

| Argument | Type | Required | What it does |
| --- | --- | --- | --- |
| `rule` | string | yes | A rule id, with or without brackets and in any case (`[nl-r-001]` works), or words from an error message to search the rule texts for, such as `billing reference`. |

For an id it answers with:

- where the rule comes from: `CEN` (EN 16931), `PEPPOL`, or this package;
- the flag: `fatal` means the receiver rejects the document, `warning` means it is accepted;
- the rule text, the element it is tested on and the XPath test;
- what the package does about it, for the rules the package deals with itself;
- a link to the rule on docs.peppol.eu, or to these docs for a package rule.

For words it lists the matching rules with their flag and text, so the assistant can ask again with the right id. When nothing matches it returns a tool error.

## Where the rules come from

The official rules are generated from the compiled OpenPEPPOL Schematron that the [PEPPOL validator](validator.md) runs, currently release 2026.5: 1144 rules, every assertion of the CEN and the PEPPOL rule set. They are never written by hand.

Rules starting with `UBL-PEPPOL-` are checks of this package, not of the specification:

| Rule | Flag | What it checks |
| --- | --- | --- |
| `UBL-PEPPOL-CN-01` | fatal | A credit note line amount is not negative |
| `UBL-PEPPOL-CN-02` | fatal | A credited quantity is not negative |
| `UBL-PEPPOL-CN-03` | fatal | The line total of a credit note is not negative |
| `UBL-PEPPOL-CN-04` | fatal | The payable amount of a credit note is not negative |
| `UBL-PEPPOL-CN-05` | warning | A credit note from a supplier outside the Netherlands references the credited invoice |

## The same rules in PHP

`RuleCatalog` is plain PHP, so you can use it without the MCP server, for example to show the rule next to an error in your own screens:

```php
use Darvis\UblPeppol\Rules\RuleCatalog;

$rule = RuleCatalog::find('[BR-CO-15]');

$rule->text;      // 'Invoice total amount with VAT (BT-112) = ...'
$rule->isFatal(); // true
$rule->url();     // 'https://docs.peppol.eu/poacc/billing/3.0/rules/ubl-tc434/BR-CO-15/'

RuleCatalog::search('billing reference'); // list of Rule objects
RuleCatalog::release();                   // '2026.5'
```

## The server does not show up

- Check that `laravel/mcp` is installed: `composer show laravel/mcp`.
- Check that `UBL_PEPPOL_MCP_ENABLED` is not `false`.
- Run `php artisan mcp:start ubl-peppol` yourself; it should wait for input without an error.
- A desktop client needs the absolute path to `artisan`.
