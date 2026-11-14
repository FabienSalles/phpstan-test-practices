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
