<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Collectors;

use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassMethodNode;

/**
 * @phpstan-type TestDouble array{dependency: string, file: string, line: int}
 * @implements Collector<InClassMethodNode, array{class: string, method: string, test: bool, fromTrait: bool, doubles: list<TestDouble>, calls: list<string>}>
 */
final readonly class TestDoubleCollector implements Collector
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

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $class = $scope->getClassReflection();
        if ($class === null || !TestMethod::isTestCase($class)) {
            return null;
        }

        $method = $node->getOriginalNode();
        $file = $scope->getTraitReflection()?->getFileName() ?? $scope->getFile();
        $doubles = [];
        foreach (TestMethod::find($method->stmts ?? [], self::isDoubleFactory(...)) as $call) {
            $dependency = self::doubledClass($call, $class->getName());
            if ($dependency !== null) {
                $doubles[] = ['dependency' => $dependency, 'file' => $file, 'line' => $call->getStartLine()];
            }
        }

        return [
            'class' => $class->getName(),
            'method' => $method->name->toString(),
            'test' => TestMethod::isTest($method, $class),
            'fromTrait' => $scope->isInTrait(),
            'doubles' => $doubles,
            'calls' => array_values(array_unique(array_map(
                static fn (Node $call): string => strtolower((string) TestMethod::callName($call)),
                TestMethod::find($method->stmts ?? [], self::isOwnMethodCall(...)),
            ))),
        ];
    }

    private static function isOwnMethodCall(Node $node): bool
    {
        if (TestMethod::callName($node) === null) {
            return false;
        }

        return ($node instanceof Expr\MethodCall && self::isThis($node->var))
            || ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && in_array($node->class->toLowerString(), ['self', 'static'], true));
    }

    private static function isDoubleFactory(Node $node): bool
    {
        if (!in_array(strtolower(TestMethod::callName($node) ?? ''), self::FACTORIES, true)) {
            return false;
        }

        return $node instanceof Expr\StaticCall || ($node instanceof Expr\MethodCall && self::isThis($node->var));
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

    private static function isThis(Expr $expr): bool
    {
        return $expr instanceof Expr\Variable && $expr->name === 'this';
    }
}
