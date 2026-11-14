<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

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
final readonly class NoComputedExpectedValueRule implements Rule
{
    private const COMPARISONS = ['assertequals', 'assertsame'];
    private const ARITHMETIC = [
        Expr\BinaryOp\Div::class,
        Expr\BinaryOp\Minus::class,
        Expr\BinaryOp\Mod::class,
        Expr\BinaryOp\Mul::class,
        Expr\BinaryOp\Plus::class,
        Expr\BinaryOp\Pow::class,
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

        $stmts = $method->stmts ?? [];
        $assigned = [];
        foreach (TestMethod::find($stmts, static fn (Node $n): bool => $n instanceof Expr\Assign) as $assign) {
            if ($assign instanceof Expr\Assign && $assign->var instanceof Expr\Variable && is_string($assign->var->name)) {
                $assigned[$assign->var->name] = $assign->expr;
            }
        }

        $errors = [];
        foreach (TestMethod::find($stmts, self::isComparison(...)) as $call) {
            if (!$call instanceof Expr\CallLike || $call->isFirstClassCallable() || count($call->getArgs()) < 2) {
                continue;
            }

            $expected = $call->getArgs()[0]->value;
            if ($expected instanceof Expr\Variable && is_string($expected->name)) {
                $expected = $assigned[$expected->name] ?? $expected;
            }

            $computation = TestMethod::find($expected, self::isComputation(...))[0] ?? null;
            if ($computation === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() computes its expected value (%s). An expectation recomputed in the test mirrors the implementation; write the literal expected result.',
                $method->name->toString(),
                self::describe($computation),
            ))
                ->identifier('testPractices.noComputedExpectedValue')
                ->line($call->getStartLine())
                ->tip('Hard-code the expected value of each case, for instance as a data provider column.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isComparison(Node $node): bool
    {
        return TestMethod::isPhpUnitAssertion($node) && in_array(strtolower((string) TestMethod::callName($node)), self::COMPARISONS, true);
    }

    private static function isComputation(Node $node): bool
    {
        return $node instanceof Expr\Match_ || $node instanceof Expr\Ternary || in_array($node::class, self::ARITHMETIC, true);
    }

    private static function describe(Node $node): string
    {
        return match (true) {
            $node instanceof Expr\Match_ => 'match expression',
            $node instanceof Expr\Ternary => 'ternary expression',
            $node instanceof Expr\BinaryOp => sprintf('arithmetic "%s"', $node->getOperatorSigil()),
            default => $node->getType(),
        };
    }
}
