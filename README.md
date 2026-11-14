# PHPStan Test Practices

PHPStan rules that check the **shape** of PHPUnit tests: assertions last, no guard assertions, literal expected values, one verified double per test, spies over mocks.

A test earns trust in two dynamic ways: it fails when the implementation is removed, and it kills mutants (Infection). Both run the suite. This extension adds a cheaper, static level in front of them: it reads the test code and rejects the shapes that make a test lie or hard to read, in seconds, before anything runs. It does not replace the dynamic checks: a well-shaped test can still assert nothing useful.

Why a PHPStan extension and not Mago, Semgrep, a PHP_CodeSniffer standard or a PHPUnit test: see [docs/why-phpstan.md](docs/why-phpstan.md).

## Installation

```bash
composer require --dev fabiensalles/phpstan-test-practices
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) the extension is registered automatically. Without it, include it in your `phpstan.neon`:

```neon
includes:
    - vendor/fabiensalles/phpstan-test-practices/extension.neon
```

Then analyse your tests (the rules only look at classes extending `PHPUnit\Framework\TestCase`, so analysing `src/` alongside is harmless):

```neon
parameters:
    paths:
        - tests
```

The rules run at any PHPStan level. Your test base classes (Symfony `KernelTestCase`, etc.) must be discoverable by PHPStan, otherwise the test class is not recognised as a `TestCase` and is silently skipped.

## Errors and warnings

PHPStan 2 has no severity: every reported error fails the analysis. This extension adds two levels:

- **error**: reported, exit code 1.
- **warning**: reported, exit code 0 when no error remains.

The extension sets PHPStan's `errorFormat` parameter to `testPractices`, an [error formatter](https://phpstan.org/developing-extensions/error-formatters) that prints the findings with an inner formatter (`table` by default), then returns 0 when every remaining finding is a warning. Each rule attaches its severity to the error as metadata. Any other PHPStan error, an internal error, or a configuration error keeps the inner formatter's exit code.

```text
 ------ ---------------------------------------------------------------------
  Line   WarningOnlyTest.php
 ------ ---------------------------------------------------------------------
  19     Test testLogsWithAMock() calls expects(), which sets a verification
         before the act (mock). Prefer a spy verified after the act.
         🪪  testPractices.spyOverMock
 ------ ---------------------------------------------------------------------

 [ERROR] Found 1 error

Test practices: 0 blocking error(s), 1 non-blocking warning(s).
```

Passing `--error-format=<name>` on the command line bypasses the severity formatter, and every finding then fails the build. To keep severities with another output (for instance GitHub annotations in CI), set the inner formatter instead:

```neon
parameters:
    testPractices:
        errorFormat: github
```

If your configuration already sets `errorFormat`, set it back to `testPractices` and move your value to `testPractices.errorFormat`.

## Configuration

All parameters with their defaults:

```neon
parameters:
    testPractices:
        level: max
        errorFormat: table
        diffBase: null
        severity:
            assertionsLast: error
            noGuardAssertion: error
            noComputedExpectedValue: error
            noProductionConstantAsExpected: error
            oneVerifiedDoublePerTest: error
            spyOverMock: warning
            preferInMemoryDouble: warning
        preferInMemoryDouble:
            threshold: 2
        assertionCount:
            warningFrom: 3
            errorFrom: 4
```

| Parameter | Values | Meaning |
|---|---|---|
| `level` | `0` to `3`, `max` | Which rules run, see [levels](#levels) |
| `errorFormat` | any PHPStan formatter name | Formatter that prints the findings |
| `diffBase` | `null`, any git revision | Report only the findings on lines changed since this revision, see [diff mode](#diff-mode) |
| `severity.<rule>` | `error`, `warning` | Severity of each rule |
| `preferInMemoryDouble.threshold` | int ≥ 1 | Number of test methods doubling the same class before it is reported |
| `assertionCount.warningFrom` | int ≥ 1 | Assertions per test from which a warning is reported |
| `assertionCount.errorFrom` | int ≥ 1 | Assertions per test from which an error is reported |

## Rules

A test method is a public, non-static method of a `TestCase` subclass named `test*`, or carrying `#[Test]` or `@test`. Data providers (static) are never treated as tests. Assertions are `assert*` calls through `self::`, `static::`, `SomeClass::` or `$this->`, and Prophecy spy verifications (`shouldHaveBeenCalled()`, `shouldHaveBeenCalledTimes()`, `shouldHaveBeenCalledOnce()`, `shouldNotHaveBeenCalled()`, `shouldHave()`).

