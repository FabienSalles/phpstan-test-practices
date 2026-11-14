<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoGuardAssertionRule implements Rule
{
    private const GUARDS = [
        'assertarrayhaskey',
        'assertcount',
        'assertinstanceof',
        'assertnotempty',
        'assertnotfalse',
        'assertnotnull',
        'assertobjecthasproperty',
    ];

    public function __construct(
        private string $severity,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $node->getOriginalNode();
        if (!TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $assertions = array_values(array_filter($method->stmts ?? [], TestMethod::isAssertionStatement(...)));
        $errors = [];
        foreach (array_slice($assertions, 0, -1) as $stmt) {
            if (!$stmt instanceof Stmt\Expression || !TestMethod::isPhpUnitAssertion($stmt->expr)) {
                continue;
            }

            $name = (string) TestMethod::callName($stmt->expr);
            if (!self::isGuard($name)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() calls %s() as a guard before the final assertion. Drop the guard and let the final assertion show the full diff.',
                $method->name->toString(),
                $name,
            ))
                ->identifier('testPractices.noGuardAssertion')
                ->line($stmt->getStartLine())
                ->tip('Read the value permissively ($result[0] ?? null, $object?->property) so the final assertion fails with a readable diff.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isGuard(string $name): bool
    {
        $name = strtolower($name);

        return in_array($name, self::GUARDS, true) || str_starts_with($name, 'assertis');
    }
}
