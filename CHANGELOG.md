# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
