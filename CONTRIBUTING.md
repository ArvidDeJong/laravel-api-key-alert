# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/laravel-api-key-alert/issues/new/choose) with the steps to reproduce.
- **Features:** open an issue first. This package stays small on purpose, so let's agree a feature fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/laravel-api-key-alert.git
cd laravel-api-key-alert
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2-8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour.
- Keep the public API compatible within 1.x: the public methods of `ApiKeyAlertManager` and the `ApiKeyAlert` facade (`failed()`, `recovered()`, `isFailing()`, `failure()`, `test()`, `guard()`, `inspectResponse()`, `check()`, `checkEnvironment()`, the `ENVIRONMENT` constant), the `Detector` interface and the protected `$statuses`, `$deniedStatuses` and `messageFrom()` of the shipped detectors, the properties of `ApiKeyFailed`, `ApiKeyRecovered`, `CheckResult`, `Failure` and the mailables, the `CheckStatus` cases, the config keys, the `api-key-alert:check`, `api-key-alert:status` and `api-key-alert:test` commands, their exit codes and the JSON shape of `status --json`, the cache key names and the wording of the log lines.
- Read settings through `Support\ApiKeyAlertConfig`, never with `config('api-key-alert.…')`.
- Nothing may throw into the host app, except `guard()`, which always throws the callback's exception again.
- Write code, comments and messages in English. New translation keys go into both `resources/lang/en` and `resources/lang/nl`.
- Update `docs/`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.
- The documentation in `docs/` is also the website. Don't write `{{ }}` or `{% %}` there; Jekyll would render it.

## Code of conduct

This project follows the [Contributor Covenant](https://github.com/ArvidDeJong/.github/blob/main/CODE_OF_CONDUCT.md).
