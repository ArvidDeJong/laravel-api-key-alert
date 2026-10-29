# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-10-29

### Added
- `api-key-alert:status`, which lists every service with `ok` or `failing`, since when and the
  provider's reason, from the cache and without API calls. It exits with a failure when a key is
  failing; `--json` prints the same for a dashboard or a monitoring tool.
- `api-key-alert:test`, which sends a test alert and a test recovery mail straight away, without
  the cache, the throttle, the queue or the events. It replaces the tinker one-liner in the docs.
- `ApiKeyAlert::failure($service)`, which returns the current `Failure` (`service`, `reason`,
  `since`) or null, and `ApiKeyAlert::test($service)` behind the command.
- An environment check: `API_KEY_ALERT_ENVIRONMENT_CHECK=true` makes the daily check compare config
  keys with expected values (`app.debug` false and `app.env` `production` by default, editable under
  `environment.expected`) and mail `EnvironmentFailedMail` on every daily check until they are right,
  then `EnvironmentRecoveredMail` once. The alert is the service `environment`, so `isFailing()`,
  `failure()`, the events and `api-key-alert:status` cover it, and `api-key-alert:check environment`
  runs it by hand. `ApiKeyAlert::checkEnvironment()` behind it.
- A check value can read the app's own config with a `config:` reference, such as
  `'config:services.google.key_php'`. Set it in `AppServiceProvider::boot()` and the check uses the
  key the app already has, without publishing the config or repeating the key in `.env`.
- Documentation: "Put it on the server" on the installation page (`.env`, `config:cache`, the
  scheduler and the three commands to check it), "When your key has a different name" on the daily
  check page, and how to load the Boost guideline (`boost.json`, then `boost:install`).
- Documentation pages "Commands", "Environment check" and "Recipes" (Slack, spatie/laravel-health, a warning in the
  admin, a deploy that stops on a dead key), and the commands in the Boost guideline and skill.

## [1.0.0] - 2026-10-29

### Added
- First release. Set `API_KEY_ALERT_MAIL_TO` to get a mail when an API key is rejected, and one when
  it works again.
- Passive detection of rejected keys in Laravel Http client responses and in reported exceptions, for
  Anthropic, Google, MessageBird, Mollie and OpenAI.
- `GoogleDetector`, which reads the HTTP 200 `REQUEST_DENIED` and `OVER_DAILY_LIMIT` answers of the
  Google Maps web services.
- `ApiKeyAlert::guard()` around a call, `ApiKeyAlert::failed()`, `recovered()`, `isFailing()` and
  `inspectResponse()`.
- `api-key-alert:check` and an opt-in daily schedule (`API_KEY_ALERT_CHECK=true`).
- `ApiKeyFailed` and `ApiKeyRecovered` events.
- Mails in English and Dutch.
- Laravel Boost guideline and skill.

[Unreleased]: https://github.com/ArvidDeJong/laravel-api-key-alert/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/ArvidDeJong/laravel-api-key-alert/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/ArvidDeJong/laravel-api-key-alert/releases/tag/v1.0.0
