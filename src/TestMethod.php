<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PHPStan\Reflection\ClassReflection;

final class TestMethod
{
    private const TEST_CASE = 'PHPUnit\Framework\TestCase';
    private const TEST_ATTRIBUTE = 'PHPUnit\Framework\Attributes\Test';
    private const HOOKS = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass', 'assertpreconditions', 'assertpostconditions'];
    private const HOOK_ATTRIBUTES = [
        'PHPUnit\Framework\Attributes\After',
        'PHPUnit\Framework\Attributes\AfterClass',
        'PHPUnit\Framework\Attributes\Before',
        'PHPUnit\Framework\Attributes\BeforeClass',
        'PHPUnit\Framework\Attributes\PostCondition',
        'PHPUnit\Framework\Attributes\PreCondition',
    ];
    private const DOUBLE_FACTORIES = [
        'createconfiguredmock',
        'createconfiguredstub',
        'createmock',
        'createpartialmock',
        'createstub',
        'getmockbuilder',
        'getmockforabstractclass',
        'prophesize',
    ];
    private const SPY_VERIFICATIONS = [
        'shouldhave',
        'shouldhavebeencalled',
        'shouldhavebeencalledonce',
        'shouldhavebeencalledtimes',
        'shouldnotbeencalled',
        'shouldnothavebeencalled',
    ];
    private const MOCK_EXPECTATIONS = [
        'expects',
        'should',
        'shouldbecalled',
        'shouldbecalledonce',
        'shouldbecalledtimes',
        'shouldnotbecalled',
    ];

    public static function isTestCase(ClassReflection $class): bool
    {
        return $class->is(self::TEST_CASE);
    }

