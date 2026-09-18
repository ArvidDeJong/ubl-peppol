---
title: FAQ
nav_order: 13
description: Short answers about generating PEPPOL BIS Billing 3.0 invoices with darvis/ubl-peppol, the Dutch and Belgian rules, and validation.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
