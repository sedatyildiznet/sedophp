# Contributing

Contributions to SedoPHP should stay small, readable and compatible with the framework's shared-hosting-first philosophy.

## Before proposing a feature

Ask:

1. Can ordinary PHP already solve this clearly?
2. Does the feature belong in the framework core?
3. Can a PHP developer understand the API without learning unnecessary framework-specific language?
4. Can it work without introducing a mandatory production service or heavy dependency?

For larger changes, open a feature request before investing significant implementation time.

## Development setup

```bash
git clone https://github.com/sedatyildiznet/sedophp.git
cd sedophp
cp .env.example .env
composer install
```

Composer is used for development convenience; SedoPHP itself remains usable without Composer on the production server.

## Before submitting a pull request

Run:

```bash
composer lint
composer test
```

The same core suite is exercised in GitHub Actions across supported PHP/database combinations.

## Pull request guidelines

- Keep each PR focused on one problem.
- Preserve backward compatibility within the current stable release line unless a breaking change is explicitly planned.
- Add or update tests for behavior changes.
- Update documentation when public APIs or deployment behavior change.
- Do not commit credentials, tokens, private keys, `.env` files or generated runtime data.
- Prefer clear PHP over abstraction for abstraction's sake.
- Keep optional services optional; shared hosting remains a first-class target.

Use the repository pull-request template and complete its checklist before requesting review.

## Security issues

Do not report vulnerabilities through public issues. Follow [SECURITY.md](SECURITY.md) and use a private GitHub Security Advisory.
