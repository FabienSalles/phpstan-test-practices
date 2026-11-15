<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoStubReturnAsExpectedRule implements Rule
{
    public const LEVEL = 0;
    private const RETURNS = ['willreturn', 'willreturnmap', 'willreturnonconsecutivecalls'];

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
        $class = $node->getClassReflection();
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $class)) {
            return [];
        }

        $printer = new Standard();
        $returned = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\MethodCall && in_array(strtolower(TestMethod::callName($node) ?? ''), self::RETURNS, true)) as $stub) {
            if (!$stub instanceof Expr\MethodCall || $stub->isFirstClassCallable()) {
                continue;
            }

            foreach (TestMethod::find($stub->getArgs(), static fn (Node $node): bool => $node instanceof Expr\Variable || $node instanceof Expr\New_) as $value) {
                if ($value instanceof Expr\Variable || $value instanceof Expr\New_) {
                    $returned[$printer->prettyPrintExpr($value)] = true;
                }
            }
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], TestMethod::isPhpUnitAssertion(...)) as $assertion) {
            $expected = $assertion instanceof Expr\CallLike ? TestMethod::expectedArgument($assertion, $class) : null;
            if ((!$expected instanceof Expr\Variable && !$expected instanceof Expr\New_) || !isset($returned[$printer->prettyPrintExpr($expected)])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() expects %s, the value a stub returns: the assertion checks the stub configuration, not the code under test.',
                $method->name->toString(),
                $printer->prettyPrintExpr($expected),
            ))
                ->identifier('testPractices.noStubReturnAsExpected')
                ->line($assertion->getStartLine())
                ->tip('Assert on what the code computes from the stubbed value, or use the real collaborator (a real serializer, an in-memory repository).')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }
}
