<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class AssertionsLastRule implements Rule
{
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

        $test = $method->name->toString();
        $errors = [];
        $assertionSeen = false;
        foreach ($method->stmts ?? [] as $stmt) {
            if (TestMethod::isAssertionStatement($stmt)) {
                $assertionSeen = true;

                continue;
            }

            if ($assertionSeen) {
                $errors[] = $this->error(
                    sprintf('Test %s() has a statement after an assertion. Assertions come last, in a single final block.', $test),
                    $stmt->getStartLine(),
                    'Move this statement before the first assertion, or split the test if it checks two behaviours.',
                );

                continue;
            }

            foreach (TestMethod::find(TestMethod::find($stmt, self::isArrangeBody(...)), TestMethod::isAssertion(...)) as $hidden) {
                $errors[] = $this->error(
                    sprintf('Test %s() calls %s() inside an arrange callback, closure or anonymous class. Verify after the act, on the result or on a spy.', $test, TestMethod::callName($hidden)),
                    $hidden->getStartLine(),
                    'Let the double record what it receives, then assert on the recorded value in the final block.',
                );
            }
        }

        return array_values(array_unique($errors, SORT_REGULAR));
    }

    private static function isArrangeBody(Node $node): bool
    {
        return $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction || $node instanceof Stmt\Class_;
    }

    private function error(string $message, int $line, string $tip): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('testPractices.assertionsLast')
            ->line($line)
            ->tip($tip)
            ->metadata(Severity::metadata($this->severity))
            ->build();
    }
}
