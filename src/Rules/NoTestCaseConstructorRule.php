<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoTestCaseConstructorRule implements Rule
{
    public const LEVEL = 1;

    public function __construct(
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $node->getOriginalNode();
        if (!Level::enables($this->level, self::LEVEL) || $method->name->toLowerString() !== '__construct' || !TestMethod::isTestCase($node->getClassReflection())) {
            return [];
        }

        return [
            RuleErrorBuilder::message('A test case declares a constructor: PHPUnit creates one instance per test with its own arguments, and the constructor runs for every test, before setUp().')
                ->identifier('testPractices.noTestCaseConstructor')
                ->tip('Build what a test needs in its arrange, or the environment in setUp().')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
