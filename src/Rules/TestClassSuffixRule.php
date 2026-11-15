<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
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
final readonly class TestClassSuffixRule implements Rule
{
    public const LEVEL = 0;

    public function __construct(
        private string $suffix,
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTestCase($class) || $class->isAbstract() || $class->isAnonymous()) {
            return [];
        }

        if (str_ends_with($class->getName(), $this->suffix)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test class %s does not end with "%s": PHPUnit only collects the files whose name ends with the suffix of the <directory> that holds them, "%s.php" by default.',
                $class->getDisplayName(),
                $this->suffix,
                $this->suffix,
            ))
                ->identifier('testPractices.testClassSuffix')
                ->tip(sprintf('Rename the class and its file with the "%s" suffix.', $this->suffix))
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
