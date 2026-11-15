<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoUnusedDoubleRule implements Rule
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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], self::isDoubleAssignment(...)) as $assign) {
            if (!$assign instanceof Expr\Assign || !$assign->var instanceof Expr\Variable || !is_string($assign->var->name)) {
                continue;
            }

            $name = $assign->var->name;
            $reads = TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Variable && $node->name === $name && $node !== $assign->var);
            if ($reads !== []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() creates the double $%s and never uses it.',
                $method->name->toString(),
                $name,
            ))
                ->identifier('testPractices.noUnusedDouble')
                ->line($assign->getStartLine())
                ->tip('Remove it, or pass it to the code under test if the test forgot to.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isDoubleAssignment(Node $node): bool
    {
        if (!$node instanceof Expr\Assign || !$node->var instanceof Expr\Variable) {
            return false;
        }

        $value = $node->expr;
        while ($value instanceof Expr\MethodCall && !TestMethod::isDoubleFactory($value)) {
            $value = $value->var;
        }

        return TestMethod::isDoubleFactory($value) || ($value instanceof Expr\New_ && $value->class instanceof Node\Stmt\Class_);
    }
}
