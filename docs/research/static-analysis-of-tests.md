# Static analysis of PHPUnit test code

Research date: 2026-11-14. Question: is there an executable that fails a build when **test code** breaks well-known testing practices (Arrange-Act-Assert, one verified double per test, no guard assertion, no production constant as expected value, and so on), the way PHPStan, Mago or Deptrac do on their own subjects? If not, what should it be built on?

Short answer: no existing tool covers these practices. PHPStan can host them as custom rules with full type information. Mago can host custom lint rules since 1.47.0, but its documented linter API exposes syntax only. Semgrep handles only the simplest cases. Recommendation in §3.

Conventions: "verified" means read in the official documentation or the cited source code at the research date; "inferred" means deduced, with a stated confidence; "unverified" means not checked.

---

## 1. Rule inventory

### 1.1 Sources

Public literature:

- `[XTP]` Gerard Meszaros, *xUnit Test Patterns*, 2007, and its companion site http://xunitpatterns.com/ (pages cited: [Test Smells](http://xunitpatterns.com/Test%20Smells.html), [Assertion Roulette](http://xunitpatterns.com/Assertion%20Roulette.html), [Conditional Test Logic](http://xunitpatterns.com/Conditional%20Test%20Logic.html), [Obscure Test](http://xunitpatterns.com/Obscure%20Test.html), [Fragile Test](http://xunitpatterns.com/Fragile%20Test.html), [Erratic Test](http://xunitpatterns.com/Erratic%20Test.html), [Four Phase Test](http://xunitpatterns.com/Four%20Phase%20Test.html), [Fresh Fixture](http://xunitpatterns.com/Fresh%20Fixture.html), [Shared Fixture](http://xunitpatterns.com/Shared%20Fixture.html), [Test Spy](http://xunitpatterns.com/Test%20Spy.html), [Mock Object](http://xunitpatterns.com/Mock%20Object.html), [Fake Object](http://xunitpatterns.com/Fake%20Object.html)).
- `[Fowler]` Martin Fowler, [Mocks Aren't Stubs](https://martinfowler.com/articles/mocksArentStubs.html), [Given When Then](https://martinfowler.com/bliki/GivenWhenThen.html), [Eradicating Non-Determinism in Tests](https://martinfowler.com/articles/nonDeterminism.html).
- `[TotT]` Google Testing Blog, Testing on the Toilet: [Don't Put Logic in Tests](https://testing.googleblog.com/2014/07/testing-on-toilet-dont-put-logic-in.html), [Don't Overuse Mocks](https://testing.googleblog.com/2013/05/testing-on-toilet-dont-overuse-mocks.html), [Fake Your Way to Better Tests](https://testing.googleblog.com/2013/06/testing-on-toilet-fake-your-way-to.html), [Test Behavior, Not Implementation](https://testing.googleblog.com/2013/08/testing-on-toilet-test-behavior-not.html), [Change-Detector Tests Considered Harmful](https://testing.googleblog.com/2015/01/testing-on-toilet-change-detector-tests.html).
- `[DMWYDO]` test double, [Don't mock what you don't own](https://github.com/testdouble/contributing-tests/wiki/Don't-mock-what-you-don't-own).
- `[tsDetect]` the test smell catalogue at https://testsmells.org/pages/testsmells.html (see §2.5).

Written conventions, from the public Claude Code marketplace https://github.com/FabienSalles/claude-marketplace:

- `[TP]` [craft/testing-principles](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/testing-principles/SKILL.md): AAA, tautological assertions, spy over mock, structured assertions, real or null collaborators over doubles, `setUp()` kept minimal.
- `[PTC]` [phpunit/php-test-conventions](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/SKILL.md) and its references [assertion-patterns](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/assertion-patterns.md), [test-doubles](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/test-doubles.md), [http-testing](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/phpunit/skills/php-test-conventions/references/http-testing.md): exception test naming, Prophecy spies, permissive stubs, named data provider keys, guard assertions, real serializer.
- `[STC]` [symfony/symfony-test-conventions](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/symfony/skills/symfony-test-conventions/SKILL.md): cheapest base class, built-in `WebTestCase` assertions over hand-rolled crawler reads.
- `[TSD]` [craft/test-suite-design](https://github.com/FabienSalles/claude-marketplace/blob/main/plugins/craft/skills/test-suite-design/SKILL.md): every test file runs in exactly one check, change detectors.

### 1.2 Classification

- **D**: deterministic by AST (no judgement, no false positive by construction, up to a configuration list).
- **H**: heuristic (AST detection with a false-positive risk to document and an escape hatch).
- **N**: not statically detectable (needs mutation testing, a pseudo-tested-method check or a human review).

### 1.3 Test structure

| # | Practice | Sources | Class | Detection logic |
|---|---|---|---|---|
| R1 | Assertions come last; no assertion before the act | [XTP] Four Phase Test, [Fowler] Given When Then, [TP] | **D** | Walk the top-level statements of a test method. Once an assertion statement is seen (`self::assert*`, `$this->assert*`, `->shouldHaveBeenCalled*()`), any later non-assertion statement is a violation. |
| R2 | No assertion hidden in the arrange (inside a `willReturnCallback` closure) | [TP], [PTC] test-doubles | **D** | Any `assert*` inside a top-level statement that is not itself an assertion. Exception: `Argument::that(fn () => self::assertEquals(...))` inside a `shouldHaveBeenCalled()` statement, which is an assertion statement. |
| R3 | No guard assertion (`assertCount`, `assertInstanceOf`, `assertNotNull`, `assertNotEmpty`, `assertArrayHasKey`...) before the real assertion; read the value permissively (`?? null`, `?->`) | [PTC] assertion-patterns | **D** | A list of guard assertions; any occurrence that is not the last assertion of the method is a violation. [TP] allows a result guard in a TypeScript `Result` context; a PHP project wanting that exception can allow a list of types. |
| R4 | One intention per test, a bounded number of assertions | [XTP] Assertion Roulette, [tsDetect] Eager Test | **H** | Count assertion statements; above N (configurable) is a violation. The useful deterministic form is R5. |
| R5 | One structured assertion (`assertEquals` on the whole object, or `assertSame` on two key/value arrays) rather than N assertions on getters of the same object | [TP] §9, [PTC] | **D** | Two consecutive `assertSame`/`assertEquals` whose actual argument is a method call or property fetch on the same root variable. The fix needs judgement (whole object or array), the detection does not. |
| R6 | Readable AAA: blocks separated by blank lines, no inline comments | [TP] | **D** (comments) / **H** (blank lines) | Comments: any comment node in a test body, except `@var`. Blank lines: needs statement line positions; feasible but fragile on multi-line arranges. Existing: `haspadar/phpstan-rules` `NoInlineCommentRule` (§2.1). |
| R7 | No conditional logic in a test (`if`, `match`, loops, ternary) | [XTP] Conditional Test Logic, [TotT] Don't Put Logic in Tests, [tsDetect] | **D** | `Stmt\If_`, `Stmt\For_`, `Stmt\Foreach_`, `Expr\Match_`, `Expr\Ternary` in a test body. Loops in data providers are out of scope (a provider is not a test). |

### 1.4 Test doubles

| # | Practice | Sources | Class | Detection logic |
|---|---|---|---|---|
| R8 | One verified double per test | [TotT] Don't Overuse Mocks, [XTP] Fragile Test | **D** | Count distinct roots carrying an expectation (PHPUnit `->expects(`, Prophecy `->shouldBeCalled*()`, `->shouldHaveBeenCalled*()`). More than one is a violation. Stubs (`willReturn` without expectation) do not count. |
| R9 | Spy over mock: verify after the act, no expectation set before it | [XTP] Test Spy vs Mock Object, [Fowler] Mocks Aren't Stubs, [TP] §6, [PTC] | **D** | Any `->expects(` or `->shouldBeCalled*()` in a test. A mock stays legitimate when the call itself is the expected behaviour, so this one fits a `warning` severity better than an error. |
| R10 | Don't mock what you don't own: no double of `EntityManagerInterface`, `QueryBuilder`, `SerializerInterface`, `HttpClientInterface`, Guzzle `ClientInterface`... | [DMWYDO], [TP] | **D** | `createMock`/`createStub`/`getMockBuilder`/`prophesize` whose argument is `X::class` in a denied namespace (`Doctrine\`, `Symfony\Component\Serializer\`, `Symfony\Contracts\HttpClient\`, `GuzzleHttp\`). Configurable list. Existing: `symplify/phpstan-rules` `NoTestMocksRule` (inverse allowlist), `NormanAlbert91/mock-check` (deny patterns), `spaze/phpstan-disallowed-calls`. |
| R11 | No double of an entity, a value object or a class without I/O | [TP], [TotT] Don't Overuse Mocks | **H** | Deterministic if "entity" is defined by namespace or by the `#[ORM\Entity]` attribute (readable through PHPStan reflection). Existing: `symplify` `NoEntityMockingRule`, `NoDocumentMockingRule`. "Without I/O" is not decidable without a namespace convention. |
| R12 | Do not mock the transformer when the transformation is what the test checks (use a real serializer) | [PTC] http-testing | **H** | Reduced case: the value a stub returns is the value the test then expects from the code under test. General case ("the transformation is the subject") depends on intent: N. |
| R13 | Permissive stub: no `Argument::type()` and no precise values in a stub (`willReturn` without `should*`); use `Argument::any()`/`cetera()` | [PTC] test-doubles | **D** | A Prophecy chain ending in `willReturn*`/`willThrow` without `should*`, whose arguments contain `Argument::type(...)` or a literal. |
| R14 | Prophecy rather than `createMock()` + `willReturnCallback()`; `createMock` only where Prophecy cannot double the class (final class without interface) | [PTC] | **D** | `createMock(...)` followed by `->willReturnCallback(` on the same variable. The final-class exception is checked by reflection (`ClassReflection::isFinal()`). |
| R15 | A real or null implementation over an unverified, unscripted double (`NullLogger`, in-memory repository, fixed clock) | [XTP] Fake Object, [TotT] Fake Your Way to Better Tests, [TP], [PTC] test-doubles | **D** / **H** | A double never scripted nor verified, of a type with a known null or in-memory implementation (configurable list, e.g. `Psr\Log\LoggerInterface` to `NullLogger`). |
| R16 | No production logic reimplemented in a double | [TP] | **H** | A `willReturnCallback`/`will(function ...)` closure containing `if`/`match`/arithmetic. False positives on legitimate fakes. |
| R17 | Mock arguments verified in only one test of the suite | [XTP] Fragile Test | **H** | Cross-test rule: a collector records, per test class, the verified calls with their literal arguments; the same signature and literals in two or more tests is a violation. False positives when two tests really check two scenarios. |

### 1.5 Expected value

| # | Practice | Sources | Class | Detection logic |
|---|---|---|---|---|
| R18 | No production constant as expected value checking its own value | [TotT] Change-Detector Tests, [TP] | **D**, with a nuance | First argument of `assertSame`/`assertEquals` is a class constant fetch on a class outside the tests, which is not an enum case (`ClassReflection::isEnum()`). A constant used to express a state without pinning its value cannot be told apart statically: the rule is strict, and an intended use is ignored line by line (`@phpstan-ignore`). |
| R19 | No expected value recomputed with the code's own logic (tautology) | [TP] tautological assertion, [PTC] assertion-patterns | **H** (reduced) / **N** (general) | Reduced case: the expected value, or the variable feeding it, contains `match`, a ternary or arithmetic. General case (`assertEquals(new Summary($input), $result)` where the constructor computes a field) needs to know what the constructor does: that is mutation testing's job, not the AST's. |
| R20 | Expected value is a literal, a hand-worked example or a known fixture | [TP] | **N** | Same reason as R19. Only mutation proves that an expected value discriminates. |
| R21 | An exception test names the exception: `itThrows{Exception}When{Condition}` | [PTC] | **D** | A test method calling `expectException(X::class)`: its name must contain the short name of `X`, and `X` must not be a bare `\Exception`/`\RuntimeException`. |
| R22 | A snapshot assertion always comes with a discrete assertion | [TP] | **D** | `assertMatchesSnapshot*` without any other `assert*` in the same method. |

### 1.6 Naming, providers, fixtures, scope

| # | Practice | Sources | Class | Detection logic |
|---|---|---|---|---|
| R23 | Descriptive test names (not `testCase1`, `testItWorks`) | [XTP] Obscure Test, [TP] | **H** | A regex denylist (`^test(Case)?\d+$`, `^testItWorks$`) is deterministic but incomplete. Domain vocabulary is N. |
| R24 | Data providers yield string keys (`yield 'label' => [...]`) | [PTC] | **D** | Any method referenced by `#[DataProvider]`/`@dataProvider`: every `yield` without a key, every non-string key is a violation. Existing: `phpstan-phpunit` `DataProviderDataRule` (arity only), Rector `NamedArgumentForDataProviderRector` (rewrites, does not detect). |
| R25 | `setUp()` for the environment only, no fixture and no double | [XTP] Fresh Fixture vs Shared Fixture, [TP] | **D** | `setUp()` assigning `$this->x = new ...`/`createMock`/`prophesize`; allowlist `bootKernel()`, `createClient()`, `getContainer()`. Existing: `ikvasnica/phpstan-clean-test` `DisallowSetupAndConstructorRule` (PHPStan 2 compatibility not announced). |
| R26 | No test of a pure getter/setter or DTO | [TP] what not to test, [TotT] Change-Detector Tests | **H** | Reduced case: the act is a getter on an object built in the same test, and the expected value is the literal passed to its constructor. "Class without behaviour" is N. |
| R27 | No `ReflectionMethod`/`ReflectionProperty`/`setAccessible` on the code under test | [TotT] Test Behavior, Not Implementation | **D** | `new \ReflectionMethod`, `new \ReflectionProperty`, `->setAccessible(` in tests. Existing, generic: `spaze/phpstan-disallowed-calls`, `shipmonk/phpstan-rules`. |
| R28 | No PHP `assert()` function in tests | `symplify/phpstan-rules` | **D** | Existing: `symplify` `NoAssertFuncCallInTestsRule`. |
| R29 | No test without an assertion | [tsDetect] Unknown Test | **D** (runtime) | Native PHPUnit: `beStrictAboutTestsThatDoNotTestAnything` plus `failOnRisky="true"`. Static complement: a test method with no `assert*`, `should*`, `expectException*` nor `#[DoesNotPerformAssertions]`. |
| R30 | Every test file belongs to a test suite | [TSD] | **D** (configuration) | Read the PHPUnit XML configuration, resolve `<directory>`/`<file>`, compare with the test files. Not an AST question, but PHPStan can still host it through reflection on test classes and a read of the configuration. Suites that include other suites must be tolerated. |
| R31 | No `Argument::that()` without a PHPUnit assertion inside (unreadable failure message) | [PTC] test-doubles | **D** | `Argument::that(closure)` whose closure contains no `assert*`. |

### 1.7 Symfony tests

| # | Practice | Sources | Class | Detection logic |
|---|---|---|---|---|
| R32 | Built-in web assertions (`assertSelectorTextContains`) over crawler plus `trim(...->text())` | [STC] | **D** | `assertSame(..., trim($crawler->filter(...)->text()))` and siblings. |
| R33 | No `filterXPath('//*[contains(@class, ...)]')` | [STC] | **D** | Regex on the literal. |
| R34 | No test whose only assertions count or locate elements by technical class | [STC], [TP] interface tests | **D** | The set of assertions of the method is a subset of {count, exists}. |
| R35 | No test of FormType structure (`->has('x')`) | [STC] | **H** | `assertTrue($form->...->has(...))`: deterministic on shape, legitimate in rare cases. |
| R36 | One rendered dataset, one test method (not N tests replaying the same request) | [STC] | **H** | Collector: two or more tests of a class with the same `request('GET', <same literal>)`. |
| R37 | No kernel booted for a pure function | [STC] | **D** | A `KernelTestCase`/`WebTestCase` subclass that never boots a kernel nor creates a client. |

### 1.8 What the AST will never see (N)

- R19/R20 in general: an expected value that mimics the code's computation in another file, a stub returning exactly the expected value through an intermediate variable. Proof: mutation testing (Infection) or a pseudo-tested-method check (§2.5).
- "The test checks a business rule at the right level": a judgement on intent.
- "The scenario is a valid business state": outside the code.
- "The name states the rule in domain words": outside the AST.
- "Do not test the absence of a rule", change detectors in general ([TotT] Change-Detector Tests): semantic.

Count over R1 to R37: 9 heuristic (R4, R11, R12, R16, R17, R23, R26, R35, R36), R20 not detectable, R6, R15 and R19 split between classes, the rest deterministic. The classification is the author's; an H rule can become D under a project convention (entity namespace, variable naming).

---

## 2. Tool landscape

### 2.1 PHPStan custom rules (verified)

- Interface `PHPStan\Rules\Rule`: `getNodeType(): string` and `processNode(Node $node, Scope $scope): array`; errors built with `RuleErrorBuilder::message(...)->identifier('x.y')->build()`. Registered through `rules:` or as a service tagged `phpstan.rules.rule`. Virtual node `InClassMethodNode` gives the original method node and a scope inside the class. https://phpstan.org/developing-extensions/rules
- Collectors (cross-node, cross-file rules): tag `phpstan.collector`, consumed by a rule on `CollectedDataNode`; collected data must be serializable (multi-process). https://phpstan.org/developing-extensions/collectors
- Test-only scope: there is no per-rule path filter. Two ways: check `$scope->getClassReflection()->is(TestCase::class)` in the rule (the approach of `phpstan-phpunit`'s `TestMethodsHelper`), or a second configuration file with `paths: [tests]`. `isSubclassOf(string)` is deprecated in 2.x in favour of `is()`/`isSubclassOfClass()`: https://apiref.phpstan.org/2.3.x/PHPStan.Reflection.ClassReflection.html
- Version: PHPStan 2.3 announced at https://phpstan.org/blog/phpstan-2-3-leap-in-performance. `level` is required unless `customRulesetUsed: true` (https://phpstan.org/config-reference); custom rules run at any level.
- `phpstan/phpstan-phpunit`: `AssertSameBooleanExpectedRule`, `AssertSameNullExpectedRule`, `AssertSameWithCountRule`, `AssertEqualsIsDiscouragedRule`, `AssertEmptyIsDiscouragedRule`, `MockMethodCallRule`, `ShouldCallParentMethodsRule`, `DataProviderDeclarationRule`, `DataProviderDataRule`, `EmptyDataProviderRule`, `ClassCoversExistsRule`, `NoMissingSpaceIn*AnnotationRule`, `AttributeRequiresPhpVersionRule`. List: https://github.com/phpstan/phpstan-phpunit/tree/2.0.x/src/Rules/PHPUnit. None of them is about AAA, doubles or guards.
- `phpstan/phpstan-strict-rules`: no test-related rule (https://github.com/phpstan/phpstan-strict-rules).
- `ergebnis/phpstan-rules`: one test rule, `TestCaseWithSuffixRule` (https://github.com/ergebnis/phpstan-rules).
- `symplify/phpstan-rules` (https://github.com/symplify/phpstan-rules): the only maintained set of mock-smell rules found. PHPUnit: `NoAssertFuncCallInTestsRule`, `PublicStaticDataProviderRule`, `NoTestMocksRule`. With `parameters: symplify: mocks: true`: `AvoidAnyExpectsRule`, `NoWithOnStubRule`, `RequireAtLeastOneRule`, `NoEntityMockingRule`, `NoDocumentMockingRule`, `NoDoubleConsecutiveTestMockRule`, `NoMockObjectAndRealObjectPropertyRule`, `MockedMethodReturnMustMatchNativeTypeRule`. `rector/mockstan` is archived and points to symplify (https://github.com/rectorphp/mockstan).
- `ikvasnica/phpstan-clean-test`: `DisallowSetupAndConstructorRule`, `UnitExtendsFromTestCaseRule`, `AssertSameOverAssertEqualsRule`, `StaticAssertOverThisAndStaticRule`. PHPStan 2 compatibility not announced: https://github.com/ikvasnica/phpstan-clean-test
- `spaze/phpstan-disallowed-calls` (forbid a method, function, class or constant, with `allowIn` by path): https://github.com/spaze/phpstan-disallowed-calls. Covers R27 and part of R10 without writing a rule.
- `shipmonk/phpstan-rules`, including `forbidCustomFunctions`: https://github.com/shipmonk-rnd/phpstan-rules
- `haspadar/phpstan-rules`: `NoInlineCommentRule` with a `testsPaths` parameter: https://github.com/haspadar/phpstan-rules
- `Lendable/phpunit-extensions`: `EnforceStrictMocking`, `ForbidLooseMock` (https://github.com/Lendable/phpunit-extensions). `NormanAlbert91/mock-check`: deny patterns for doubled classes (https://github.com/NormanAlbert91/mock-check).
- PHPStan restricted usage extensions (2.1.13+): forbid a method or class without a full rule: https://phpstan.org/blog/restricted-usage-extensions-you-dont-always-need-custom-rule

**Already available somewhere**: R10 partly (symplify `NoTestMocksRule`, mock-check, spaze), R11 partly (`NoEntityMockingRule`), R25 (ikvasnica), R27 through generic tools (spaze, shipmonk), R28 (symplify), R6 comments (haspadar), R29 (native PHPUnit). **Not found** (GitHub and Packagist search, 2026-11-14): R1, R2, R3, R5, R8, R9, R13, R14, R15, R18, R19, R21, R23, R24 (string keys).

### 2.2 Mago (verified on the repository and documentation)

- "a static analyzer, linter, and formatter for PHP, written in Rust"; four tools: Analyzer, Linter, Formatter, Guard (architectural boundaries, comparable to Deptrac and PHPArkitect). https://mago.carthage.software/latest/en/tools/overview/
- Release 1.59.0: https://github.com/carthage-software/mago/releases/tag/1.59.0; 1.0.0: https://github.com/carthage-software/mago/releases/tag/1.0.0. Installation by shell script, binary, Docker, Composer (`carthage-software/mago`), Homebrew or cargo: https://mago.carthage.software/latest/en/guide/installation/
- **Custom rules: yes, since 1.47.0.** The release notes mention "custom lint rules, analyzer plugins, and CLI tooling" as part of an extension API: https://github.com/carthage-software/mago/releases/tag/1.47.0. Extensions run as an external process; a PHP SDK is provided (`Mago\Sdk\Linter\Rule`, targets by node kind, reports through `Issue`). https://mago.carthage.software/latest/en/extensions/overview/, https://mago.carthage.software/latest/en/extensions/linter/rules/, analyzer plugins at https://mago.carthage.software/latest/en/extensions/analyzer/overview/, template at https://github.com/carthage-software/mago-extension-template
- Main limit for this use case: the documented `LintContext` of a custom linter rule exposes the file, the current node, its parent and children, its source text and `getResolvedName()`. No type or class reflection is documented there. A lint rule therefore cannot tell whether a class extends `TestCase` other than by name convention, nor whether `X::Y` is an enum case or a constant (R18). Inferred, high confidence on the absence of types in the linter API; what analyzer plugins receive is unverified.
- Existing PHPUnit rules (`integrations = ["phpunit"]`): `assertion-style`, `strict-assertions`, `use-specific-assertions`, `prefer-test-attribute`; Pest rules as well. Index: https://mago.carthage.software/latest/en/tools/linter/rules/. Nothing on AAA, doubles or guards.
- Configuration `mago.toml`: per-rule `level` and `exclude` paths; scoping by exclusion only (no "include only tests/"). https://mago.carthage.software/latest/en/tools/linter/configuration-reference/

### 2.3 Psalm, PHP_CodeSniffer, PHP-CS-Fixer, Rector, PHPat, Deptrac, PHPArkitect

- Psalm: plugins through `Psalm\Plugin\EventHandler\*` interfaces (`AfterMethodCallAnalysisInterface`, `AfterStatementAnalysisInterface`...), https://psalm.dev/docs/running_psalm/plugins/authoring_plugins/. `psalm/plugin-phpunit` infers types for doubles and providers, not style (https://github.com/psalm/psalm-plugin-phpunit). Feasible, but no advantage over PHPStan for this purpose.
- PHP-CS-Fixer: `php_unit_*` fixers (`php_unit_strict`, `php_unit_mock`, `php_unit_data_provider_name`, `php_unit_data_provider_static`, `php_unit_dedicate_assert`, `php_unit_method_casing`, `php_unit_test_case_static_method_calls`...), https://cs.symfony.com/doc/rules/index.html. All syntactic and local; nothing about statement order or double count.
- PHP_CodeSniffer: `flaconi/coding-standard` (https://github.com/flaconi/phpcs-coding-standard) and `woodcockjosh/PHPUnit-CodeSniffer` (https://github.com/woodcockjosh/PHPUnit-CodeSniffer, an assertions-per-test sniff, inactive). A sniff works on tokens, not an AST: a poor fit for R1 or R8.
- Rector `rector-phpunit` (https://github.com/rectorphp/rector-phpunit, rule list at https://getrector.com/find-rule?activeRectorSetGroup=phpunit): `AssertEqualsToSameRector`, `RemoveEmptyTestMethodRector`, `YieldDataProviderRector`, `NamedArgumentForDataProviderRector`, `RemoveExpectAnyFromMockRector`, `AddDoesNotPerformAssertionToNonAssertingTestRector`... Rector rewrites; `--dry-run` as a CI gate is common practice (unverified in the Rector docs). Some of its rules go against the practices above (`AddInstanceofAssertForNullable*Rector` adds guards).
- PHPat (https://www.phpat.dev/documentation/assertions/, https://www.phpat.dev/documentation/selectors/): class selectors and dependency/structure assertions. **No assertion on a method body.** Same for Deptrac (layers and collectors, https://deptrac.github.io/deptrac/collectors/) and PHPArkitect (per-class expressions, https://github.com/phparkitect/arkitect). These answer "who depends on whom", not "how many doubles in this test".

### 2.4 Structural matchers

- **Semgrep**: PHP is listed as generally available (https://semgrep.dev/docs/supported-languages). `pattern`, `pattern-inside`, `pattern-either`, `metavariable-regex`, `metavariable-comparison` (https://semgrep.dev/docs/writing-rules/rule-syntax); the sequence ellipsis `foo(); ... bar();` (https://semgrep.dev/docs/writing-rules/pattern-syntax). R8 could be written as two `createMock($A)` ... `createMock($B)` with `str($A) != str($B)`: it expresses "at least two" but does not count, and two doubles of the same class pass. R1 could be written as `self::$ASSERT(...); ... $sut->$M(...);`, which requires a naming convention for the code under test. `--error` exits non-zero on findings (https://semgrep.dev/docs/cli-reference). Licensing: https://semgrep.dev/docs/faq/overview. These two rules were composed from the documented primitives, not executed.
- **ast-grep**: PHP is in the native language list (https://ast-grep.github.io/reference/languages.html); issue https://github.com/ast-grep/ast-grep/issues/639 recommends the `{context, selector}` form for tricky PHP patterns. YAML rules with `pattern`/`kind`/`inside`/`has`/`follows`/`precedes`/`not`/`constraints` (https://ast-grep.github.io/reference/rule.html). No counting, no `$A != $B`. Exit code 1 when an `error` rule matches (https://ast-grep.github.io/reference/cli/scan.html).
- tree-sitter with a script (https://github.com/tree-sitter/tree-sitter-php, query predicates at https://tree-sitter.github.io/tree-sitter/using-parsers/queries/3-predicates-and-directives.html): the most flexible (count, order by offset), the most code, no types.
- Comby: `:[hole]` templates, no notion of method or order; unsuitable (https://comby.dev/docs/overview).

Verdict: Semgrep handles R1 and an approximation of R8 in a few lines of YAML, without types ("is a TestCase", "is an enum") and without counting. Exact R8, R18, R25 with an allowlist, or R17 (cross-test) need a typed AST.

### 2.5 Test smell catalogues and detectors

- tsDetect: Peruma et al., "tsDetect: an open source test smells detection tool", ESEC/FSE 2020, DOI 10.1145/3368089.3417921; https://github.com/TestSmells/TestSmellDetector (Java/JUnit). Catalogue at https://testsmells.org/pages/testsmells.html: Assertion Roulette, Conditional Test Logic, Constructor Initialization, Default Test, Duplicate Assert, Eager Test, Empty Test, Exception Handling, General Fixture, Ignored Test, Lazy Test, Magic Number Test, Mystery Guest, Redundant Print, Redundant Assertion, Resource Optimism, Sensitive Equality, Sleepy Test, Unknown Test. Mapping to §1: Eager Test to R8/R4, Conditional Test Logic to R7, General Fixture to R25, Unknown Test to R29, Constructor Initialization to a constructor in a test case, Exception Handling to try/catch in a test.
- Van Deursen, Moonen, van den Bergh, Kok, "Refactoring test code", 2001, https://ir.cwi.nl/pub/4324: the original test smell list (Mystery Guest, General Fixture, Eager Test, Assertion Roulette...).
- Meszaros, *xUnit Test Patterns*: test smells (Obscure Test, Conditional Test Logic, Assertion Roulette, Fragile Test, Erratic Test...), http://xunitpatterns.com/Test%20Smells.html. Erratic Test and [Fowler] Eradicating Non-Determinism cover tests depending on the real clock and on each other.
- Pseudo-tested methods: Niedermayr, Juergens, Wagner, "Will my tests tell me if I break this code?", 2016, https://arxiv.org/abs/1611.07163; Descartes for PITest (Vera-Pérez, Monperrus, Baudry, ASE 2018, https://arxiv.org/abs/1811.03045). A covered method whose body can be removed without any test failing. Infection has no extreme mutation mode; its `MethodCallRemoval` and return-removal mutators approximate it (https://infection.github.io/guide/mutators.html).
- PHP detectors: `jorj-sh/php-test-quality` (https://github.com/jorjives/php-test-quality, https://packagist.org/packages/jorj-sh/php-test-quality), an "AST-based test quality analyser for PHPUnit tests" covering tsDetect-style smells; very young at the research date, useful as a rule corpus (code not read, not executed). `petrkotek/phpunit-naughtytestdetector` (global state, https://github.com/petrkotek/phpunit-naughtytestdetector). SonarSource PHP: tests without assertions, https://rules.sonarsource.com/php/RSPEC-2699/.
- Pest `arch()` (https://pestphp.com/docs/arch-testing): `toBeFinal`, `toUseStrictTypes`, `toExtend`, `toHaveSuffix`, `toOnlyUse`... Nothing about test method bodies, doubles or assertion order.
- PHPUnit 12 configuration (https://docs.phpunit.de/en/12.4/configuration.html): `beStrictAboutTestsThatDoNotTestAnything`, `failOnRisky`, `failOnWarning`, `failOnDeprecation`, `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState`, `enforceTimeLimit`. No warning for datasets without keys. Test doubles: https://docs.phpunit.de/en/12.4/test-doubles.html.

---

## 3. Recommendation

**Choice: a package of custom PHPStan rules, scoped by `ClassReflection::is(TestCase::class)`, published as an extension loadable by `phpstan/extension-installer`.** Runner-up: a Mago extension through its PHP SDK. Trade-off in one line: PHPStan provides types and class hierarchy (TestCase, enum versus constant, final class) and already runs in most PHP CI pipelines; Mago is faster but its custom lint rules are syntactic and its extension API is recent.

Why not a home-grown tool: everything it would do (parse, resolve, traverse, report, exit code, JSON output, baseline, inline ignore) is provided by PHPStan. Semgrep stays useful to sketch a rule in YAML before writing it in PHP, not as a gate.

Reuse rather than rewrite:

- `symplify/phpstan-rules` with `symplify: mocks: true` for parts of R10/R11 and for R28.
- `spaze/phpstan-disallowed-calls` for R27 (`ReflectionMethod`, `setAccessible`).
- PHPUnit `failOnRisky="true"` for R29.

Integration:

1. **CI**: a dedicated configuration (`paths: [tests]`), a dedicated job, exit code 1 blocks. `--error-format=json` for tooling, `--error-format=github` to annotate pull requests, an initial baseline (`--generate-baseline`) on an existing suite.
2. **Coding agents**: a rule written as prose in a convention document becomes an error message with `file:line` that an agent receives verbatim. A Claude Code `PostToolUse` hook on writes to `*Test.php` can run PHPStan on the file and return the errors on stderr with exit code 2, which "Shows stderr to Claude; the tool already ran" (https://code.claude.com/docs/en/hooks). The error message should carry the expected fix (for instance "read the value permissively (`?? null`, `?->`)").

What the static gate does not replace: mutation testing and pseudo-tested-method checks (§2.5). They answer different questions: static analysis judges the shape of the test, a pseudo-tested check judges whether the test depends on the code, mutation judges whether the assertion discriminates a fault.

Limits identified while designing the rules:

- R9 is stricter than the practice when the call is the behaviour; a `warning` severity, or an exception when it is the only verified double of the test.
- R19, reduced, only sees computations in the test file.
- R1 judges top-level statements; assertions in a loop or a try/catch around the act need dedicated handling (hence separate rules for control flow and try/catch).
- R18 also flags a constant that is not the subject of the test; an inline `@phpstan-ignore` documents that intent.
- Test-method detection requires the test's base class to be autoloadable: if `WebTestCase` cannot be resolved, a test class is silently skipped. In a real project Composer autoloading solves it; in an isolated CI, watch `class.notFound` on base classes.

---

## 4. Rules that came out of this research

The extension implements 29 rules. Mapping to the inventory above:

| Inventory | Rule |
|---|---|
| R1, R2 | [assertionsLast](../rules/assertionsLast.md) |
| R3 | [noGuardAssertion](../rules/noGuardAssertion.md) |
| R4 | [assertionCount](../rules/assertionCount.md) |
| R5 | [structuredAssertion](../rules/structuredAssertion.md) |
| R7 | [noControlFlowInTest](../rules/noControlFlowInTest.md) |
| R8 | [oneVerifiedDoublePerTest](../rules/oneVerifiedDoublePerTest.md) |
| R9 | [spyOverMock](../rules/spyOverMock.md) |
| R10 | [dontMockWhatYouDontOwn](../rules/dontMockWhatYouDontOwn.md) |
| R12 (reduced) | [noStubReturnAsExpected](../rules/noStubReturnAsExpected.md) |
| R13 | [permissiveStub](../rules/permissiveStub.md) |
| R14 | [prophecyOverCreateMockCallback](../rules/prophecyOverCreateMockCallback.md) |
| R15 | [preferInMemoryDouble](../rules/preferInMemoryDouble.md) |
| R18 | [noProductionConstantAsExpected](../rules/noProductionConstantAsExpected.md) |
| R19 (reduced) | [noComputedExpectedValue](../rules/noComputedExpectedValue.md) |
| R21 | [exceptionTestNamesException](../rules/exceptionTestNamesException.md) |
| R24 | [dataProviderNamedKeys](../rules/dataProviderNamedKeys.md) |
| R25 | [noFixtureInSetUp](../rules/noFixtureInSetUp.md) |
| R30 | [testFileInTestsuite](../rules/testFileInTestsuite.md) |
| R32 | [builtInWebAssertions](../rules/builtInWebAssertions.md) |
| R37 | [noKernelForPureFunction](../rules/noKernelForPureFunction.md) |
| tsDetect Constructor Initialization | [noTestCaseConstructor](../rules/noTestCaseConstructor.md) |
| tsDetect Exception Handling | [noTryCatchInTest](../rules/noTryCatchInTest.md), [expectExceptionBeforeAct](../rules/expectExceptionBeforeAct.md) |
| [XTP] Erratic Test, [Fowler] non-determinism | [frozenClock](../rules/frozenClock.md), [noDependsBetweenTests](../rules/noDependsBetweenTests.md) |
| Discovery and naming | [testClassSuffix](../rules/testClassSuffix.md), [testMethodIsDiscoverable](../rules/testMethodIsDiscoverable.md) |
| Unused doubles | [noUnusedDouble](../rules/noUnusedDouble.md) |
| [TotT] Test Behavior, Not Implementation | [noTestOfLogging](../rules/noTestOfLogging.md) |

Left to existing tools or not implemented: R6, R11, R16, R17, R22, R23, R26 to R29, R31, R33 to R36.

---

## 5. Unverified

- PHPStan 2 compatibility of `ikvasnica/phpstan-clean-test`: not announced in its README, not tested.
- `symplify/phpstan-rules`: rule list read from its README; not installed nor executed; required PHPStan version not stated in the README.
- Rector `--dry-run` as a CI gate: standard behaviour, not re-read in the Rector documentation.
- Mago: no rule was written nor executed; what analyzer plugins receive was not checked.
- Semgrep and ast-grep: the rules in §2.4 were composed from documented primitives, not executed.
- `jorj-sh/php-test-quality`: Packagist page read, code neither read nor executed.
- The D/H/N counts reflect the author's classification.
