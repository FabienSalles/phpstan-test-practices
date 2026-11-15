# Analyse statique des tests : un 4e niveau de preuve

Date : 2026-11-14. Question posée : existe-t-il un exécutable qui fait échouer un build quand le **code de test** viole les pratiques enseignées dans la formation PHPUnit (AAA, un mock par test, pas de guard, pas de constante de prod dans l'attendu, etc.), à la manière de PHPStan, Mago ou Deptrac sur leurs propres sujets ? Si non, sur quoi le construire ?

Réponse courte : rien d'existant ne couvre ces règles. PHPStan les héberge sans effort (6 règles prototypées et exécutées, 405 lignes de PHP), Mago les hébergerait aussi depuis sa version 1.47 mais sans types, Semgrep ne tient que les cas simples. Recommandation en §3, prototype en §4.

Conventions du document : « vérifié » = lu dans la doc officielle ou le code source cité ; « inféré » = déduction, avec le niveau de confiance. Les règles sont ancrées `fichier:ligne` sur les sources lues le 14 novembre 2026.

---

## 1. Inventaire des règles

Sources lues :

- `S1` = `src/content/formations/phpunit/day-2/slides/05-test-pratices.md`
- `S2` = `src/content/formations/phpunit/day-1/slides/08-test-double.md`
- `S3` = `src/content/formations/tests-utiles/conference/slides/*.md`
- `K1` = `plugins/craft/skills/testing-principles/SKILL.md`
- `K2` = `plugins/phpunit/skills/php-test-conventions/SKILL.md` et `references/{assertion-patterns,test-doubles,http-testing}.md`
- `K3` = `plugins/symfony/skills/symfony-test-conventions/SKILL.md`
- `K4` = `plugins/craft/skills/test-suite-design/SKILL.md`, `plugins/craft/skills/tdd-workflow-principles/SKILL.md`

Classification :

- **D** : détectable de façon déterministe par AST (zéro jugement, zéro faux positif par construction, à une liste de configuration près)
- **H** : heuristique (détection AST avec un risque de faux positif à documenter et une porte de sortie)
- **N** : non détectable statiquement (nécessite bite, mutation, ou une relecture)

### 1.1 Structure du test (AAA, assertions)

| # | Règle | Source | Classe | Logique de détection |
|---|---|---|---|---|
| R1 | Assertions uniquement à la fin, aucune assertion avant l'act (« Assertion Last ») | S1:565-584, K1:80-82, S3 04-reparer:184 | **D** | Dans une méthode de test, parcourir les statements de premier niveau. Dès qu'un statement d'assertion est vu (`self::assert*`, `$this->assert*`, `->shouldHaveBeenCalled*()`), tout statement non-assertion qui suit est une violation. Prototype : `AssertionsLastRule`. |
| R2 | Pas d'assertion cachée dans l'arrange (callback `willReturnCallback`, closure) | S1:719-744 (exemple ❌ du cours, cf. `OrderProcessorTest` main:43-48) | **D** | Toute `assert*` trouvée à l'intérieur d'un statement de premier niveau qui n'est pas lui-même une assertion (closure dans un `willReturnCallback`). Exception : `Argument::that(fn() => self::assertEquals(...))->shouldHaveBeenCalled()`, qui est un statement d'assertion (K2 test-doubles:131-142). Prototype : même règle, identifiant `testRules.assertionInArrange`. |
| R3 | Pas de guard (`assertCount`, `assertInstanceOf`, `assertNotNull`, `assertNotEmpty`, `assertArrayHasKey`...) avant l'assertion finale ; accès permissif `?? null`, `?->` | S1:689-715, K2 assertion-patterns:63-90 | **D** | Liste des assertions « guard ». Toute occurrence qui n'est pas la dernière assertion de la méthode est une violation. Prototype : `NoGuardAssertionRule`. Nuance : K1:286-288 autorise un « Result guard » avant l'assertion de payload (contexte TypeScript, type `Result`). En PHP, l'accès permissif rend le guard inutile ; si un projet veut l'exception, l'autoriser sur une liste de types. |
| R4 | Une intention par test, une seule assertion (ou un seul bloc) | S1:588-592, S1:210-215, K1:286 (« several assertions per test are allowed ») | **H** | Compter les statements d'assertion : > N (configurable, 1 ou 3) = violation. K1 et S1 ne disent pas la même chose (une assertion / plusieurs autorisées). À trancher : la règle déterministe utile est R5 (une assertion structurée plutôt que N assertions propriété par propriété). |
| R5 | Une assertion structurée (`assertEquals` sur l'objet complet, ou `assertSame` sur deux tableaux clé/valeur) plutôt que N `assertSame` sur des getters du même objet | S1:608-633, K1:124-131, K2:141-143, K2 test-doubles:158-169 | **D** | Deux statements `assertSame`/`assertEquals` consécutifs dont l'argument « actual » est un `MethodCall`/`PropertyFetch` sur la même variable racine (`$order->id`, `$order->customer`...). Exemple flagué : `OrderProcessorTest` main:63-66. La correction demande un jugement (objet complet ou tableau), la détection non. |
| R6 | AAA lisible : blocs séparés par des lignes vides, pas de commentaires | K1:80-82, S1:335-342, S3 04-reparer:184 | **D** (commentaires) / **H** (lignes vides) | Commentaires : tout nœud `Comment` dans le corps d'une méthode de test, hors `@var`. Lignes vides : exige de lire les positions de ligne des statements ; faisable (l'act est le dernier statement avant la première assertion), mais fragile sur les arranges multi-lignes. Existant : `haspadar/phpstan-rules` `NoInlineCommentRule` (cf. §2.1). |
| R7 | Pas de logique conditionnelle dans le test (if, match, boucles) | Implicite dans K1:102 (« no `if` should be needed in the test body »), catalogue tsDetect « Conditional Test Logic » | **D** | `Stmt\If_`, `Stmt\For_`, `Stmt\Foreach_`, `Expr\Match_`, `Expr\Ternary` dans le corps d'un test. Exception à prévoir : le `foreach` de K2:99-108 qui filtre `::cases()` (il vit dans un provider, pas dans un test). |

### 1.2 Doublures

| # | Règle | Source | Classe | Logique de détection |
|---|---|---|---|---|
| R8 | Un mock vérifié par test (une intention) | S1:719-744 | **D** | Compter les racines distinctes portant une attente (`->expects(` PHPUnit, `->shouldBeCalled*()`, `->shouldHaveBeenCalled*()` Prophecy). > 1 = violation. Prototype : `OneMockPerTestRule`. Les stubs (`willReturn` sans attente) ne comptent pas. |
| R9 | Spy plutôt que mock : vérification après l'act, pas d'attente avant (`expects()`, `shouldBeCalled()`) | S2:221-248, S2:355-364, S2:443-456, S1:822-843, K1:84-96, K2:39-49, S3 03-modele:62-68 | **D** | Toute occurrence de `->expects(` ou `->shouldBeCalled*()` dans un test. Prototype : `NoExpectationBeforeActRule`. Attention : la branche `solution/best-practices` du repo d'exercice garde un `expects($this->once())` (`OrderProcessorTest`:22-25) et se fait flaguer. Le cours (S3 03-modele:68 : « le mock reste légitime quand l'appel lui-même est le comportement attendu ») et la règle dure se contredisent ; à passer en niveau `warning` ou à assumer. |
| R10 | Don't mock what you don't own : jamais de doublure de `EntityManagerInterface`, `QueryBuilder`, `Query`, `SerializerInterface`, `HttpClientInterface`, Guzzle `ClientInterface`... | S1:103-143, K1:140, S3 03-modele:47-52, S3 02-symptomes:94-109 | **D** | `createMock`/`createStub`/`getMockBuilder`/`prophesize` dont l'argument est un `ClassConstFetch ::class` sur un namespace interdit (`Doctrine\`, `Symfony\Component\Serializer\`, `Symfony\Contracts\HttpClient\`, `GuzzleHttp\`). Liste configurable. Existant : `symplify/phpstan-rules` `NoTestMocksRule` (allowlist inverse), `NormanAlbert91/mock-check` (deny patterns, exemple `EntityManagerInterface` dans son README), `spaze/phpstan-disallowed-calls`. |
| R11 | Pas de doublure d'entité, de value object, de classe sans I/O | S3 02-symptomes:13-72 (histoire 1, `createMock(Beneficiary::class)`), K1:33-41 | **H** | Déterministe si on définit « entité » par namespace (`App\Entity\`) ou attribut `#[ORM\Entity]` (lecture de l'attribut via la réflexion PHPStan). Existant : `symplify` `NoEntityMockingRule` (Doctrine) et `NoDocumentMockingRule`. « Sans I/O » n'est pas décidable statiquement sans convention de namespace. |
| R12 | Pas de mock du transformeur quand la transformation est l'objet du test (vrai `Serializer`) | S1:147-184, K2:145-147, K2 http-testing:3 | **H** | Cas réduit détectable : `prophesize(SerializerInterface::class)` + `deserialize(Argument::cetera())->willReturn(...)` + une assertion `assertEquals` sur la valeur retournée par le SUT dans le même test (le mock renvoie l'attendu). Le cas général (« l'objet du test est la transformation ») dépend de l'intention, donc N. |
| R13 | Stub permissif : jamais `Argument::type()` ni de valeurs précises dans un stub (`willReturn` sans `should*`) ; `Argument::any()`/`cetera()` | K2 test-doubles:76-104, S1:748-777 | **D** | Chaîne Prophecy terminée par `willReturn*`/`willThrow` sans `should*`, dont un argument est `Argument::type(...)` ou un littéral/`new`. Exemple flagué : `OrderImporterTest` main:37 `$repository->save(Argument::type(Order::class))` (sans `will*` ni `should*` : attente implicite). |
| R14 | Prophecy plutôt que `createMock` + `willReturnCallback` ; `createMock` seulement si Prophecy ne peut pas (classe finale sans interface) | K2:55-59 | **D** | `createMock(...)` suivi de `->willReturnCallback(` sur la même variable. Le cas « classe finale » se vérifie par réflexion (`ClassReflection::isFinal()`), donc l'exception est aussi déterministe. |
| R15 | Implémentation réelle ou null object plutôt qu'une doublure non vérifiée ni scriptée (`NullLogger`) | K2 test-doubles:5-20, K1:142-146 | **D** | `prophesize(X::class)` dont la variable n'est jamais sujet d'un `->method(...)` autre que `reveal()`, et `X` a une implémentation nulle connue (`Psr\Log\LoggerInterface` → `NullLogger`, liste configurable). |
| R16 | Pas de logique de production réimplémentée dans la doublure | K1:148-180 | **H** | `willReturnCallback`/`will(function...)` dont la closure contient `if`/`match`/arithmétique. Faux positifs possibles (un fake légitime, S2:107-130). |
| R17 | Les arguments d'un mock se vérifient dans un seul test de la suite | S1:748-769, S3 04-reparer:208 | **H** | Règle inter-tests : collector PHPStan qui enregistre, par classe de test, les `shouldHaveBeenCalled()`/`->with(...)` avec leurs arguments littéraux ; une même signature + mêmes littéraux dans ≥ 2 tests = violation. Faux positifs quand deux tests vérifient réellement deux scénarios. |

### 1.3 Valeur attendue

| # | Règle | Source | Classe | Logique de détection |
|---|---|---|---|---|
| R18 | Pas de constante de production dans l'attendu pour vérifier sa propre valeur | S1:49-69 | **D** avec nuance | Premier argument de `assertSame`/`assertEquals` est un `ClassConstFetch` sur une classe hors `tests/` et hors `TestCase`, qui n'est pas un enum (`ClassReflection::isEnum()`). Prototype : `NoProductionConstantAsExpectedRule`. Flague `OrderControllerTest` main:21, passe sur solution:21 (`"CONFIRMED"`). La nuance S1:69 (constante utilisée pour exprimer un état sans verrouiller sa valeur) n'est pas distinguable statiquement ; la règle est donc stricte, à désactiver ligne par ligne (`@phpstan-ignore`) quand c'est voulu. |
| R19 | Pas d'attendu recalculé par la même logique que le code (tautologie) | S1:637-686, K1:43, K2 assertion-patterns:32-61 | **H** (cas réduit) / **N** (cas général) | Cas réduit : l'attendu (ou la variable qui l'alimente) contient `match`, ternaire, `*`, `/`, `+`, `-`. Prototype : `NoComputedExpectedValueRule`, flague `OrderDiscountCalculatorTest`:24-30 sur main **et** sur solution (la branche solution n'a pas corrigé ce test). Cas général (`assertEquals(new OrderSummary($birthDate), $result)` où le constructeur calcule) : il faudrait savoir que le constructeur de `OrderSummary` calcule un champ ; c'est le travail de la mutation (Infection) ou de bite, pas de l'AST. |
| R20 | Valeur attendue : un littéral, un exemple à la main, une fixture connue | K1:43 | **N** | Même raison que R19. Seule la mutation prouve qu'une valeur attendue discrimine. |
| R21 | Un test d'exception nomme l'exception : `itThrows{Exception}When{Condition}` | K2:14-26, K1:122 | **D** | Méthode de test contenant `expectException(X::class)` : le nom de la méthode doit contenir le nom court de `X` ; et `X` ne doit pas être `\Exception`/`\RuntimeException` nus. |
| R22 | Snapshot toujours accompagné d'une assertion discrète | K1:290 | **D** | `assertMatchesSnapshot*`/`assertMatchesHtmlSnapshot` sans autre `assert*` dans la même méthode. |

### 1.4 Nommage, providers, fixtures, périmètre

| # | Règle | Source | Classe | Logique de détection |
|---|---|---|---|---|
| R23 | Noms de test descriptifs (pas `testCase1`, `testItWorks`, `testEligibility`) | S1:265-286, S3 04-reparer:149-162 | **H** | Denylist regex (`^test(Case)?\d+$`, `^testItWorks$`, `^test$`) : déterministe mais incomplète. Longueur minimale ou nombre de mots camelCase ≥ 3 : heuristique. Le vocabulaire du domaine (K1:110-114) est N. |
| R24 | Data provider avec clés `yield 'label' => [...]`, arguments nommés | S1:290-331, K2:63-91 | **D** | Méthode référencée par `#[DataProvider]`/`@dataProvider` : tout `yield` sans clé, toute clé non-string, tout tableau de dataset sans clés string = violation. Existant : `phpstan-phpunit` `DataProviderDataRule` (arité seulement), Rector `NamedArgumentForDataProviderRector` (renomme, ne détecte pas). |
| R25 | `setUp()` pour l'environnement uniquement, pas de fixtures ni de doublures | S1:219-261, S1:346-397, K1:72-78, S3 04-reparer:169-180 | **D** | `setUp()` contenant une affectation `$this->x = new ...`/`createMock`/`prophesize` = violation ; allowlist `bootKernel()`, `createClient()`, `getContainer()`. Flague `OrderProcessorTest` main:23-28. Existant : `ikvasnica/phpstan-clean-test` `DisallowSetupAndConstructorRule` (15 étoiles, compatibilité PHPStan 2 non annoncée). |
| R26 | Pas de test de getter/setter pur, pas de test de DTO | K1:29-41, S3 02-symptomes:106-110 | **H** | Cas réduit : test dont l'act est un getter sur un objet construit dans le même test, et dont l'attendu est le littéral passé au constructeur (même nœud littéral). Reste N pour « classe sans comportement ». |
| R27 | Pas de `ReflectionMethod`/`setAccessible`/`ReflectionProperty` sur le SUT | S1:73-99 | **D** | `new \ReflectionMethod`, `new \ReflectionProperty`, `->setAccessible(`, `->invoke(` d'un `ReflectionMethod` dans `tests/`. Existant générique : `spaze/phpstan-disallowed-calls`, `shipmonk/phpstan-rules` `forbidCustomFunctions`. |
| R28 | Pas de fonction `assert()` PHP dans les tests | symplify | **D** | Existant : `symplify` `NoAssertFuncCallInTestsRule`. |
| R29 | Pas de test sans assertion | S3 08-mesurer:11-25 | **D** (runtime) | PHPUnit natif : `beStrictAboutTestsThatDoNotTestAnything` (défaut `true`) + `failOnRisky="true"`. Statique en complément : méthode de test sans `assert*`, sans `should*`, sans `expectException*`, sans `#[DoesNotPerformAssertions]`. |
| R30 | Chaque fichier de test appartient à exactement une testsuite | S3 05-suite:62-67 (« guard statique »), S3 08-mesurer:49, K4 | **D** (config) | Lire `phpunit.xml.dist`, résoudre les `<directory>`/`<file>`, comparer à `find tests -name '*Test.php'`. Pas une règle PHPStan (pas d'AST), un script de 30 lignes. Note : le repo d'exercice a une suite `all` qui englobe les autres, donc « exactement une » doit tolérer l'inclusion. |
| R31 | Pas de `Argument::that` sans assertion PHPUnit dedans (message illisible) | S2:499-513, K2 test-doubles:150-156 | **D** | `Argument::that(closure)` dont la closure ne contient aucun `assert*`. |

### 1.5 Tests Symfony (K3)

| # | Règle | Source | Classe | Logique |
|---|---|---|---|---|
| R32 | Assertions intégrées (`assertSelectorTextContains`) plutôt que crawler + `trim(...->text())` | K3:70-89 | **D** | `assertSame(..., trim($crawler->filter(...)->text()))`. |
| R33 | Pas de `filterXPath('//*[contains(@class, ...)]')` | K3:159-162 | **D** | Regex sur le littéral. |
| R34 | Pas de test dont les seules assertions sont `assertSelectorCount`/`assertSelectorExists` par classe technique | K3:128-148, K1:218-221 | **D** | Ensemble des assertions de la méthode ⊂ {count, exists}. |
| R35 | Pas de test de structure de FormType (`->has('x')`) | K3:45-66 | **H** | `assertTrue($form->...->has(...))` : déterministe sur la forme, mais légitime dans de rares cas. |
| R36 | Un dataset rendu = une méthode de test (pas N tests qui rejouent la même requête) | K3:91-126 | **H** | Collector : ≥ 2 tests d'une classe avec le même `request('GET', <même littéral>)` et une assertion chacun. |

### 1.6 Ce que l'AST ne verra jamais (N)

- R19/R20 cas général : un attendu qui mime le calcul du SUT dans un autre fichier (`new OrderSummary($birthDate)`), un stub qui renvoie exactement l'attendu via une variable intermédiaire. Preuve : mutation (Infection, `--git-diff-lines`) et bite (S3 08-mesurer:61-91).
- « Le test vérifie une règle métier au bon niveau » (K1:242-266, S3 03-modele:82-110) : jugement sur l'intention.
- « Scénario métier valide » (S1:792-804, K1:64-70) : hors code.
- « Le nom dit la règle dans les mots du domaine » (K1:108-114) : hors AST.
- « Ne pas tester l'absence de règle », « change detector » (K1:47-62) : sémantique.

Total : 36 règles, 22 D, 9 H, 5 N (plus 3 D « hors AST » : R29 runtime PHPUnit, R30 config, R22 trivial).

---

## 2. Paysage des outils

### 2.1 PHPStan : règles personnalisées (vérifié)

- Interface `PHPStan\Rules\Rule` : `getNodeType(): string` et `processNode(Node $node, Scope $scope): array` ; erreurs construites par `RuleErrorBuilder::message(...)->identifier('x.y')->build()`. Enregistrement par `rules:` (sans constructeur) ou `services:` + tag `phpstan.rules.rule`. Nœuds virtuels `InClassMethodNode` (donne `getOriginalNode()` et un `Scope` dans la classe). Source : https://phpstan.org/developing-extensions/rules
- Collectors (règles inter-nœuds, inter-fichiers) : tag `phpstan.collector`, consommés par une règle sur `CollectedDataNode` ; données sérialisables (multi-process). Source : https://phpstan.org/developing-extensions/collectors
- Périmètre « tests seulement » : pas de filtre de chemin par règle. Deux voies : tester `$scope->getClassReflection()->is(TestCase::class)` dans la règle (c'est ce que fait `phpstan-phpunit` `TestMethodsHelper`, lu dans `vendor/phpstan/phpstan-phpunit/src/Rules/PHPUnit/TestMethodsHelper.php`), ou un second fichier neon avec `paths: [tests]`. `isSubclassOf(string)` est déprécié en 2.x, utiliser `is()`/`isSubclassOfClass()` : https://apiref.phpstan.org/2.3.x/PHPStan.Reflection.ClassReflection.html
- Version : PHPStan 2.3 (annonce 2026-10-06, https://phpstan.org/blog/phpstan-2-3-leap-in-performance), 2.3.5 installée dans le prototype. `level` obligatoire sauf `customRulesetUsed: true` ; les règles custom tournent à tout niveau (vérifié : le prototype tourne à `level: 0`).
- `phpstan/phpstan-phpunit` 2.1.2 (installé) : `AssertSameBooleanExpectedRule`, `AssertSameNullExpectedRule`, `AssertSameWithCountRule`, `AssertEqualsIsDiscouragedRule`, `AssertEmptyIsDiscouragedRule`, `MockMethodCallRule` (méthode mockée inexistante), `ShouldCallParentMethodsRule`, `DataProviderDeclarationRule`, `DataProviderDataRule`, `EmptyDataProviderRule`, `ClassCoversExistsRule`, `NoMissingSpaceIn*AnnotationRule`, `AttributeRequiresPhpVersionRule`. Liste : https://github.com/phpstan/phpstan-phpunit/tree/2.0.x/src/Rules/PHPUnit. Aucune de ces règles ne porte sur AAA, les mocks ou les guards.
- `phpstan/phpstan-strict-rules` : aucune règle liée aux tests (https://github.com/phpstan/phpstan-strict-rules).
- `ergebnis/phpstan-rules` : une seule règle test, `TestCaseWithSuffixRule` (https://github.com/ergebnis/phpstan-rules).
- **`symplify/phpstan-rules`** (278 étoiles, poussé 2026-10-28, https://github.com/symplify/phpstan-rules) : le seul jeu maintenu de règles « mock smells ». PHPUnit : `NoAssertFuncCallInTestsRule`, `PublicStaticDataProviderRule`, `NoTestMocksRule` (allowlist de types mockables). Avec `parameters: symplify: mocks: true` : `AvoidAnyExpectsRule`, `NoWithOnStubRule`, `RequireAtLeastOneRule`, `NoEntityMockingRule`, `NoDocumentMockingRule`, `NoDoubleConsecutiveTestMockRule`, `NoMockObjectAndRealObjectPropertyRule`, `MockedMethodReturnMustMatchNativeTypeRule`. `rector/mockstan` est archivé (2026-06-08) et renvoie vers symplify (https://github.com/rectorphp/mockstan).
- `ikvasnica/phpstan-clean-test` (15 étoiles) : `DisallowSetupAndConstructorRule` (R25), `UnitExtendsFromTestCaseRule`, `AssertSameOverAssertEqualsRule`, `StaticAssertOverThisAndStaticRule`. Compatibilité PHPStan 2 non annoncée : https://github.com/ikvasnica/phpstan-clean-test
- `spaze/phpstan-disallowed-calls` (interdire méthode/fonction/classe/constante, avec `allowIn` par chemin) : https://github.com/spaze/phpstan-disallowed-calls. Couvre R27 et une partie de R10 sans écrire de règle.
- `haspadar/phpstan-rules` : `NoInlineCommentRule` + paramètre `testsPaths` (R6 commentaires) : https://github.com/haspadar/phpstan-rules
- `Lendable/phpunit-extensions` : `EnforceStrictMocking`, `ForbidLooseMock` (https://github.com/Lendable/phpunit-extensions). `NormanAlbert91/mock-check` : deny patterns de classes mockées, 0 étoile (https://github.com/NormanAlbert91/mock-check).
- PHPStan « restricted usage extensions » (2.1.13+) : interdire une méthode/classe sans règle complète : https://phpstan.org/blog/restricted-usage-extensions-you-dont-always-need-custom-rule

**Règles de la liste §1 déjà existantes quelque part** : R10 partiel (symplify `NoTestMocksRule`, mock-check, spaze), R11 partiel (`NoEntityMockingRule`), R25 (ikvasnica), R27 par outil générique (spaze/shipmonk), R28 (symplify), R6 commentaires (haspadar), R29 (PHPUnit natif). **Introuvables** (recherche GitHub + Packagist, 14 novembre 2026) : R1, R2, R3, R5, R8, R9, R13, R14, R15, R18, R19, R21, R24 (clés string), R23.

### 2.2 Mago (vérifié sur le dépôt et la doc)

- « Mago is a static analyzer, linter, and formatter for PHP, written in Rust » ; quatre outils : Analyzer, Linter, Formatter, Guard (frontières architecturales, « covers the same ground as deptrac and arkitect »). https://mago.carthage.software/latest/en/tools/overview/
- Version 1.59.0 (2026-10-25, https://github.com/carthage-software/mago/releases/tag/1.59.0) ; 1.0.0 stable le 2025-12-20 ; licence MIT ou Apache-2.0 ; 3 559 étoiles. Installation : script shell, binaire, Docker `ghcr.io/carthage-software/mago`, `composer require --dev carthage-software/mago`, Homebrew, cargo. https://mago.carthage.software/latest/en/guide/installation/
- **Règles personnalisées : oui, depuis 1.47.0 (2026-08-18)** : « Extension API: adds language-neutral workers, a PHP SDK, custom lint rules, analyzer plugins ». https://github.com/carthage-software/mago/releases/tag/1.47.0. Mécanisme : un processus externe (« extension host ») parlant un protocole binaire, SDK PHP fourni (`Mago\Sdk\Linter\Rule` avec `getDefinition(): RuleDefinition` et `lint(LintContext $context)`, cibles par `NodeKind`, rapport par `Issue::new()`). https://mago.carthage.software/latest/en/extensions/overview/ et https://mago.carthage.software/latest/en/extensions/linter/rules/. Template : https://github.com/carthage-software/mago-extension-template
- Limite majeure, citée : « A custom linter rule inspects syntax. It does not receive inferred analyzer types. » Pas de hiérarchie de classes dans une règle de lint : impossible de savoir si une classe étend `TestCase` autrement que par convention de nom, ni si `X::Y` est un cas d'enum ou une constante (R18). Les plugins Analyzer reçoivent plus, mais sur d'autres points d'extension (types de retour, assertions, points d'entrée). Le formatter et Guard n'ont pas d'API d'extension.
- Règles PHPUnit existantes (`integrations = ["phpunit"]`) : `assertion-style`, `strict-assertions`, `use-specific-assertions`, `prefer-test-attribute`. Pest : `no-only`, `use-dedicated-expectation`, `use-simpler-expectation`, `use-specific-expectations`. Index : https://mago.carthage.software/latest/en/tools/linter/rules/. Rien sur AAA, mocks, guards.
- Config `mago.toml` : `[linter.rules] "acme/no-eval" = { level = "warning", exclude = ["tests/"] }` ; scoping par **exclusion** seulement (pas de « include only tests/ »). https://mago.carthage.software/latest/en/tools/linter/configuration-reference/
- Inféré (confiance moyenne) : l'API d'extension a 3 mois, la doc dit elle-même que `--list-rules`/`--explain` ne décrivent que les règles natives et que la config par règle n'est pas encore transmise aux callbacks PHP.

### 2.3 Psalm, PHP_CodeSniffer, PHP-CS-Fixer, Rector, PHPat, Deptrac, PHPArkitect

- Psalm : plugins par `Psalm\Plugin\EventHandler\*` (`AfterMethodCallAnalysisInterface`, `AfterStatementAnalysisInterface`...), https://psalm.dev/docs/running_psalm/plugins/authoring_plugins/. Psalm 6.16.1 maintenu (Packagist 2026-11-10). `psalm/plugin-phpunit` : inférence de types pour mocks/providers, pas de style. Faisable mais aucun avantage sur PHPStan, que Fabien a déjà en CI.
- PHP-CS-Fixer : 23 fixers `php_unit_*` (`php_unit_strict`, `php_unit_mock`, `php_unit_data_provider_name`, `php_unit_data_provider_static`, `php_unit_dedicate_assert`, `php_unit_method_casing`, `php_unit_test_case_static_method_calls`...), https://cs.symfony.com/doc/rules/index.html. Tous syntaxiques et locaux ; rien sur l'ordre des statements ni le compte de mocks.
- PHP_CodeSniffer : `flaconi/coding-standard` (2 sniffs test), `woodcockjosh/PHPUnit-CodeSniffer` (sniff « assertions par test », 0 étoile, abandonné). Écrire un sniff = travailler sur des tokens, pas un AST : mauvais support pour R1/R8.
- Rector `rector-phpunit` (110 règles, https://getrector.com/find-rule?activeRectorSetGroup=phpunit) : `AssertEqualsToSameRector`, `RemoveEmptyTestMethodRector`, `YieldDataProviderRector`, `NamedArgumentForDataProviderRector`, `RemoveExpectAnyFromMockRector`, `AddDoesNotPerformAssertionToNonAssertingTestRector`, `PreferTestsWithSnakeCaseRector`... Rector corrige, `--dry-run` fait échouer la CI (inféré, pratique standard). Il va parfois dans le sens inverse des règles de Fabien (`AddInstanceofAssertForNullable*Rector` ajoute des guards).
- PHPat (https://www.phpat.dev/documentation/assertions/) : sélecteurs de classes et assertions de dépendance/structure (`shouldNotDependOn`, `shouldBeFinal`, `shouldHaveOnlyOnePublicMethod`...). **Aucune assertion sur le corps d'une méthode.** Idem Deptrac (couches et collectors, https://deptrac.github.io/deptrac/collectors/) et PHPArkitect (expressions par classe, https://github.com/phparkitect/arkitect). Ces trois outils répondent à « qui dépend de qui », pas à « combien de mocks dans ce test ».

### 2.4 Matchers structurels

- **Semgrep** : PHP en « Generally available » (https://semgrep.dev/docs/supported-languages). Syntaxe `pattern`, `pattern-inside`, `pattern-either`, `metavariable-regex`, `metavariable-comparison` avec `str($A) != str($B)` (https://semgrep.dev/docs/writing-rules/rule-syntax), ellipse de séquence `foo(); ... bar();` qui ne remonte pas hors d'un bloc (https://semgrep.dev/docs/writing-rules/pattern-syntax). R8 s'écrit « deux `createMock($A)` ... `createMock($B)` avec `str($A) != str($B)` » : exprime ≥ 2 mais ne compte pas, et deux mocks de la même classe passent. R1 s'écrit `self::$ASSERT(...); ... $sut->$M(...);` : exige que le SUT s'appelle `$sut`. `--error` renvoie 1 sur findings (https://semgrep.dev/docs/cli-reference). Moteur LGPL 2.1, règles du registre sous licence Semgrep (usage interne).
- **ast-grep** : PHP dans la liste native (grammaire `php_only`, tree-sitter-php 0.25, https://ast-grep.github.io/reference/languages.html) mais le dépôt garde un « TODO: better php support » et l'issue #639 recommande la forme `{context, selector}` pour les patterns délicats. Règles YAML `pattern`/`kind`/`inside`/`has`/`follows`/`precedes`/`not`/`constraints` (https://ast-grep.github.io/reference/rule.html). Pas de comptage, pas de `$A != $B`. Exit 1 si une règle `error` matche (https://ast-grep.github.io/reference/cli/scan.html).
- tree-sitter + script Python : le plus souple (compter, ordonner par offset), le plus de code, sans types.
- Comby : templates `:[hole]`, pas de notion de méthode ni d'ordre ; inadapté (https://comby.dev/docs/overview).

Verdict pour les deux exemples : Semgrep fait R1 et une approximation de R8 en 20 lignes de YAML, mais sans types (pas de « est une TestCase », pas de « est un enum ») et sans comptage. Dès R8 exact, R18, R25 avec allowlist ou R17 (inter-tests), il faut un AST typé.

### 2.5 Catalogues de test smells et détecteurs

- tsDetect (Peruma et al., ESEC/FSE 2020, DOI 10.1145/3368089.3417921 ; https://github.com/TestSmells/TestSmellDetector, Java/JUnit, dernier push 2022-10-01) : Assertion Roulette, Conditional Test Logic, Constructor Initialization, Default Test, Duplicate Assert, Eager Test, Empty Test, Exception Handling, General Fixture, Ignored Test, Lazy Test, Magic Number Test, Mystery Guest, Redundant Print, Redundant Assertion, Resource Optimism, Sensitive Equality, Sleepy Test, Unknown Test (https://testsmells.org/pages/testsmells.html). Correspondances avec §1 : Eager Test ≈ R8/R4, Conditional Test Logic = R7, General Fixture ≈ R25, Unknown Test = R29, Redundant Assertion ≈ R18/R19.
- Van Deursen et al., « Refactoring test code », 2001 (https://ir.cwi.nl/pub/4324) ; Meszaros, *xUnit Test Patterns* ch. 15-17 (Obscure Test, Conditional Test Logic, Assertion Roulette, Eager Test, Mystery Guest, General Fixture, Fragile Test...).
- Pseudo-tested methods : Niedermayr et al. 2016 (https://arxiv.org/abs/1611.07163) ; Descartes pour PITest (Vera-Pérez et al., ASE 2018, https://arxiv.org/abs/1811.03045). C'est la formalisation de « le test ne mord pas » : une méthode couverte dont la suppression du corps ne fait échouer aucun test. Infection n'a pas de mode « extreme mutation » ; `MethodCallRemoval`/`ReturnRemoval` en sont l'approximation (https://infection.github.io/guide/mutators.html).
- Détecteurs PHP : **`jorj-sh/php-test-quality`** (https://github.com/jorjives/php-test-quality, v1.3.2 du 2026-10-27, 0 étoile, 7 installations, PHP ≥ 8.4) : « AST-based test quality analyser for PHPUnit tests », 15 à 16 smells de type tsDetect (Assertion Roulette, Conditional Test Logic, Magic Number, Rotten Green Test, Mystery Guest, Long Test, « Existence Check Assertion »...). Trop jeune pour une CI, intéressant comme corpus de règles. `petrkotek/phpunit-naughtytestdetector` (état global, 2021). SonarSource PHP : RSPEC-2699 (tests sans assertion) et quelques règles « tests ». Rien d'autre de crédible.
- Pest `arch()` (https://pestphp.com/docs/arch-testing) : `toBeFinal`, `toUseStrictTypes`, `toExtend`, `toHaveSuffix`, `toOnlyUse`, `toHaveLineCountLessThan`... Rien sur le corps d'une méthode de test, les mocks ou l'ordre des assertions. Exécution dans le runner Pest.
- PHPUnit 12 (https://docs.phpunit.de/en/12.4/configuration.html) : `beStrictAboutTestsThatDoNotTestAnything` (défaut `true`), `failOnRisky`, `failOnWarning`, `failOnDeprecation`, `beStrictAboutOutputDuringTests`, `beStrictAboutChangesToGlobalState`, `enforceTimeLimit`. Aucun avertissement sur les datasets sans clé.

---

## 3. Recommandation

**Choix : un paquet de règles PHPStan personnalisées (`phpstan-test-rules`), scopé par `ClassReflection::is(TestCase::class)`, publié comme extension et chargé par `phpstan/extension-installer`.** Runner-up : une extension Mago via son SDK PHP. Arbitrage en une ligne : PHPStan donne les types et la hiérarchie (TestCase, enum vs constante, classe finale) et tourne déjà dans la CI et dans `goal` ; Mago est plus rapide mais ses règles de lint sont purement syntaxiques et son API d'extension a trois mois.

Pourquoi pas un outil maison : tout ce que l'outil ferait (parser, résoudre, traverser, reporter, sortir un code 1, un format JSON, un baseline, un `@phpstan-ignore`) est fourni par PHPStan ; le prototype §4 tient en 405 lignes pour 6 règles, réflexion comprise. Semgrep reste utile pour prototyper une règle en YAML avant de l'écrire en PHP, pas comme gate.

Ce qu'on réutilise au lieu de réécrire :

- `symplify/phpstan-rules` avec `symplify: mocks: true` pour R10/R11 (`NoTestMocksRule`, `NoEntityMockingRule`) et R28.
- `spaze/phpstan-disallowed-calls` pour R27 (`ReflectionMethod`, `setAccessible`) et la liste « don't mock what you don't own » (R10), sans code.
- PHPUnit `failOnRisky="true"` pour R29.
- Un script pour R30 (fichiers de test hors de toute testsuite), hors PHPStan.

Ce qu'on écrit (ordre de valeur, toutes D) : R1+R2 (assertions à la fin, rien dans l'arrange), R8 (un mock vérifié), R3 (guards), R18 (constante de prod), R24 (clés de provider), R25 (setUp sans fixtures, l'existant ikvasnica n'est pas garanti PHPStan 2), R21 (nom d'exception), R13 (stub permissif), R5 (assertion structurée), R9 (spy plutôt que mock, niveau `warning` vu le conflit avec la branche solution).

Intégration :

1. **CI** : second fichier `phpstan-tests.neon` (`paths: [tests]`, `includes: [vendor/.../rules.neon]`), job dédié, exit 1 bloque. `--error-format=json` pour les agents, `--error-format=github` pour annoter la PR, baseline initiale (`--generate-baseline`) sur une suite existante.
2. **`goal`** : `bite.ts` commente que gate1 est le critère d'acceptation et que « gate2..N are supporting lints that pass with or without the implementation » (`plugins/goal/src/gate/bite.ts`, commentaire au-dessus de `biteCheck`). La commande `vendor/bin/phpstan analyse -c phpstan-tests.neon <test_files>` est un gate2 naturel : elle ne dépend pas de l'implémentation, elle juge le test. Ordre : gate2 (statique, 2 s) avant gate1 (exécution), un test mal formé est refusé avant d'être exécuté.
3. **Skills Claude Code** : la règle écrite en prose dans `php-test-conventions` devient un message d'erreur avec `file:line` que l'agent reçoit tel quel. Deux branchements : (a) hook `PostToolUse` sur `Write|Edit` de `*Test.php` qui lance PHPStan sur le fichier et renvoie les erreurs en `stderr` avec exit 2 (feedback immédiat, sans attendre la gate) ; (b) le skill cite l'identifiant de règle (`testRules.assertionsLast`) au lieu de paraphraser la règle, et le message d'erreur contient le correctif attendu (c'est le cas dans le prototype : « read the value permissively (?? null, ?->) »). Résultat : l'agent est rouge sur un fait, pas sur une instruction.

Ce que la gate statique ne remplace pas : bite (le test dépend du code) et la mutation (l'assertion discrimine). Les trois se complètent sans recouvrement : statique = forme du test, bite = dépendance au code, mutation = finesse de l'assertion.

---

## 4. Prototype exécuté

Environnement : pas de PHP ni de Composer sur la machine (`php -v` : command not found), Docker disponible, image locale `php:8.4-cli` (PHP 8.4.21). Composer 2.11.2 installé dans le conteneur. Tout est dans `scratchpad/test-rules/` (chemin complet : `/private/tmp/claude-501/-Users-fabiensalles-orca-workspaces-formation-prestest/d5e90838-4954-42f6-9277-a0bb5f45a8eb/scratchpad/test-rules/`). Aucun dépôt d'origine modifié : les corpus viennent de `git archive main` et `git archive solution/best-practices`.

Dépendances installées : `phpstan/phpstan` 2.3.5, `phpstan/phpstan-phpunit` 2.1.2, `phpunit/phpunit` 11.5.58, `phpspec/prophecy` 1.26.1, `nikic/php-parser` 5.9.0, plus `symfony/framework-bundle` 7.4, `doctrine/orm` 3.8, `spatie/phpunit-snapshot-assertions` 5.4 pour résoudre les symboles des corpus.

Fichiers :

```
composer.json, phpstan.neon, run.sh            (run.sh main|solution|slides|phpunit-exercice|phpunit-mailer-exercice)
src/TestMethod.php                             détection « méthode de test » (TestCase + test*/#[Test]/@test), helpers AST
src/Rules/AssertionsLastRule.php               R1 + R2   identifiants testRules.assertionsLast, testRules.assertionInArrange
src/Rules/OneMockPerTestRule.php               R8        testRules.oneMockPerTest
src/Rules/NoGuardAssertionRule.php             R3        testRules.noGuardAssertion
src/Rules/NoProductionConstantAsExpectedRule.php R18     testRules.noConstantAsExpected
src/Rules/NoComputedExpectedValueRule.php      R19 réduit testRules.noComputedExpected
src/Rules/NoExpectationBeforeActRule.php       R9        testRules.noExpectationBeforeAct
corpus/main, corpus/solution                   tests + src des deux branches de phpunit-tautological-test
corpus/slides/tests/SlideExamplesTest.php      les exemples ❌ et ✅ des slides (guards, assertion au milieu, 2 mocks Prophecy, Argument::that)
corpus/phpunit-exercice, corpus/phpunit-mailer-exercice  HEAD des deux autres repos (tests « bons »)
```

`phpstan.neon` : `level: 0`, `reportUnmatchedIgnoredErrors: false`, `ignoreErrors` sur `class.notFound`, `staticMethod.notFound`, `phpunit.mockMethod` (bruit de résolution, sans rapport avec les règles), `rules:` listant les 6 classes.

### 4.1 Sortie réelle, branche `main` (tests volontairement mauvais), exit code 1

```
main/tests/Calculator/OrderDiscountCalculatorTest.php:30:Test discountIsCalculatedFromOrderTotal(): the expected value is computed in the test (Expr_Match). A recomputed expectation mirrors the implementation; write the literal result.
main/tests/Controller/OrderControllerTest.php:21:Expected value App\Controller\OrderController::STATUS_CONFIRMED is a production constant: the assertion compares the constant to itself. Freeze the literal.
main/tests/Processor/OrderProcessorTest.php:30:Test processConfirmsOrderAndSendsEmailAndLogs() verifies 3 doubles ($this->repository, $this->mailer, $this->logger): one intention per test, one verified double per test.
main/tests/Processor/OrderProcessorTest.php:35:Test processConfirmsOrderAndSendsEmailAndLogs(): expects() sets a verification before the act (mock). Prefer a spy verified after the act: shouldHaveBeenCalled() or a hand-written double.
main/tests/Processor/OrderProcessorTest.php:40:Test processConfirmsOrderAndSendsEmailAndLogs(): expects() sets a verification before the act (mock). [...]
main/tests/Processor/OrderProcessorTest.php:44:Test processConfirmsOrderAndSendsEmailAndLogs(): assertsame() hidden inside an arrange statement (callback or closure). Verify after the act, on a spy or on the result.
main/tests/Processor/OrderProcessorTest.php:45: [idem]
main/tests/Processor/OrderProcessorTest.php:46: [idem]
main/tests/Processor/OrderProcessorTest.php:47: [idem]
main/tests/Processor/OrderProcessorTest.php:50:Test processConfirmsOrderAndSendsEmailAndLogs(): expects() sets a verification before the act (mock). [...]
```

### 4.2 Sortie réelle, branche `solution/best-practices`, exit code 1

```
solution/tests/Calculator/OrderDiscountCalculatorTest.php:30:Test discountIsCalculatedFromOrderTotal(): the expected value is computed in the test (Expr_Match). [...]
solution/tests/Processor/OrderProcessorTest.php:22:Test processLogsTheConfirmation(): expects() sets a verification before the act (mock). Prefer a spy verified after the act [...]
```

Lecture :

- `OrderControllerTest` : flagué sur main (constante), propre sur solution (littéral `"CONFIRMED"`). R18 fonctionne comme prévu, enum exclu par réflexion.
- `OrderProcessorTest` : 8 erreurs sur main (3 mocks vérifiés, 3 `expects()`, 4 assertions dans le `willReturnCallback`), 1 sur solution (`expects($this->once())` dans `processLogsTheConfirmation`). Les trois tests de la solution passent R8 (un double vérifié chacun), R1, R2, R3. Le `StubbedMailer` manuel et `mockRepository()` par référence passent sans faux positif.
- `OrderDiscountCalculatorTest` : flagué sur les **deux** branches. Le fichier est identique sur main et solution (vérifié par `git diff --stat main solution/best-practices -- tests`) : la branche solution n'a pas corrigé l'exercice de la tautologie par `match`. C'est soit un oubli du repo, soit l'exercice est corrigé sur `solution/exercise-5`. Point à vérifier avant la formation.
- `OrderRepositoryMockTest` (main et solution identiques, `assertCount(1, $expected)` sur la donnée d'arrange, jamais d'appel au SUT) : **aucune règle ne le flague**. Le guard n'est pas suivi d'une autre assertion (R3 ne s'applique pas), l'attendu n'est pas calculé. C'est le cas d'école de bite : le test passe sans implémentation. Une règle statique « l'assertion porte sur une variable jamais produite par un appel » serait possible (H) mais c'est le travail de bite.
- `OrderImporterTest` main (mock du `SerializerInterface` qui renvoie l'attendu, R12) : non flagué, la règle n'est pas prototypée. L'approximation syntaxique de §1 R12 l'attraperait.

### 4.3 Sortie réelle, corpus `slides` (exemples des slides)

```
SlideExamplesTest.php:23: badAssertionInTheMiddleOfTheAct(): assertnotnull() is a guard before the final assertion. [...]
SlideExamplesTest.php:24: badAssertionInTheMiddleOfTheAct(): statement after an assertion (line 24). Assertions come last, in one block.
SlideExamplesTest.php:34: badGuardAssertions(): assertcount() is a guard before the final assertion. [...]
SlideExamplesTest.php:35: badGuardAssertions(): assertinstanceof() is a guard [...]
SlideExamplesTest.php:36: badGuardAssertions(): assertnotnull() is a guard [...]
SlideExamplesTest.php:40: badProphecyMocksBeforeAct() verifies 2 doubles ($logger, $audit): one intention per test [...]
SlideExamplesTest.php:44: badProphecyMocksBeforeAct(): shouldbecalled() sets a verification before the act (mock). [...]
SlideExamplesTest.php:46: [idem]
```

Les trois méthodes `good*` (accès permissif + une assertion, spy `shouldHaveBeenCalled` après l'act, `Argument::that` avec `assertSame` dans la closure du spy) produisent zéro erreur : l'exception R2 pour `Argument::that` dans un statement d'assertion tient.

### 4.4 Corpus `phpunit-exercice` (HEAD `solution/exercise-api`) et `phpunit-mailer-exercice` (HEAD `step-3-solution`) : zéro erreur, exit 0

`OrderNotificationManagerTest` (3 tests, Prophecy, `assertEquals` + `assertMatchesHtmlSnapshot`) et les deux `ChuckNorris*Test` (stub Prophecy, `shouldHaveBeenCalled()` après l'act) passent les 6 règles. Aucun faux positif sur les tests écrits selon la méthode.

### 4.5 Faux positifs et limites observés

- R9 flague le `expects($this->once())` de la solution : la règle est plus stricte que la pratique de Fabien quand « l'appel est le comportement » (S3 03-modele:68). Niveau `warning`, ou exception quand c'est le seul double vérifié du test.
- R19 réduit ne voit que les calculs dans le fichier de test ; `assertEquals(new OrderSummary($birthDate), $result)` (S1:655-660) passe. Mutation requise.
- R1 juge les statements de premier niveau ; un `foreach` d'assertions ou un `try/catch` autour de l'act demande une extension.
- R18 flague aussi `assertSame(Foo::DEFAULT_LIMIT, $service->limit())` quand la constante n'est pas l'objet du test (S1:69). Réponse : `@phpstan-ignore testRules.noConstantAsExpected` sur la ligne, ce qui documente l'intention.
- La détection « méthode de test » exige que la classe résolve vers `PHPUnit\Framework\TestCase` : sans `symfony/framework-bundle` installé, `WebTestCase` était inconnu et `OrderControllerTest` passait silencieusement. En projet réel, l'autoload Composer règle le point ; en CI isolée, surveiller `class.notFound` sur les classes de base.

Commande de reproduction : `cd <scratchpad>/test-rules && ./run.sh main --error-format=raw` (Docker, image `php:8.4-cli`, `vendor/` déjà installé dans le dossier).

---

## 5. Angle pour la conférence

Position dans le plan actuel : la slide « Une échelle de preuves » (`08-mesurer.md`:47-57) liste 5 marches ; la règle statique s'insère comme marche 0, avant « le test tourne » : la forme du test est jugée avant son exécution, en deux secondes, sans implémentation.

1. **« Trois juges, trois questions »** : tableau à trois colonnes. Analyse statique : « le test a-t-il la forme d'une preuve ? » (AAA, un mock, pas de guard, pas de constante de prod). Bite : « le test dépend-il du code livré ? ». Mutation : « l'assertion discrimine-t-elle une faute ? ». Message : chaque juge répond à une question que les deux autres ne posent pas. Coût : 2 s, 10 s, minutes.

2. **« Le même fichier, trois verdicts »** : `OrderProcessorTest` de l'exercice. PHPStan : 8 erreurs (3 mocks vérifiés, assertions dans le callback, `expects()` avant l'act), avec les lignes. Bite : rouge sans l'implémentation (le test mord, il n'est pas décoratif). Infection : `MethodCallRemoval` sur `$this->mailer->send()` tué, sur l'affectation de statut survivant. Les trois verdicts sont vrais en même temps : bien formé n'est pas suffisant, mordre n'est pas suffisant, et un test mal formé peut mordre.

3. **« Ce que PHPStan peut juger, ce qu'il ne verra jamais »** : deux colonnes. Déterministe : assertions à la fin, un double vérifié, pas de guard, constante de prod dans l'attendu, clé de provider, `setUp` sans fixture, `ReflectionMethod`. Jamais : « l'attendu est recalculé par la même formule dans un autre fichier », « le test vérifie la règle au bon niveau », « le scénario est un état métier valide ». Limite dite franchement : 22 règles sur 36 sont déterministes, les 5 qui comptent le plus (tautologie, niveau, intention) ne le sont pas, c'est pour elles que bite et la mutation existent.

4. **« La règle devient un message, l'IA devient rouge »** : à gauche la phrase du skill (« Never use guard assertions like assertCount, assertInstanceOf, assertNotNull before the real assertion »), à droite la sortie `OrderProcessorTest.php:44: assertsame() hidden inside an arrange statement [...]`. Une instruction en prose se négocie ; un exit code 1 avec une ligne ne se négocie pas. C'est le gate2 de l'orchestrateur, joué avant gate1 : un test mal formé n'est pas exécuté.

5. (optionnel) **« Rien n'existait, 400 lignes suffisent »** : état de l'art en une slide. `phpstan-phpunit` (assertSame vs count, providers), `symplify` (mocks d'entités, `any()`), Mago (`strict-assertions`, extension API depuis août 2026), Semgrep (séquences, pas de types), tsDetect (Java, 2020). Aucun ne vérifie AAA ni « un mock par test ». 6 règles, 405 lignes, PHPStan 2.3, exécutées sur les deux branches du repo d'exercice. Honnêteté : une règle (`expects()`) a flagué ma propre solution.

---

## 6. Sources

Formation et skills (lus localement le 2026-11-14) :

- `src/content/formations/phpunit/day-2/slides/05-test-pratices.md`
- `src/content/formations/phpunit/day-1/slides/08-test-double.md`
- `src/content/formations/tests-utiles/conference/slides/02-symptomes.md`, `03-modele.md`, `04-reparer.md`, `05-suite.md`, `08-mesurer.md`
- `~/projects/github/claude-marketplace/plugins/craft/skills/testing-principles/SKILL.md`
- `~/projects/github/claude-marketplace/plugins/phpunit/skills/php-test-conventions/SKILL.md` + `references/assertion-patterns.md`, `test-doubles.md`, `http-testing.md`
- `~/projects/github/claude-marketplace/plugins/symfony/skills/symfony-test-conventions/SKILL.md`
- `~/projects/github/claude-marketplace/plugins/craft/skills/tdd-workflow-principles/SKILL.md`, `test-suite-design/SKILL.md`
- `~/projects/github/claude-marketplace/plugins/goal/src/gate/bite.ts`
- `~/projects/github/phpunit-tautological-test` (branches `main`, `solution/best-practices`), `phpunit-exercice` (`solution/exercise-api`), `phpunit-mailer-exercice` (`step-3-solution`)

PHPStan :

- https://phpstan.org/developing-extensions/rules
- https://phpstan.org/developing-extensions/collectors
- https://phpstan.org/config-reference
- https://phpstan.org/blog/phpstan-2-3-leap-in-performance
- https://phpstan.org/blog/restricted-usage-extensions-you-dont-always-need-custom-rule
- https://apiref.phpstan.org/2.3.x/PHPStan.Reflection.ClassReflection.html
- https://github.com/phpstan/phpstan-phpunit/tree/2.0.x/src/Rules/PHPUnit
- https://github.com/phpstan/phpstan-strict-rules
- https://github.com/ergebnis/phpstan-rules
- https://github.com/symplify/phpstan-rules
- https://github.com/rectorphp/mockstan
- https://github.com/ikvasnica/phpstan-clean-test
- https://github.com/spaze/phpstan-disallowed-calls
- https://github.com/haspadar/phpstan-rules
- https://github.com/Lendable/phpunit-extensions
- https://github.com/NormanAlbert91/mock-check
- https://github.com/shipmonk-rnd/phpstan-rules

Mago :

- https://github.com/carthage-software/mago
- https://github.com/carthage-software/mago/releases/tag/1.59.0
- https://github.com/carthage-software/mago/releases/tag/1.47.0
- https://github.com/carthage-software/mago/releases/tag/1.0.0
- https://mago.carthage.software/latest/en/tools/overview/
- https://mago.carthage.software/latest/en/guide/installation/
- https://mago.carthage.software/latest/en/extensions/overview/
- https://mago.carthage.software/latest/en/extensions/linter/rules/
- https://mago.carthage.software/latest/en/extensions/analyzer/overview/
- https://mago.carthage.software/latest/en/tools/linter/rules/
- https://mago.carthage.software/latest/en/tools/linter/configuration-reference/
- https://github.com/carthage-software/mago-extension-template

Autres outils PHP :

- https://psalm.dev/docs/running_psalm/plugins/authoring_plugins/
- https://github.com/psalm/psalm-plugin-phpunit
- https://cs.symfony.com/doc/rules/index.html
- https://getrector.com/find-rule?activeRectorSetGroup=phpunit
- https://github.com/rectorphp/rector-phpunit
- https://www.phpat.dev/documentation/assertions/ et https://www.phpat.dev/documentation/selectors/
- https://deptrac.github.io/deptrac/collectors/
- https://github.com/phparkitect/arkitect
- https://github.com/flaconi/phpcs-coding-standard
- https://pestphp.com/docs/arch-testing
- https://docs.phpunit.de/en/12.4/configuration.html
- https://infection.github.io/guide/mutators.html
- https://github.com/jorjives/php-test-quality et https://packagist.org/packages/jorj-sh/php-test-quality

Matchers :

- https://semgrep.dev/docs/supported-languages
- https://semgrep.dev/docs/writing-rules/rule-syntax
- https://semgrep.dev/docs/writing-rules/pattern-syntax
- https://semgrep.dev/docs/cli-reference
- https://semgrep.dev/docs/faq/overview
- https://ast-grep.github.io/reference/languages.html
- https://ast-grep.github.io/reference/rule.html
- https://ast-grep.github.io/reference/cli/scan.html
- https://github.com/ast-grep/ast-grep/issues/639
- https://github.com/tree-sitter/tree-sitter-php
- https://tree-sitter.github.io/tree-sitter/using-parsers/queries/3-predicates-and-directives.html
- https://comby.dev/docs/overview

Recherche :

- Peruma et al., « tsDetect: an open source test smells detection tool », ESEC/FSE 2020, DOI 10.1145/3368089.3417921 ; https://testsmells.org/pages/testsmells.html ; https://github.com/TestSmells/TestSmellDetector
- Van Deursen, Moonen, van den Bergh, Kok, « Refactoring test code », 2001, https://ir.cwi.nl/pub/4324
- Meszaros, *xUnit Test Patterns*, 2007, ch. 15-17
- Niedermayr, Juergens, Wagner, « Will my tests tell me if I break this code? », 2016, https://arxiv.org/abs/1611.07163
- Vera-Pérez, Monperrus, Baudry, « Descartes: a PITest engine to detect pseudo-tested methods », ASE 2018, https://arxiv.org/abs/1811.03045

## 7. Non vérifié

- Compatibilité PHPStan 2 de `ikvasnica/phpstan-clean-test` : non annoncée dans le README, non testée ici.
- `symplify/phpstan-rules` : liste de règles lue dans le README ; non installée ni exécutée sur le corpus. Exigence de version PHPStan non précisée dans le README.
- Rector `--dry-run` comme gate CI : comportement standard, non relu dans la doc Rector pour ce rapport.
- Mago : les règles PHPUnit et le SDK d'extension sont documentés, mais aucune règle Mago n'a été écrite ni exécutée ici ; l'affirmation « pas de hiérarchie de classes dans une règle de lint » repose sur la phrase citée de la doc (« does not receive inferred analyzer types ») et sur la liste des méthodes de `LintContext` (`getResolvedName()` donne un nom, pas une réflexion). Confiance : haute sur l'absence de types, moyenne sur l'impossibilité pratique de tester « étend TestCase » (une convention `*Test.php` + `extends TestCase` textuel reste possible).
- Semgrep et ast-grep : les deux règles YAML de §2.4 sont composées à partir des primitives documentées, pas exécutées (ni l'un ni l'autre n'est installé sur la machine).
- `jorj-sh/php-test-quality` : fiche Packagist lue, code non lu, non exécuté.
- Branche `solution/exercise-5` du repo d'exercice : non inspectée ; elle contient peut-être la correction de `OrderDiscountCalculatorTest` absente de `solution/best-practices`.
- Les chiffres « 22 D, 9 H, 5 N » reflètent ma classification ; une règle H peut passer D avec une convention de projet (namespace des entités, nom `$sut`).
- Le hook `PostToolUse` décrit en §3 n'a pas été écrit ; la mécanique (exit 2 + stderr renvoyé à l'agent) est celle documentée pour les hooks Claude Code, non relue pour ce rapport.
