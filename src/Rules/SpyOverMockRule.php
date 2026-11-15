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
final readonly class SpyOverMockRule implements Rule
{
    public const LEVEL = 3;
    private const SPY_VERIFICATIONS = [
        'shouldbecalled' => 'shouldHaveBeenCalled',
        'shouldbecalledonce' => 'shouldHaveBeenCalledOnce',
        'shouldbecalledtimes' => 'shouldHaveBeenCalledTimes',
        'shouldnotbecalled' => 'shouldNotHaveBeenCalled',
    ];

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
        if (!Level::enables($this->level, self::LEVEL)) {
            return [];
        }

        $method = $node->getOriginalNode();
        if (!TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], TestMethod::isMockExpectation(...)) as $call) {
            $error = RuleErrorBuilder::message(sprintf(
                'Test %s() calls %s(), which sets a verification before the act (mock). Prefer a spy verified after the act.',
                $method->name->toString(),
                TestMethod::callName($call),
            ))
                ->identifier('testPractices.spyOverMock')
                ->line($call->getStartLine())
                ->tip('Stub the call, act, then verify with Prophecy ->shouldHaveBeenCalled() or by reading a hand-written double.')
                ->metadata(Severity::metadata($this->severity));
            $position = self::movablePosition($method, $call);
            if ($position !== null) {
                $error->fixNode($method, static fn (Stmt\ClassMethod $fixed): Stmt\ClassMethod => self::verifiedAfterTheAct($fixed, $position));
            }

            $errors[] = $error->build();
        }

        return $errors;
    }

    private static function movablePosition(Stmt\ClassMethod $method, Node $call): ?int
    {
        if (!$call instanceof Expr\MethodCall || !isset(self::SPY_VERIFICATIONS[strtolower((string) TestMethod::callName($call))])) {
            return null;
        }

        $prophecy = $call->var;
        if (!$prophecy instanceof Expr\MethodCall || TestMethod::verifiedDouble($prophecy) === null || $prophecy->var instanceof Expr\MethodCall) {
            return null;
        }

        foreach ($method->stmts ?? [] as $position => $stmt) {
            if ($stmt instanceof Stmt\Expression && $stmt->expr === $call) {
                return $position;
            }
        }

        return null;
    }

    private static function verifiedAfterTheAct(Stmt\ClassMethod $method, int $position): Stmt\ClassMethod
    {
        $stmts = $method->stmts ?? [];
        $expectation = $stmts[$position] ?? null;
        $call = $expectation instanceof Stmt\Expression ? $expectation->expr : null;
        if (!$call instanceof Expr\MethodCall || !$call->name instanceof Node\Identifier) {
            return $method;
        }

        unset($stmts[$position]);
        $stmts[] = new Stmt\Expression(new Expr\MethodCall(
            $call->var,
            self::SPY_VERIFICATIONS[$call->name->toLowerString()],
            $call->args,
        ));
        $method->stmts = array_values($stmts);

        return $method;
    }
}
