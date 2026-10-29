# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/laravel-api-key-alert` is a Laravel package (PHP 8.2+, Laravel 11/12/13) that mails a configured address when an API key is rejected, and again when it works.

- Namespace: `Darvis\LaravelApiKeyAlert\` → `src/`
- Service provider and the `ApiKeyAlert` facade alias auto-registered via `extra.laravel` in [composer.json](composer.json)
- Config key: `api-key-alert`

## Architecture

- [ApiKeyAlertManager](src/ApiKeyAlertManager.php) is a singleton and holds every decision: `failed()` and `recovered()` are the only paths to a mail; the Http listener (`handleResponse`), the report callback (`handleReportedException`), `guard()`, `inspectResponse()` and `check()` all end there.
- [ApiKeyAlertConfig](src/Support/ApiKeyAlertConfig.php) is the only place that reads the package config. `tests/Feature/PackageTest.php` walks `src/` and `resources/` for `config('api-key-alert.…')`.
- Detectors implement [Detector](src/Detectors/Detector.php). They run on every response from a service's hosts, so they return early and only decode a body when the status or a `str_contains` calls for it.
- State lives in the default cache store: `api-key-alert:down:{service}` (the outage, 30 days) and `api-key-alert:alerted:{service}` (the throttle). `Cache::add()` and the return value of `Cache::forget()` make sure two requests at once send one mail.

## Conventions

- Nothing throws into the host app. Every entry point catches and logs (`API key alert: …`), because it runs inside someone else's request. The one exception is `guard()`, which always throws the callback's exception again; never make it swallow, host code relies on its own `catch` still running.
- The `$busy` flag must cover both the handling and the mail send. A mail driver that talks to an API over HTTP would otherwise alert about its own dead key in a loop; `AlertTest` pins this. A side effect: the events fire while `$busy` is set, so a host listener that calls `failed()` or `recovered()` from inside one is silently ignored. That is intended; don't "fix" it by clearing the flag before the event.
- Everything that reaches a mail, a log line or an event goes through [Redactor](src/Support/Redactor.php) first. Never add a field to the context that skips it.
- The listener and the report callback are registered unconditionally and check their switch on every call, so a config change in a test applies without booting again. The schedule entry reads `check.enabled` when the schedule is built.
- Keep the public API compatible within 1.x: the list is in `CONTRIBUTING.md`. It includes the event properties and the cache key names, because host apps read them.
- Never call a real API from a test. `TestCase` calls `Http::preventStrayRequests()`.