    public static function isTest(Stmt\ClassMethod $method, ClassReflection $class): bool
    {
        if (!self::isTestCase($class) || !$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
            return false;
        }

        if (str_starts_with($method->name->toLowerString(), 'test')) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === self::TEST_ATTRIBUTE) {
                    return true;
                }
            }
        }

        $doc = $method->getDocComment();

        return $doc !== null && preg_match('/@test\b/', $doc->getText()) === 1;
    }

    public static function isHook(Stmt\ClassMethod $method): bool
    {
        if (in_array($method->name->toLowerString(), self::HOOKS, true)) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array($attribute->name->toString(), self::HOOK_ATTRIBUTES, true)) {
                    return true;
                }
            }
        }

        $doc = $method->getDocComment();

        return $doc !== null && preg_match('/@(before|after|beforeClass|afterClass|preCondition|postCondition)\b/', $doc->getText()) === 1;
    }

    public static function callName(Node $node): ?string
    {
        if (($node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall) && $node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }

    public static function isPhpUnitAssertion(Node $node): bool
    {
        if (!str_starts_with(strtolower(self::callName($node) ?? ''), 'assert')) {
            return false;
        }

        return $node instanceof Expr\StaticCall || ($node instanceof Expr\MethodCall && self::isThis($node->var));
    }

    public static function isSpyVerification(Node $node): bool
    {
        return $node instanceof Expr\MethodCall && in_array(strtolower(self::callName($node) ?? ''), self::SPY_VERIFICATIONS, true);
    }

    public static function isMockExpectation(Node $node): bool
    {
        return $node instanceof Expr\MethodCall && in_array(strtolower(self::callName($node) ?? ''), self::MOCK_EXPECTATIONS, true);
    }

    public static function isVerification(Node $node): bool
    {
        return self::isMockExpectation($node) || self::isSpyVerification($node);
    }

    public static function isAssertion(Node $node): bool
    {
        return self::isPhpUnitAssertion($node) || self::isSpyVerification($node);
    }

    public static function isAssertionStatement(Stmt $stmt): bool
    {
        return $stmt instanceof Stmt\Expression && self::isAssertion($stmt->expr);
    }

    /**
     * @param Node|array<Node> $nodes
     * @param callable(Node): bool $filter
     * @return list<Node>
     */
    public static function find(Node|array $nodes, callable $filter): array
    {
        return array_values((new NodeFinder())->find($nodes, $filter));
    }

    /**
     * @param Node|array<mixed> $node
     * @return list<Expr>
     */
    public static function outermostAssertions(Node|array $node): array
    {
        if ($node instanceof Expr && self::isAssertion($node)) {
            return [$node];
        }

        $children = is_array($node) ? $node : array_map(static fn (string $name): mixed => $node->{$name}, $node->getSubNodeNames());
        $found = [];
        foreach ($children as $child) {
            if ($child instanceof Node || is_array($child)) {
                $found = [...$found, ...self::outermostAssertions($child)];
            }
        }

        return $found;
    }

    public static function withoutStatement(Stmt\ClassMethod $method, int $position): Stmt\ClassMethod
    {
        $stmts = $method->stmts ?? [];
        unset($stmts[$position]);
        $method->stmts = array_values($stmts);

        return $method;
    }

    public static function isDoubleFactory(Node $node): bool
    {
        if (!in_array(strtolower(self::callName($node) ?? ''), self::DOUBLE_FACTORIES, true)) {
            return false;
        }

        return $node instanceof Expr\StaticCall || ($node instanceof Expr\MethodCall && self::isThis($node->var));
    }

    public static function doubledClass(Node $call, string $testClass): ?string
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

    /**
     * @param Node|array<Node> $nodes
     * @param callable(Node): bool $filter
     * @return list<Node>
     */
    public static function findOutsideClosures(Node|array $nodes, callable $filter): array
    {
        $found = [];
        foreach (is_array($nodes) ? $nodes : [$nodes] as $node) {
            if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction || $node instanceof Stmt\Class_) {
                continue;
            }

            if ($filter($node)) {
                $found[] = $node;
            }

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};
                $children = array_values(array_filter(is_array($child) ? $child : [$child], static fn (mixed $item): bool => $item instanceof Node));
                $found = [...$found, ...self::findOutsideClosures($children, $filter)];
            }
        }

        return $found;
    }

    public static function expectedArgument(Expr\CallLike $call, ClassReflection $class): ?Expr
    {
        $method = (string) self::callName($call);
        if ($call->isFirstClassCallable() || !$class->hasNativeMethod($method)) {
            return null;
        }

        $parameter = $class->getNativeMethod($method)->getOnlyVariant()->getParameters()[0] ?? null;
        if ($parameter === null || !str_starts_with($parameter->getName(), 'expected')) {
            return null;
        }

        foreach ($call->getArgs() as $position => $argument) {
            if ($argument->name?->toString() === $parameter->getName() || ($position === 0 && $argument->name === null && !$argument->unpack)) {
                return $argument->value;
            }
        }

        return null;
    }

    public static function rootVariable(Expr $expr): ?string
    {
        while ($expr instanceof Expr\MethodCall || $expr instanceof Expr\PropertyFetch || $expr instanceof Expr\ArrayDimFetch || $expr instanceof Expr\NullsafeMethodCall || $expr instanceof Expr\NullsafePropertyFetch) {
            $expr = $expr->var;
        }

        return $expr instanceof Expr\Variable && is_string($expr->name) && $expr->name !== 'this' ? $expr->name : null;
    }

    public static function verifiedDouble(Expr\MethodCall $call): ?string
    {
        $owner = $call->var;
        while ($owner instanceof Expr\MethodCall) {
            $owner = $owner->var;
        }

        return self::describe($owner);
    }

    private static function describe(Expr $expr): ?string
    {
        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            return '$' . $expr->name;
        }

        if ($expr instanceof Expr\PropertyFetch && $expr->name instanceof Node\Identifier) {
            $owner = self::describe($expr->var);

            return $owner === null ? null : $owner . '->' . $expr->name->toString();
        }

        return null;
    }

    private static function isThis(Expr $expr): bool
    {
        return $expr instanceof Expr\Variable && $expr->name === 'this';
    }
}
