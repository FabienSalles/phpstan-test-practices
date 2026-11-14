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
final readonly class NoTryCatchInTestRule implements Rule
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

        $errors = [];
        foreach (TestMethod::findOutsideClosures($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Stmt\TryCatch) as $try) {
            if (!$try instanceof Stmt\TryCatch || $try->catches === [] || self::endsWithFail($try)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() catches an exception: when nothing is thrown, the catch block never runs and the test passes anyway.',
                $method->name->toString(),
            ))
                ->identifier('testPractices.noTryCatchInTest')
                ->line($try->getStartLine())
                ->tip('Call $this->expectException() before the act, or end the try block with self::fail() when the caught exception itself must be inspected.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function endsWithFail(Stmt\TryCatch $try): bool
    {
        $last = $try->stmts[array_key_last($try->stmts) ?? 0] ?? null;

        return $last instanceof Stmt\Expression
            && ($last->expr instanceof Expr\StaticCall || $last->expr instanceof Expr\MethodCall)
            && strtolower((string) TestMethod::callName($last->expr)) === 'fail';
    }
}
