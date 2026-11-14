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
final readonly class NoDependsBetweenTestsRule implements Rule
{
    public const LEVEL = 1;
    private const ATTRIBUTE_PREFIX = 'PHPUnit\Framework\Attributes\Depends';

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $depends = preg_match('/@depends\b/', $method->getDocComment()?->getText() ?? '') === 1;
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $depends = $depends || str_starts_with($attribute->name->toString(), self::ATTRIBUTE_PREFIX);
            }
        }

        if (!$depends) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test %s() depends on another test: it cannot run alone, and it is skipped when the other one fails.',
                $method->name->toString(),
            ))
                ->identifier('testPractices.noDependsBetweenTests')
                ->tip('Build what the test needs in its own arrange, with a helper or a factory shared by both tests.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
