<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\PhpUnit\TestSuites;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final readonly class TestFileInTestsuiteRule implements Rule
{
    public const LEVEL = 0;

    private TestSuites $testSuites;

    public function __construct(
        ?string $configuration,
        string $workingDirectory,
        private string $severity,
        private int|string $level,
    ) {
        $this->testSuites = TestSuites::load($configuration, $workingDirectory);
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!Level::enables($this->level, self::LEVEL) || $this->testSuites->configuration === null || !TestMethod::isTestCase($class) || $class->isAbstract() || $class->isAnonymous()) {
            return [];
        }

        if ($this->testSuites->includes($scope->getFile())) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test class %s is in a file that no <testsuite> of %s includes: PHPUnit never runs it.',
                $class->getDisplayName(),
                basename($this->testSuites->configuration),
            ))
                ->identifier('testPractices.testFileInTestsuite')
                ->tip('Move the file under a <directory> of a test suite (with its suffix and prefix), add a <file> entry, or remove it from <exclude>.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
