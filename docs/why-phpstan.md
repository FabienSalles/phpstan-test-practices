# Why PHPStan

This extension checks the shape of PHPUnit tests: assertions after the act, no guard assertions, literal expected values, no production constant as expected value, one verified double per test, spies over mocks, reusable in-memory doubles, few assertions per test. This page explains why it is a PHPStan extension and not a Mago extension, a Semgrep ruleset, a PHP_CodeSniffer standard or something else, and when one of those is the better choice.

Facts below were checked on the linked page on 2026-11-14. "Unverified" marks what was not.

## What a candidate tool needs

The eight rules ask more of a host than "match this pattern":

- **Test-method detection.** A test is a public, non-static method of a `TestCase` subclass named `test*` or carrying `#[Test]` or `@test`. "Subclass of `TestCase`" goes through the class hierarchy, including base classes such as Symfony's `KernelTestCase`.
- **An AST with statement order.** `assertionsLast`, `noGuardAssertion` and `spyOverMock` compare the position of statements inside one method body. `assertionCount` and `oneVerifiedDoublePerTest` count calls.
- **Types and reflection, for some rules.** `noProductionConstantAsExpected` must know that `Foo::BAR` is a class constant and not an enum case, that `Foo` is not a built-in class nor the test class, and which argument of the assertion is the `$expected` parameter (read from the assertion method's signature, so it works with named arguments).
- **Cross-file data.** `preferInMemoryDouble` counts the test methods that double the same class across a concrete test class, its abstract parents, its traits and the helpers they call. The data lives in several files.
- **Two severities.** `spyOverMock` and `preferInMemoryDouble` are warnings by default: reported, without failing the build.
- **Baseline and ignore by rule identifier.** Adopting the rules on an existing suite needs a baseline; a deliberate exception needs a line-level ignore that names the rule.

## Summary

| Tool | Group | Can host the eight rules | Verdict |
|---|---|---|---|
| Mago | Static analyser | Lint rules: syntax only. Analyzer plugins: typed, young API | Closest alternative |
| Psalm plugins | Static analyser | Yes | Works, no gain over PHPStan |
| PHP_CodeSniffer sniffs | Linter | Partly, on tokens | Wrong level |
| PHP-CS-Fixer fixers | Fixer | No | Formatting tool |
| Rector | Refactoring | Not its model | Use it to rewrite, not to gate |
| Semgrep | Pattern matcher | Simple cases only | Good for prototyping a rule |
| ast-grep | Pattern matcher | Simple cases only | Same as Semgrep, fewer operators |
| Comby | Pattern matcher | No | Search and replace |
| tree-sitter queries | Pattern matcher | With a script around it | You write the tool |
| PHPat, Deptrac, PHPArkitect | Architecture | No | Class level, not method bodies |
| Pest `arch()` | Architecture | No | Same |
| ta-tikoma/phpunit-architecture-test | Architecture | No | Same |
| PHPUnit tests that parse the code | Architecture, hand-made | Yes, by hand | You write the tool |
| tsDetect | Test smells | No (Java) | Catalogue, not a PHP tool |
| jorj-sh/php-test-quality | Test smells | Overlaps a little | Complementary |
| symplify/phpstan-rules | PHPStan extension | Other rules | Complementary |
| phpstan/phpstan-phpunit | PHPStan extension | Other rules | Complementary |
| phpstan/phpstan-strict-rules | PHPStan extension | Nothing on tests | Unrelated |
| ergebnis/phpstan-rules | PHPStan extension | One test rule | Complementary |
| spaze/phpstan-disallowed-calls | PHPStan extension | Bans calls, no order | Complementary |
| ikvasnica/phpstan-clean-test | PHPStan extension | Other rules, PHPStan 1 | Not installable with PHPStan 2; ideas reimplemented here |
| haspadar/phpstan-rules | PHPStan extension | No test rule yet | Complementary |
| PHPUnit strictness options | Runtime | Different checks | Turn them on as well |
| Infection, implementation removal | Runtime | Different proof | Complementary |

## Other static analysers

### Mago

[Mago](https://mago.carthage.software/latest/en/tools/overview/) is a PHP analyser, linter and formatter written in Rust. Custom rules arrived in [1.47.0](https://github.com/carthage-software/mago/releases/tag/1.47.0) with its Extension API ("language-neutral workers, a PHP SDK, custom lint rules, analyzer plugins"). Its linter already has four PHPUnit rules (`assertion-style`, `strict-assertions`, `use-specific-assertions`, `prefer-test-attribute`, see the [rules index](https://mago.carthage.software/latest/en/tools/linter/rules/)), none about test structure.

- **What it could do.** Mago has what PHPStan lacks natively: per-rule `level` (`error`, `warning`, `help`, `note`) and a linter baseline ([configuration reference](https://mago.carthage.software/latest/en/tools/linter/configuration-reference/)). A custom lint rule could implement `assertionsLast`, `noGuardAssertion`, `assertionCount`, `spyOverMock` and `oneVerifiedDoublePerTest`, provided "is a test class" is approximated by a name convention. Analyzer plugins go further: their hooks "can report issues", including "resolved method-call" hooks that run after native analysis ([analyzer plugins](https://mago.carthage.software/latest/en/extensions/analyzer/overview/)).
- **What it lacks.** The docs are explicit about lint rules: "A custom linter rule inspects syntax. It does not receive inferred analyzer types or whole-codebase metadata", and "Use an analyzer plugin when a decision depends on semantic types, inheritance, or cross-file references" ([linter extensions](https://mago.carthage.software/latest/en/extensions/linter/overview/)). So `noProductionConstantAsExpected` and real `TestCase` detection need an analyzer plugin. Whether an analyzer plugin can aggregate data across files the way `preferInMemoryDouble` needs is unverified. The API is a few months old, and rules are scoped by `exclude` only (no "only `tests/`").
- **Verdict.** The serious alternative, best placed if your project already runs Mago; the typed part would need its youngest API.

### Psalm plugins

Psalm plugins implement event handlers such as `AfterMethodCallAnalysisInterface`, `AfterStatementAnalysisInterface` or `AfterFunctionLikeAnalysisInterface` ([authoring plugins](https://psalm.dev/docs/running_psalm/plugins/authoring_plugins/)). [psalm/psalm-plugin-phpunit](https://github.com/psalm/psalm-plugin-phpunit) teaches Psalm PHPUnit's conventions (providers, mocks); it does not check test style.

- **What it could do.** Everything: typed AST, reflection, a function-like hook to read a whole test method.
- **What it lacks.** Nothing blocking. A cross-file aggregation equivalent to PHPStan collectors is unverified.
- **Verdict.** Viable if your CI runs Psalm and not PHPStan; otherwise no reason to pick it.

## Linters and fixers

### PHP_CodeSniffer custom sniffs

A sniff registers token types and is called with the file and a position in the token stack ([coding standard tutorial](https://github.com/PHPCSStandards/PHP_CodeSniffer/wiki/Coding-Standard-Tutorial)).

- **What it could do.** Count `assert` tokens in a method, find a token after another one.
- **What it lacks.** No AST, no types, no hierarchy. "Statement order inside a method" means walking tokens and brace levels by hand. No cross-file data.
- **Verdict.** Wrong level of abstraction for these rules.

### PHP-CS-Fixer custom fixers

Custom fixers implement `FixerInterface` ([custom rules](https://cs.symfony.com/doc/custom_rules.html)). The built-in set has 23 `php_unit_*` fixers ([rules index](https://cs.symfony.com/doc/rules/index.html)), such as `php_unit_strict`, `php_unit_dedicate_assert` or `php_unit_data_provider_static`.

- **What it could do.** Rewrite local syntax: `$this->assert*` to `self::assert*`, `assertEquals` to `assertSame`.
- **What it lacks.** A fixer rewrites; it has no notion of "report and let a human decide". Nothing on statement order, double count or types.
- **Verdict.** Keep it for formatting, not for these rules.

### Rector

Rector rules declare node types and return a changed node ([custom rule](https://getrector.com/documentation/custom-rule)). The [PHPUnit set](https://getrector.com/find-rule?activeRectorSetGroup=phpunit) includes `RemoveExpectAnyFromMockRector` and `PropertyCreateMockToCreateStubRector`.

- **What it could do.** Automate the mechanical fixes some of these rules suggest.
- **What it lacks.** Most findings here have no safe automatic rewrite (moving an assertion after the act, replacing a mock with an in-memory double). Using `--dry-run` as a CI gate is common practice, unverified in Rector's docs for this page.
- **Verdict.** A refactoring tool, not a gate for test shape.

## Pattern matchers

### Semgrep

PHP is "Generally available" ([supported languages](https://docs.semgrep.dev/supported-languages)). Rules combine `pattern-inside`, `pattern-not`, `metavariable-regex` and `metavariable-comparison` ([rule syntax](https://docs.semgrep.dev/writing-rules/rule-syntax)); the ellipsis matches a sequence of statements but "cannot jump between nested and outer statement blocks" ([pattern syntax](https://docs.semgrep.dev/writing-rules/pattern-syntax)). `--error` exits 1 on findings ([CLI reference](https://docs.semgrep.dev/cli-reference)).

- **What it could do.** `self::$A(...); ... $sut->$M(...);` expresses "assertion before act" when the act is recognisable by name; two `createMock` calls on different classes express "two doubles".
- **What it lacks.** No count ("3 is a warning, 4 an error"), no types (enum or constant, subclass of `TestCase`), no cross-file aggregation. These rules were composed from the documented primitives, not executed.
- **Verdict.** Useful to prototype a rule in YAML before writing it in PHP, not as the gate.

### ast-grep

PHP is in the [language list](https://ast-grep.github.io/reference/languages.html). Rules use relational keys (`inside`, `has`, `follows`, `precedes`) and composites (`all`, `any`, `not`, `matches`); there is no counting feature ([rule reference](https://ast-grep.github.io/reference/rule.html)). `scan` exits 1 when an `error` rule matches ([scan](https://ast-grep.github.io/reference/cli/scan.html)).

- **What it could do.** `follows`/`precedes` cover ordering cases like Semgrep's ellipsis.
- **What it lacks.** Counting, types, cross-file data.
- **Verdict.** Same place as Semgrep.

### Comby

Comby matches templates with holes, aware of brackets, strings and comments ([overview](https://comby.dev/docs/overview)).

- **What it could do.** Find a call shape.
- **What it lacks.** No notion of a method, statement order or type.
- **Verdict.** Search and replace, not analysis.

### tree-sitter queries

[tree-sitter-php](https://github.com/tree-sitter/tree-sitter-php) gives a concrete syntax tree; queries filter it with predicates such as `#eq?`, `#match?`, `#any-of?`, and no count predicate ([predicates](https://tree-sitter.github.io/tree-sitter/using-parsers/queries/3-predicates-and-directives.html)).

- **What it could do.** Anything, with a script around the queries to count and compare offsets.
- **What it lacks.** Types, reflection, reporting, baseline, ignores: all to write.
- **Verdict.** You end up writing the tool PHPStan already is.

## Architecture tools

### PHPat, Deptrac, PHPArkitect

[PHPat](https://www.phpat.dev/documentation/assertions/) asserts on classes (`BeFinal`, `Extend`, `DependOn`, `HaveOnlyOnePublicMethod`...). [Deptrac](https://deptrac.github.io/deptrac/collectors/) groups classes into layers with collectors and checks dependencies between layers. [PHPArkitect](https://github.com/phparkitect/arkitect) offers namespace, naming, inheritance and type expressions.

- **What they could do.** Class-level conventions on tests: test classes final, in a `Tests\` namespace, not depending on some infrastructure.
- **What they lack.** None of them reads a method body.
- **Verdict.** They answer "who depends on whom", not "how many doubles in this test".

### Pest `arch()`

[Arch testing](https://pestphp.com/docs/arch-testing) provides expectations such as `toExtend()`, `toOnlyUse()`, `toUseStrictTypes()`, `toHaveLineCountLessThan()`.

- **What it lacks.** Nothing about method bodies, mocks or assertion order; it also runs inside the Pest runner.
- **Verdict.** Same as the tools above.

### ta-tikoma/phpunit-architecture-test

[phpunit-architecture-test](https://github.com/ta-tikoma/phpunit-architecture-test) adds PHPUnit assertions on layers: `assertDependOn`, `assertDoesNotDependOn`, argument and return types, `assertMethodSizeLessThan`, `assertHasNotPublicProperties`.

- **What it lacks.** No analysis of method implementations beyond their size.
- **Verdict.** Architecture checks written as tests; not for test shape.

### PHPUnit tests that parse the code

Frédéric Bouchery's Forum PHP 2021 talk, [Des tests unitaires pour nos règles de conception](https://fr.slideshare.net/slideshow/des-tests-unitaires-pour-nos-rgles-de-conception/250539867), argues for unit tests that enforce design rules. The exact technique in the slides (parser, reflection) could not be read from the page: unverified.

- **What it could do.** A test that parses `tests/` with a PHP parser can implement any rule, in the team's own test suite, with no new tool.
- **What it lacks.** Types, collectors, baseline, ignore by identifier, severities and IDE integration are yours to build. One failing test per rule, not one finding per line.
- **Verdict.** Fine for one or two house rules; past that it becomes a hand-made PHPStan.

## Test-smell tools

### tsDetect

[TestSmellDetector](https://github.com/TestSmells/TestSmellDetector) detects the [19 smells of testsmells.org](https://testsmells.org/pages/testsmells.html) (Assertion Roulette, Conditional Test Logic, Eager Test, Magic Number Test...) in Java/JUnit projects, with CSV input and output.

- **What it could do.** Its catalogue maps onto some rules here: Eager Test is close to `oneVerifiedDoublePerTest` and `assertionCount`, Assertion Roulette to `assertionCount`.
- **What it lacks.** It does not read PHP.
- **Verdict.** A reference catalogue, not a tool for a PHP project.

### jorj-sh/php-test-quality

[php-test-quality](https://github.com/jorjives/php-test-quality) ([Packagist](https://packagist.org/packages/jorj-sh/php-test-quality)) is an "AST-based test quality analyser for PHPUnit tests" detecting about 16 smells (no assertion, conditional logic, magic numbers, sleep, debug output, long tests, tautological assertions...). It has a baseline, a `.tq.yaml` configuration and JSON output. v1.3.2, PHP >= 8.4, 7 installs on Packagist.

- **What it could do.** Smells this extension does not cover.
- **What it lacks.** None of its smells matches the eight rules here; whether it uses inferred types is unverified. It is a separate tool with its own configuration, and it requires PHP 8.4.
- **Verdict.** Complementary; young.

## Other PHPStan extensions

These run in the same PHPStan process and add to this extension rather than replace it.

- **[symplify/phpstan-rules](https://github.com/symplify/phpstan-rules).** `NoAssertFuncCallInTestsRule`, `PublicStaticDataProviderRule`, and with `symplify: mocks: true` a mock ruleset (`AvoidAnyExpectsRule`, `NoWithOnStubRule`, `NoEntityMockingRule`, `NoDoubleConsecutiveTestMockRule`...). Nothing on statement order, guards or expected values. Verdict: complementary, enable the mock set. Its `NoEntityMockingRule` (and `NoDocumentMockingRule` for Doctrine ODM) is a partial alternative to [`noDoubleOfEntityOrValueObject`](rules/noDoubleOfEntityOrValueObject.md): it covers Doctrine documents and entities, not enums, readonly classes or configured value object namespaces.
- **[phpstan/phpstan-phpunit](https://github.com/phpstan/phpstan-phpunit).** Types for mocks and assertions, plus strict rules on `assertSame` with booleans, `null` or `count()`, `assertEquals` on same types, `assertEmpty`. Verdict: complementary, install it for the types anyway.
- **[phpstan/phpstan-strict-rules](https://github.com/phpstan/phpstan-strict-rules).** Type strictness for all code; nothing test-specific. Verdict: unrelated.
- **[ergebnis/phpstan-rules](https://github.com/ergebnis/phpstan-rules).** One test rule, `TestCaseWithSuffixRule`. Verdict: complementary.
- **[spaze/phpstan-disallowed-calls](https://github.com/spaze/phpstan-disallowed-calls).** Bans functions, methods, classes, constants, namespaces, with `allowInPaths`, `allowInInstanceOf` and other scopes. It can ban `ReflectionMethod::setAccessible()` in tests or `sleep()`; it cannot express order or count. Verdict: complementary. It is a partial alternative to [`noRandomness`](rules/noRandomness.md) and [`noReflectionOnSut`](rules/noReflectionOnSut.md): it can ban `random_int()` or `ReflectionProperty::setValue()` in `tests/`, but it cannot tolerate a seeded Faker, nor tell a `ReflectionMethod` from another `invoke()` without a configured class, and its message does not say what to do instead.
- **[ikvasnica/phpstan-clean-test](https://github.com/ikvasnica/phpstan-clean-test).** `DisallowSetupAndConstructorRule`, `AssertSameOverAssertEqualsRule`, `StaticAssertOverThisAndStaticRule`, `UnitExtendsFromTestCaseRule`, `NoNullableArgumentRule`. Its `composer.json` requires `phpstan/phpstan: ^1.10`, last push 2023-08-23. Verdict: not installable with PHPStan 2. This extension reimplements the ideas it lacked: the constructor half of `DisallowSetupAndConstructorRule` became [`noTestCaseConstructor`](rules/noTestCaseConstructor.md), the `setUp()` half is covered more narrowly by [`noFixtureInSetUp`](rules/noFixtureInSetUp.md) (fixtures and doubles, not the environment), and `UnitExtendsFromTestCaseRule` by [`noKernelForPureFunction`](rules/noKernelForPureFunction.md). The other two are already in `phpstan/phpstan-phpunit` and PHP-CS-Fixer.
- **[haspadar/phpstan-rules](https://github.com/haspadar/phpstan-rules).** General design and documentation rules (complexity, coupling, nulls, comments such as `NoInlineCommentRule`), with a `testsPaths` parameter that keeps production rules off the tests; its README announces test-oriented rules "when introduced", and none exists yet (checked 2026-11-15). Verdict: complementary, nothing to take for test shape.

## Runtime checks

### PHPUnit strictness options

PHPUnit 11.5 and 12.5 both document `failOnRisky`, `failOnWarning`, `failOnEmptyTestSuite`, `beStrictAboutTestsThatDoNotTestAnything` (default `true`), `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState`, `beStrictAboutCoverageMetadata` and `enforceTimeLimit` ([11.5](https://docs.phpunit.de/en/11.5/configuration.html), [12.5](https://docs.phpunit.de/en/12.5/configuration.html)); 12.5 also documents `requireCoverageMetadata`.

- **What they do.** Mark or fail tests that assert nothing, print output, touch global state, take too long.
- **What they lack.** They observe a run, not the code: an assertion before the act or a computed expected value passes them.
- **Verdict.** Turn them on as well; they check other things.

### Infection and the implementation-removal check

Two dynamic proofs sit above this extension. Removing the implementation must make the test fail. Mutation testing ([Infection](https://infection.github.io/guide/mutators.html), with removal mutators such as `MethodCallRemoval` and `ReturnRemoval`) shows that the assertion discriminates.

- **What they do.** Prove the test depends on the code and that its assertion is sharp.
- **What they lack.** Speed, and an opinion about shape: a test with five assertions before the act can kill every mutant.
- **Verdict.** Complementary, not competitors. Static shape (seconds, before running), implementation removal (the test depends on the code), mutation (the assertion is precise): three levels, no overlap.

## Why PHPStan

- **Inferred types and reflection.** Rules receive a `Scope` that resolves expression types and gives class reflection ([custom rules](https://phpstan.org/developing-extensions/rules)). This is how `TestMethod::isTestCase()` follows the hierarchy and how `noProductionConstantAsExpected` excludes enums, built-in classes and the test class, and finds the `$expected` parameter through the assertion method's signature.
- **Collectors for cross-file rules.** "All of the data collected by collectors is gathered into a single object `CollectedDataNode`" and processed in the main process ([collectors](https://phpstan.org/developing-extensions/collectors)). `preferInMemoryDouble` uses two collectors to follow parent classes, traits and helpers.
- **Baseline and ignore by identifier, native.** `--generate-baseline` ([baseline](https://phpstan.org/user-guide/baseline)), `// @phpstan-ignore testPractices.spyOverMock` and `ignoreErrors` by `identifier` and `path` ([ignoring errors](https://phpstan.org/user-guide/ignoring-errors)) work with no extra code.
- **Severity through an error formatter.** PHPStan has no warning level, but a custom [error formatter](https://phpstan.org/developing-extensions/error-formatters) returns the exit code. This extension's formatter returns 0 when only warnings remain.
- **Fix infrastructure.** PHPStan 2.3.5 (the version installed here) has `--fix` ("Fix auto-fixable errors (experimental)") and `RuleErrorBuilder::fixNode()`, marked `@internal Experimental` in the [2.1.x API reference](https://apiref.phpstan.org/2.1.x/source-src.Rules.RuleErrorBuilder.html). None of the rules uses it yet; the version that introduced `--fix` is unverified.
- **Ecosystem.** [phpstan/extension-installer](https://github.com/phpstan/extension-installer) registers the extension on `composer require`; `phpstan-phpunit`, `symplify/phpstan-rules` and `spaze/phpstan-disallowed-calls` run in the same pass.
- **Already in CI.** Most PHP projects already run PHPStan: one more `includes:` line, no new binary, no new job. That last point is an observation, not a measured figure.

## When to use something else

- **Your project runs Mago and not PHPStan.** Port the syntax-only rules to Mago lint rules and keep the typed ones for an analyzer plugin once that API settles. You get native warning levels for free.
- **Your project runs Psalm and not PHPStan.** A Psalm plugin can host the same rules.
- **You want to try a rule before writing PHP.** Write it in Semgrep or ast-grep, run it on your suite, then port it.
- **You need one house rule and refuse a new dependency.** A PHPUnit test that parses `tests/` is enough.
- **Your rule is about classes, not method bodies** (test classes final, namespaces, dependencies). Use PHPat, Deptrac, PHPArkitect or Pest `arch()`.
- **You want to know whether tests catch bugs.** That is Infection and the implementation-removal check, not this extension.
