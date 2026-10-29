---
title: "FAQ"
nav_order: 12
description: "Short answers about darvis/laravel-api-key-alert: what it detects, which APIs it knows, how many mails you get, privacy of the key, costs and testing."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
