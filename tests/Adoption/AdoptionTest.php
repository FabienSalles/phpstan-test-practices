<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Adoption;

use PHPUnit\Framework\TestCase;

final class AdoptionTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/Severity';
    private const SUMMARY = 'Test practices: %d blocking error(s), %d non-blocking warning(s).';

    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/test-practices-' . bin2hex(random_bytes(4));
        mkdir($this->workspace);
    }

    protected function tearDown(): void
    {
        exec(sprintf('rm -rf %s', escapeshellarg($this->workspace)));
    }

    public function testAGeneratedBaselineSilencesErrorsAndWarnings(): void
    {
        [$generationExitCode] = $this->phpstan(self::FIXTURES . '/phpstan.neon', ['--generate-baseline', $this->workspace . '/baseline.neon'], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon', $this->workspace . '/baseline.neon']]);

        [$exitCode, $output] = $this->phpstan($config, [], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');

        self::assertSame(
            ['generationExitCode' => 0, 'exitCode' => 0, 'noErrors' => true],
            ['generationExitCode' => $generationExitCode, 'exitCode' => $exitCode, 'noErrors' => str_contains($output, '[OK] No errors')],
        );
    }

    public function testBaseliningTheErrorsLeavesTheWarningsNonBlocking(): void
    {
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon'], 'ignoreErrors' => [['identifier' => 'testPractices.noGuardAssertion', 'path' => self::FIXTURES . '/errors/GuardAssertionTest.php', 'count' => 1]]]);

        [$exitCode, $output] = $this->phpstan($config, [], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');

        self::assertSame(
            ['exitCode' => 0, 'summary' => true],
            ['exitCode' => $exitCode, 'summary' => str_contains($output, sprintf(self::SUMMARY, 0, 1))],
        );
    }

    public function testBaseliningTheWarningsLeavesTheErrorsBlocking(): void
    {
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon'], 'ignoreErrors' => [['identifier' => 'testPractices.spyOverMock', 'path' => self::FIXTURES . '/warnings/*']]]);

        [$exitCode, $output] = $this->phpstan($config, [], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');

        self::assertSame(
            ['exitCode' => 1, 'summary' => true],
            ['exitCode' => $exitCode, 'summary' => str_contains($output, sprintf(self::SUMMARY, 1, 0))],
        );
    }

    public function testLevelZeroLeavesTheGuardAndSpyRulesOut(): void
    {
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon'], 'testPractices' => ['level' => 0]]);

        [$exitCode, $output] = $this->phpstan($config, [], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');

        self::assertSame(
            ['exitCode' => 0, 'noErrors' => true],
            ['exitCode' => $exitCode, 'noErrors' => str_contains($output, '[OK] No errors')],
        );
    }

    public function testLevelOneAddsTheGuardRuleButNotTheSpyRule(): void
    {
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon'], 'testPractices' => ['level' => 1]]);

        [$exitCode, $output] = $this->phpstan($config, [], self::FIXTURES . '/warnings', self::FIXTURES . '/errors');

        self::assertSame(
            ['exitCode' => 1, 'summary' => true],
            ['exitCode' => $exitCode, 'summary' => str_contains($output, sprintf(self::SUMMARY, 1, 0))],
        );
    }

    public function testDiffBaseReportsOnlyTheFindingsOnChangedLines(): void
    {
        $test = $this->workspace . '/GuardAssertionTest.php';
        copy(self::FIXTURES . '/errors/GuardAssertionTest.php', $test);
        $this->git('init', '--quiet');
        $this->git('add', '.');
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Initial');
        file_put_contents($test, str_replace("    }\n}\n", <<<'PHP'
                }

                public function testAnotherGuard(): void
                {
                    $result = [2];

                    self::assertNotNull($result);
                    self::assertSame([2], $result);
                }
            }

            PHP, (string) file_get_contents($test)));
        $config = $this->config(['includes' => [self::FIXTURES . '/phpstan.neon'], 'testPractices' => ['errorFormat' => 'json', 'diffBase' => 'HEAD']]);

        [$exitCode, $output] = $this->phpstan($config, [], $test);

        self::assertSame(
            ['exitCode' => 1, 'lines' => [23]],
            ['exitCode' => $exitCode, 'lines' => array_column(json_decode($output, true, flags: JSON_THROW_ON_ERROR)['files'][$test]['messages'] ?? [], 'line')],
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function config(array $parameters): string
    {
        $includes = $parameters['includes'];
        unset($parameters['includes']);
        $file = $this->workspace . '/phpstan-' . bin2hex(random_bytes(4)) . '.neon';
        file_put_contents($file, json_encode(['includes' => $includes, 'parameters' => $parameters], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $file;
    }

    /**
     * @param list<string> $options
     * @return array{int, string}
     */
    private function phpstan(string $config, array $options, string ...$paths): array
    {
        $command = sprintf(
            'cd %s && %s %s analyse --no-progress --no-interaction --memory-limit=512M -c %s %s 2>/dev/null',
            escapeshellarg($this->workspace),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../../vendor/bin/phpstan'),
            escapeshellarg($config),
            implode(' ', array_map(escapeshellarg(...), [...$options, ...$paths])),
        );
        exec($command, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    private function git(string ...$arguments): void
    {
        exec(sprintf('git -C %s %s', escapeshellarg($this->workspace), implode(' ', array_map(escapeshellarg(...), $arguments))));
    }
}
