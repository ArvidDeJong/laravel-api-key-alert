# Security policy

This package handles API keys and mails about them, so a way to get a key into a mail, a log line or an event counts as a security issue.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/laravel-api-key-alert/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version, and the request or exception that leaks the key.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.

## Out of scope

- A key format the redactor does not know, passed in by the host app as a reason or context. The package strips secret query parameters, bearer tokens and the key formats of Google, OpenAI, Anthropic and Mollie; other secrets are the caller's responsibility, as the [Boost guideline](resources/boost/guidelines/core.blade.php) says.
- An alert that does not arrive because the throttle window is still open, the cache was cleared or the mailer is down. That is documented in [Troubleshooting](https://arviddejong.github.io/laravel-api-key-alert/troubleshooting.html), not a vulnerability.
