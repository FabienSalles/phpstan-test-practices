<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class ExpectExceptionBeforeActRule implements Rule
{
    public const LEVEL = 0;

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

        $stmts = array_values($method->stmts ?? []);
        $expectation = null;
        foreach ($stmts as $position => $stmt) {
            if ($stmt instanceof Stmt\Expression && ($stmt->expr instanceof Expr\MethodCall || $stmt->expr instanceof Expr\StaticCall) && str_starts_with(strtolower((string) TestMethod::callName($stmt->expr)), 'expectexception')) {
                $expectation = $position;
            }
        }

        $deadCode = $expectation === null ? null : ($stmts[$expectation + 2] ?? null);
        if ($deadCode === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test %s() has statements after the act that follows expectException(): the act throws, so they never run.',
                $method->name->toString(),
            ))
                ->identifier('testPractices.expectExceptionBeforeAct')
                ->line($deadCode->getStartLine())
                ->tip('Make the act the last statement after expectException(); move what must be checked into another test.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
