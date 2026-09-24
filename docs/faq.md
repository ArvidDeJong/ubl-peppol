---
title: "FAQ"
nav_order: 17
description: "Short answers about darvis/ubl-peppol: what it is, PHP and Laravel versions, sending to PEPPOL, credit notes, validation, VIES, the log table and testing."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