Each rule has its own page: what it detects, a failing example with the real PHPStan output, a passing example, the reasoning behind it, its known limits.

| Identifier | Level | Default | Rationale |
|---|---|---|---|
| [`testPractices.assertionsLast`](docs/rules/assertionsLast.md) | 0 | error | Arrange, act, assert: an assertion before the act, or hidden in an arrange callback, reads as a step of the scenario and fails with a confusing message |
| [`testPractices.noComputedExpectedValue`](docs/rules/noComputedExpectedValue.md) | 0 | error | An expected value computed with the same logic as the code passes by construction |
| [`testPractices.noGuardAssertion`](docs/rules/noGuardAssertion.md) | 1 | error | A guard (`assertCount`, `assertInstanceOf`, `assertNotNull`, `assertIs*`...) stops the test before the real assertion shows its diff |
| [`testPractices.noProductionConstantAsExpected`](docs/rules/noProductionConstantAsExpected.md) | 1 | error | Comparing a production constant with itself never fails when its value changes |
| [`testPractices.oneVerifiedDoublePerTest`](docs/rules/oneVerifiedDoublePerTest.md) | 2 | error | Several verified doubles mean several intentions in one test |
| [`testPractices.assertionCount`](docs/rules/assertionCount.md) | 2 | 3: warning, 4+: error | One structured assertion reads better and fails with a complete diff; many assertions usually hide several intentions |
| [`testPractices.spyOverMock`](docs/rules/spyOverMock.md) | 3 | warning | A verification set before the act (mock) breaks arrange-act-assert; a spy verifies after the act |
| [`testPractices.preferInMemoryDouble`](docs/rules/preferInMemoryDouble.md) | 3 | warning | The same dependency doubled in several tests is a sign that a reusable in-memory implementation would be shorter and sturdier |

## Levels

`testPractices.level` selects the rules, from the ones that catch tests passing by construction to the opinionated ones:

| Level | Adds | Why at this level |
|---|---|---|
| 0 | `assertionsLast`, `noComputedExpectedValue` | A computed expectation passes by construction; an assertion in the arrange may never run. Both make a test lie, with almost no false positive |
| 1 | `noGuardAssertion`, `noProductionConstantAsExpected` | Tautologies and hidden diffs, with a few legitimate exceptions (a constant that is the contract, two real expectations) |
| 2 | `oneVerifiedDoublePerTest`, `assertionCount` | Heuristics about intentions per test: valuable, but thresholds and interaction-heavy tests make exceptions more common |
| 3 (`max`, default) | `spyOverMock`, `preferInMemoryDouble` | Design preferences, warnings by default |

```neon
parameters:
    testPractices:
        level: 1
```

