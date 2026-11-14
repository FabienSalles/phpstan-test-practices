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
 * @phpstan-type Method array{class: string, method: string, test: bool, root: bool, public: bool, asserts: bool, fromTrait: bool, file: string, line: int, doubles: list<TestDouble>, calls: list<string>}
 * @implements Collector<InClassMethodNode, Method>
 */
final readonly class TestCaseMethodCollector implements Collector
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
            'root' => TestMethod::isHook($method),
            'public' => $method->isPublic() && !$method->isStatic(),
            'asserts' => TestMethod::find($method->stmts ?? [], TestMethod::isAssertion(...)) !== [],
            'fromTrait' => $scope->isInTrait(),
            'file' => $file,
            'line' => $method->getStartLine(),
            'doubles' => $doubles,
            'calls' => array_values(array_unique([
                ...array_map(
                    static fn (Node $call): string => strtolower((string) TestMethod::callName($call)),
                    TestMethod::find($method->stmts ?? [], self::isOwnMethodCall(...)),
                ),
                ...self::callableReferences($method),
            ])),
        ];
    }

    /**
     * @return list<string>
     */
    private static function callableReferences(Node\Stmt\ClassMethod $method): array
    {
        $references = [];
        foreach (TestMethod::find($method, static fn (Node $node): bool => $node instanceof Expr\Array_ && count($node->items) === 2) as $array) {
            if (!$array instanceof Expr\Array_) {
                continue;
            }

            $target = $array->items[0]->value ?? null;
            $name = $array->items[1]->value ?? null;
            if ($target instanceof Expr\Variable && $target->name === 'this' && $name instanceof Node\Scalar\String_) {
                $references[] = strtolower($name->value);
            }
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $provider = $attribute->args[0]->value ?? null;
                if ($attribute->name->toString() === 'PHPUnit\Framework\Attributes\DataProvider' && $provider instanceof Node\Scalar\String_) {
                    $references[] = strtolower($provider->value);
                }
            }
        }

        $doc = $method->getDocComment()?->getText() ?? '';
        if (preg_match_all('/@dataProvider\s+(\w+)/', $doc, $matches) > 0) {
            $references = [...$references, ...array_map(strtolower(...), $matches[1])];
        }

        return $references;
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
