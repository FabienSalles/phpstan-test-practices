<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Corpus;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorpusTest extends TestCase
{
    private const CORPUS = __DIR__ . '/../Fixtures/Corpus';

    /**
     * @param list<string> $expectedFindings
     */
    #[DataProvider('provideCorpora')]
    public function testReportsTheExpectedFindings(string $corpus, array $expectedFindings): void
    {
        $findings = self::analyse($corpus);

        self::assertSame($expectedFindings, $findings);
    }

    /**
     * @return iterable<string, array{corpus: string, expectedFindings: list<string>}>
     */
    public static function provideCorpora(): iterable
    {
        yield 'the bad tests of the exercise' => [
            'corpus' => 'main',
            'expectedFindings' => [
                'Calculator/OrderDiscountCalculatorTest.php:30 testPractices.noComputedExpectedValue',
                'Controller/OrderControllerTest.php:21 testPractices.noProductionConstantAsExpected',
                'Processor/OrderProcessorTest.php:30 testPractices.assertionCount',
                'Processor/OrderProcessorTest.php:30 testPractices.oneVerifiedDoublePerTest',
                'Processor/OrderProcessorTest.php:35 testPractices.spyOverMock',
                'Processor/OrderProcessorTest.php:40 testPractices.spyOverMock',
                'Processor/OrderProcessorTest.php:44 testPractices.assertionsLast',
                'Processor/OrderProcessorTest.php:45 testPractices.assertionsLast',
                'Processor/OrderProcessorTest.php:46 testPractices.assertionsLast',
                'Processor/OrderProcessorTest.php:47 testPractices.assertionsLast',
                'Processor/OrderProcessorTest.php:50 testPractices.spyOverMock',
            ],
        ];

        yield 'the fixed tests of the exercise' => [
            'corpus' => 'solution',
            'expectedFindings' => [
                'Calculator/OrderDiscountCalculatorTest.php:30 testPractices.noComputedExpectedValue',
                'Processor/OrderProcessorTest.php:21 testPractices.preferInMemoryDouble',
                'Processor/OrderProcessorTest.php:22 testPractices.spyOverMock',
                'Processor/OrderProcessorTest.php:89 testPractices.preferInMemoryDouble',
                'Processor/OrderProcessorTest.php:90 testPractices.preferInMemoryDouble',
            ],
        ];

        yield 'the slide examples' => [
            'corpus' => 'slides',
            'expectedFindings' => [
                'SlideExamplesTest.php:23 testPractices.noGuardAssertion',
                'SlideExamplesTest.php:24 testPractices.assertionsLast',
                'SlideExamplesTest.php:29 testPractices.assertionCount',
                'SlideExamplesTest.php:34 testPractices.noGuardAssertion',
                'SlideExamplesTest.php:35 testPractices.noGuardAssertion',
                'SlideExamplesTest.php:36 testPractices.noGuardAssertion',
                'SlideExamplesTest.php:40 testPractices.oneVerifiedDoublePerTest',
                'SlideExamplesTest.php:43 testPractices.preferInMemoryDouble',
                'SlideExamplesTest.php:44 testPractices.spyOverMock',
                'SlideExamplesTest.php:46 testPractices.spyOverMock',
            ],
        ];

        yield 'the HTTP client exercise' => [
            'corpus' => 'phpunit-exercice',
            'expectedFindings' => [],
        ];

        yield 'the mailer exercise' => [
            'corpus' => 'phpunit-mailer-exercice',
            'expectedFindings' => [
                'Service/OrderNotificationManagerTest.php:36 testPractices.preferInMemoryDouble',
                'Spi/Mailer/ApiEmailSenderTest.php:18 testPractices.assertionCount',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function analyse(string $corpus): array
    {
        $directory = self::CORPUS . '/' . $corpus;
        $command = sprintf(
            '%s %s analyse --no-progress --no-interaction --memory-limit=512M --error-format=json -c %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../../vendor/bin/phpstan'),
            escapeshellarg($directory . '/phpstan.neon'),
            escapeshellarg($directory . '/tests'),
        );
        exec($command, $output);

        /** @var array{files: array<string, array{messages: list<array{line: int, identifier?: string}>}>} $result */
        $result = json_decode(implode("\n", $output), true, flags: JSON_THROW_ON_ERROR);
        $findings = [];
        foreach ($result['files'] as $file => $report) {
            foreach ($report['messages'] as $message) {
                if (!str_starts_with($message['identifier'] ?? '', 'testPractices.')) {
                    continue;
                }

                $findings[] = sprintf('%s:%d %s', substr($file, strlen(realpath($directory . '/tests') . '/')), $message['line'], $message['identifier'] ?? '');
            }
        }
        sort($findings);

        return $findings;
    }
}