The rules do not follow PHPStan's own `level`. PHPStan's levels are cumulative `config.levelN.neon` files that the command includes ([`CommandHelper`](https://github.com/phpstan/phpstan-src/blob/2.1.x/src/Command/CommandHelper.php)); the level reaches the container only as the `%usedLevel%` string, and `conditionalTags` only cast each condition to a boolean ([`ConditionalTagsExtension`](https://github.com/phpstan/phpstan-src/blob/2.1.x/src/DependencyInjection/ConditionalTagsExtension.php)), so NEON cannot express "from level 5". Type strictness and test shape are also unrelated scales. Each rule therefore reads `testPractices.level` and stays silent below its own level.

## Adopting on an existing suite

### Baseline

PHPStan's [baseline](https://phpstan.org/user-guide/baseline) works with every identifier of this extension and with both severities: a baselined error or warning disappears, and the exit code is computed on what remains (`tests/Adoption/AdoptionTest.php` runs the binary to pin this).

1. Start at `level: 0`, generate a baseline, commit it: `vendor/bin/phpstan analyse --generate-baseline`. New tests are checked from now on; existing findings are listed in `phpstan-baseline.neon`, by message, identifier, path and count. Since the message is part of each entry, a reworded message in a new version of this extension un-ignores the finding: regenerate the baseline after upgrading, or replace the `message` keys by the `identifier` alone.
2. Fix the baseline down over time, then raise the level and regenerate.
3. Keep `reportUnmatchedIgnoredErrors` (on by default) so a fixed finding has to leave the baseline.

To exclude a whole legacy directory from one rule instead, use `ignoreErrors` with an `identifier` and a `path`.

### Diff mode

`testPractices.diffBase` reports only the findings on lines changed since a git revision, for instance in a pull request job:

```neon
parameters:
    testPractices:
        diffBase: origin/main
```

The error formatter runs `git diff --unified=0 <diffBase>` in the working directory and keeps the findings whose line is in a changed hunk (added or modified lines, staged or not; untracked files are not in the diff until `git add -N`). The filter applies to every PHPStan error printed by the formatter, not only to this extension's. The analysis itself is unchanged: PHPStan still analyses all the configured paths, so the result cache stays valid and `preferInMemoryDouble` still sees the whole hierarchy. A `preferInMemoryDouble` finding is kept as soon as its file changed, since its line points to the first creation of the dependency, not to the test that was added. This needs PHPStan 2.1.41 or later (`AnalysisResult::withFileSpecificErrors()`); older versions report a configuration error.

Limiting the analysed files instead is possible with plain PHPStan, at a cost:

```bash
git diff --name-only --diff-filter=AM origin/main -- tests | xargs -r vendor/bin/phpstan analyse --no-progress
```

It is faster on a cold cache but reports every finding of the changed files, and `preferInMemoryDouble` only sees the parents and traits that are among them. PHPStan has no native option for either: its result cache re-analyses only changed files and their dependents, but still reports every finding.

## Ignoring a finding

Use PHPStan's usual mechanisms with the rule identifier:

```php
self::assertSame(Config::DEFAULT_LIMIT, $limit); // @phpstan-ignore testPractices.noProductionConstantAsExpected
```

```neon
parameters:
    ignoreErrors:
        -
            identifier: testPractices.spyOverMock
            path: tests/Legacy/*
```

A baseline works as well for an existing suite, see [adopting on an existing suite](#adopting-on-an-existing-suite).

## Compatibility

Tested in this repository's CI matrix and locally with Docker:

| | Lowest tested | Highest tested |
|---|---|---|
| PHP | 8.2 | 8.4 |
| PHPStan | 2.1.0 | 2.3.5 |
| PHPUnit 11 (fixtures and test suite) | 11.5.50 | 11.5.58 |
| PHPUnit 12 (fixtures and test suite, PHP 8.3+) | 12.5.8 | 12.5.44 |
| Prophecy (`phpspec/prophecy`) | 1.19.0 | 1.26.1 |
| `phpspec/prophecy-phpunit` | 2.3.0 | 2.5.0 |

The matrix runs PHP 8.2 with PHPUnit 11, and PHP 8.3 and 8.4 with both PHPUnit 11 and 12, each with lowest and highest dependencies. The lowest PHPUnit versions are the oldest ones Composer installs: earlier releases are blocked by a security advisory.

The rules read the syntax of PHPUnit and Prophecy calls; they do not depend on a PHPUnit or Prophecy version at runtime. PHPUnit 12 no longer reads the `@test` annotation, but the rules still treat a method carrying it as a test.

## License

MIT, see [LICENSE](LICENSE).
