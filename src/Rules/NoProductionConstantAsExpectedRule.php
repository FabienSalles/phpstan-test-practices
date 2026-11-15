<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Expr\CallLike>
 */
final readonly class NoProductionConstantAsExpectedRule implements Rule
{
    public const LEVEL = 1;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!Level::enables($this->level, self::LEVEL)) {
            return [];
        }

        $class = $scope->getClassReflection();
        if (!TestMethod::isPhpUnitAssertion($node) || $class === null || !TestMethod::isTestCase($class)) {
            return [];
        }

        $expected = TestMethod::expectedArgument($node, $class);
        if (!$expected instanceof Expr\ClassConstFetch || !$expected->class instanceof Node\Name || !$expected->name instanceof Node\Identifier) {
            return [];
        }

        if ($expected->name->toLowerString() === 'class') {
            return [];
        }

        $className = $scope->resolveName($expected->class);
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $owner = $this->reflectionProvider->getClass($className);
        if ($owner->isEnum() || $owner->isBuiltin() || TestMethod::isTestCase($owner)) {
            return [];
        }

        $error = RuleErrorBuilder::message(sprintf(
            'Expected value %s::%s is a production constant: the assertion compares the constant with itself. Write the literal value instead.',
            $owner->getDisplayName(),
            $expected->name->toString(),
        ))
            ->identifier('testPractices.noProductionConstantAsExpected')
            ->tip('If the constant itself is the contract under test, keep it and add "// @phpstan-ignore testPractices.noProductionConstantAsExpected" on this line.')
            ->metadata(Severity::metadata($this->severity));
        $literal = $owner->hasConstant($expected->name->toString()) ? self::literal($owner->getConstant($expected->name->toString())->getValueExpr()) : null;
        if ($literal !== null) {
            $error->fixNode($expected, static fn (): Expr => $literal);
        }

        return [$error->build()];
    }

    private static function literal(Expr $value): ?Expr
    {
        return match (true) {
            $value instanceof Node\Scalar\String_ => new Node\Scalar\String_($value->value),
            $value instanceof Node\Scalar\Int_ => new Node\Scalar\Int_($value->value),
            $value instanceof Node\Scalar\Float_ => new Node\Scalar\Float_($value->value),
            $value instanceof Expr\ConstFetch && in_array($value->name->toLowerString(), ['true', 'false', 'null'], true) => new Expr\ConstFetch(new Node\Name($value->name->toLowerString())),
            default => null,
        };
    }
}
