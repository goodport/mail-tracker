# Contributing

Contributions are welcome and will be credited.

We accept contributions via pull requests on
[GitHub](https://github.com/goodport/mail-tracker).

## Pull Requests

- **Follow the existing code style.** Run `composer format` before submitting.
- **Add tests.** A behavior change without tests is not accepted.
- **Document any behavior change.** Keep `README.md`, `AGENTS.md`, and
  `CHANGELOG.md` up to date.
- **Follow semantic versioning.** Breaking public API changes require a new
  major version.
- **Create feature branches.** Do not ask maintainers to pull from your default
  branch.
- **One pull request per feature.** Send separate pull requests for unrelated
  changes.
- **Keep a coherent history.** Squash intermediate commits before submitting.

## Running Tests

```bash
composer test
```

Run a single test file:

```bash
vendor/bin/phpunit --no-coverage tests/SentEmailPurgerTest.php
```
