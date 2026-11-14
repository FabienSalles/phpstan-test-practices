# Contributing

## Development setup

With PHP 8.2+ and Composer installed locally:

```bash
composer update
composer ci        # composer validate --strict, PHPUnit, PHPStan on src/
composer test      # PHPUnit only
composer analyse   # PHPStan on src/ at level max
```

Without a local PHP, the Makefile runs the same commands in Docker (the image is built from `docker/Dockerfile`, with Composer and the PHP version you pick):

```bash
make ci                          # PHP 8.4, highest dependencies
make ci PHP=8.2 DEPS=lowest      # the lowest leg of the CI matrix
make test                        # PHPUnit only, on the vendor/ already installed
make analyse                     # PHPStan only
```

`composer ci` is the entry point the GitHub Actions workflow runs on each leg of its matrix (PHP 8.2, 8.3, 8.4 × lowest, highest dependencies).

## Tests

- `tests/Rules/`: one `PHPStan\Testing\RuleTestCase` per rule, analysing a fixture from `tests/Fixtures/Rules/`. Fixture classes are autoloaded through the `autoload-dev` classmap; run `composer dump-autoload` after adding a fixture file.
- `tests/Severity/`: runs the real `vendor/bin/phpstan` on `tests/Fixtures/Severity/` and asserts the exit codes of the severity formatter.
- `tests/Corpus/`: runs the real binary on the training corpus in `tests/Fixtures/Corpus/` and pins every finding (file, line, identifier). A rule change that moves a finding shows up here.

## Adding a rule

1. Write the rule class in `src/Rules/`, `final readonly`, implementing `PHPStan\Rules\Rule`. Scope it to test methods with `TestMethod::isTest()` (or `TestMethod::isTestCase()` for class-level checks).
2. Give every error the identifier `testPractices.<ruleName>`, a message that says what is wrong and what to do instead, a `tip()` when useful, and `->metadata(Severity::metadata($this->severity))`.
3. Register it in `extension.neon` as a service tagged `phpstan.rules.rule`, add `testPractices.severity.<ruleName>` with its default to `parameters`, and its `anyOf('error', 'warning')` entry to `parametersSchema`.
4. Add a fixture in `tests/Fixtures/Rules/` covering flagged and non-flagged cases: PHPUnit doubles and Prophecy, data providers, hand-written or anonymous-class doubles when relevant.
5. Add a `RuleTestCase` in `tests/Rules/` asserting every expected message, line and tip.
6. Run `composer ci`, then update the expected findings in `tests/Corpus/CorpusTest.php` if the corpus is affected, and explain the change in the commit message.
7. Add a row to the rules table in `README.md`, a ❌/✅ example, and the new parameter in the configuration section.

No comments in the code unless a non-obvious constraint demands one: the reasoning goes in the commit message.

## Commits

[Conventional Commits](https://www.conventionalcommits.org/), in English: `feat: ...`, `fix: ...`, `test: ...`, `docs: ...`, `ci: ...`, `chore: ...`. One logical change per commit.
