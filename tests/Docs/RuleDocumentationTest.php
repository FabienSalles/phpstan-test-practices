<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Docs;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuleDocumentationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @var list<array{file: string, line: int, message: string, identifier: string}>|null */
    private static ?array $messages = null;

    #[DataProvider('provideRules')]
    public function testThePageShowsItsFixturesAndTheirRealOutput(string $rule): void
    {
        $page = (string) file_get_contents(self::ROOT . '/docs/rules/' . $rule . '.md');
        $fixtures = 'tests/Fixtures/Docs/' . $rule;
        $messages = array_values(array_filter(self::analyse(), static fn (array $message): bool => str_starts_with($message['file'], $fixtures . '/')));
        $output = implode("\n", array_map(static fn (array $message): string => sprintf('%s:%d:%s', $message['file'], $message['line'], $message['message']), $messages));

        self::assertSame(
            [
                'failingShown' => true,
                'passingShown' => true,
                'outputShown' => true,
                'files' => [$fixtures . '/Failing.php'],
                'identifiers' => ['testPractices.' . $rule],
            ],
            [
                'failingShown' => str_contains($page, (string) file_get_contents(self::ROOT . '/' . $fixtures . '/Failing.php')),
                'passingShown' => str_contains($page, (string) file_get_contents(self::ROOT . '/' . $fixtures . '/Passing.php')),
                'outputShown' => $output !== '' && str_contains($page, "```text\n" . $output . "\n```"),
                'files' => array_values(array_unique(array_column($messages, 'file'))),
                'identifiers' => array_values(array_unique(array_column($messages, 'identifier'))),
            ],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRules(): iterable
    {
        foreach (['assertionsLast', 'noGuardAssertion', 'noComputedExpectedValue', 'noProductionConstantAsExpected', 'oneVerifiedDoublePerTest', 'spyOverMock', 'preferInMemoryDouble', 'assertionCount', 'testFileInTestsuite', 'testClassSuffix', 'testMethodIsDiscoverable', 'noTryCatchInTest', 'noControlFlowInTest', 'expectExceptionBeforeAct', 'noDependsBetweenTests', 'noFixtureInSetUp', 'permissiveStub', 'prophecyOverCreateMockCallback', 'dontMockWhatYouDontOwn', 'noStubReturnAsExpected', 'noUnusedDouble', 'noTestOfLogging', 'frozenClock', 'dataProviderNamedKeys', 'exceptionTestNamesException', 'structuredAssertion', 'builtInWebAssertions', 'noKernelForPureFunction', 'noTestCaseConstructor'] as $rule) {
            yield $rule => [$rule];
        }
    }

    /**
     * @return list<array{file: string, line: int, message: string, identifier: string}>
     */
    private static function analyse(): array
    {
        if (self::$messages !== null) {
            return self::$messages;
        }

        $command = sprintf(
            'cd %s && %s %s analyse --no-progress --no-interaction --memory-limit=512M --error-format=json -c phpstan.neon %s 2>/dev/null',
            escapeshellarg(self::ROOT . '/tests/Fixtures/Docs'),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(self::ROOT . '/tests/Fixtures/Docs'),
        );
        exec($command, $output);
        $root = (string) realpath(self::ROOT) . '/';
        $messages = [];
        foreach (json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR)['files'] ?? [] as $file => $result) {
            foreach ($result['messages'] as $message) {
                if (!str_starts_with($message['identifier'] ?? '', 'testPractices.')) {
                    continue;
                }

                $messages[] = ['file' => str_replace($root, '', $file), 'line' => $message['line'], 'message' => $message['message'], 'identifier' => $message['identifier']];
            }
        }

        self::$messages = $messages;

        return $messages;
    }
}
