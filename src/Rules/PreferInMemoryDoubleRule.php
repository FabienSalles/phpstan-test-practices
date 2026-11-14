<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final readonly class PreferInMemoryDoubleRule implements Rule
{
    private const FACTORIES = [
        'createconfiguredmock',
        'createconfiguredstub',
        'createmock',
        'createpartialmock',
        'createstub',
        'getmockbuilder',
        'getmockforabstractclass',
        'prophesize',
    ];

    public function __construct(
        private int $threshold,
        private string $severity,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!TestMethod::isTestCase($class)) {
            return [];
        }

        $usages = [];
        foreach ($node->getOriginalNode()->getMethods() as $method) {
            if (!TestMethod::isTest($method, $class)) {
                continue;
            }

            foreach (TestMethod::find($method->stmts ?? [], self::isDoubleFactory(...)) as $call) {
                $dependency = self::doubledClass($call, $class->getName());
                if ($dependency === null) {
                    continue;
                }

                $usages[$dependency][$method->name->toString()] ??= $call->getStartLine();
            }
        }

        $errors = [];
        foreach ($usages as $dependency => $methods) {
            if (count($methods) < $this->threshold) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s is doubled in %d test methods (%s). Prefer a hand-written in-memory stub or fake, reused across the tests.',
                $dependency,
                count($methods),
                implode('(), ', array_keys($methods)) . '()',
            ))
                ->identifier('testPractices.preferInMemoryDouble')
                ->line(min($methods))
                ->tip('An in-memory implementation of the interface keeps each test short and survives changes to how the dependency is called.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isDoubleFactory(Node $node): bool
    {
        if (!in_array(strtolower(TestMethod::callName($node) ?? ''), self::FACTORIES, true)) {
            return false;
        }

        return $node instanceof Expr\StaticCall
            || ($node instanceof Expr\MethodCall && $node->var instanceof Expr\Variable && $node->var->name === 'this');
    }

    private static function doubledClass(Node $call, string $testClass): ?string
    {
        if (!$call instanceof Expr\CallLike || $call->isFirstClassCallable()) {
            return null;
        }

        $argument = $call->getArgs()[0]->value ?? null;
        if (!$argument instanceof Expr\ClassConstFetch || !$argument->class instanceof Node\Name || !$argument->name instanceof Node\Identifier) {
            return null;
        }

        if ($argument->name->toLowerString() !== 'class') {
            return null;
        }

        $name = $argument->class->toString();

        return in_array(strtolower($name), ['self', 'static'], true) ? $testClass : ltrim($name, '\\');
    }
}
