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
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class ProphecyOverCreateMockCallbackRule implements Rule
{
    public const LEVEL = 3;
    private const PHPUNIT_FACTORIES = ['createmock', 'createstub', 'getmockbuilder', 'createpartialmock', 'createconfiguredmock', 'createconfiguredstub'];
    private const SCRIPTING = ['willreturncallback', 'will'];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
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
        if (!Level::enables($this->level, self::LEVEL) || !$this->reflectionProvider->hasClass('Prophecy\Prophet') || !TestMethod::isTest($method, $class)) {
            return [];
        }

        $doubles = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) as $assign) {
            if (!$assign instanceof Expr\Assign || !$assign->var instanceof Expr\Variable || !is_string($assign->var->name)) {
                continue;
            }

            $factory = $assign->expr;
            while ($factory instanceof Expr\MethodCall && !TestMethod::isDoubleFactory($factory)) {
                $factory = $factory->var;
            }

            if (in_array(strtolower(TestMethod::callName($factory) ?? ''), self::PHPUNIT_FACTORIES, true) && TestMethod::isDoubleFactory($factory)) {
                $doubles[$assign->var->name] = TestMethod::doubledClass($factory, $class->getName());
            }
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\MethodCall && in_array(strtolower(TestMethod::callName($node) ?? ''), self::SCRIPTING, true)) as $call) {
            $double = $call instanceof Expr\MethodCall ? TestMethod::rootVariable($call) : null;
            if ($double === null || !array_key_exists($double, $doubles) || !$this->prophecyCanDouble($doubles[$double])) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() scripts a PHPUnit double with %s(): a double that needs behaviour reads better as a Prophecy double or a hand-written one.',
                $method->name->toString(),
                TestMethod::callName($call),
            ))
                ->identifier('testPractices.prophecyOverCreateMockCallback')
                ->line($call->getStartLine())
                ->tip('Use $this->prophesize() with ->will(...) or ->willReturn(...) per argument, or write a small in-memory implementation.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private function prophecyCanDouble(?string $doubled): bool
    {
        if ($doubled === null || !$this->reflectionProvider->hasClass($doubled)) {
            return true;
        }

        $class = $this->reflectionProvider->getClass($doubled);

        return !$class->isFinal() || $class->getInterfaces() !== [];
    }
}
