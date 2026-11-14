<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

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
    private const COMPARISONS = ['assertequals', 'assertsame'];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $severity,
    ) {
    }

    public function getNodeType(): string
    {
        return Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!TestMethod::isPhpUnitAssertion($node) || !in_array(strtolower((string) TestMethod::callName($node)), self::COMPARISONS, true)) {
            return [];
        }

        $class = $scope->getClassReflection();
        if ($class === null || !TestMethod::isTestCase($class) || $node->isFirstClassCallable() || count($node->getArgs()) < 2) {
            return [];
        }

        $expected = $node->getArgs()[0]->value;
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

        return [
            RuleErrorBuilder::message(sprintf(
                'Expected value %s::%s is a production constant: the assertion compares the constant with itself. Write the literal value instead.',
                $owner->getDisplayName(),
                $expected->name->toString(),
            ))
                ->identifier('testPractices.noProductionConstantAsExpected')
                ->tip('If the constant itself is the contract under test, keep it and add "// @phpstan-ignore testPractices.noProductionConstantAsExpected" on this line.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
