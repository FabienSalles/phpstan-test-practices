<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * @implements Rule<Expr\CallLike>
 */
final readonly class NoReflectionOnSutRule implements Rule
{
    public const LEVEL = 2;
    private const ACCESSORS = [
        'ReflectionMethod' => ['setaccessible', 'invoke', 'invokeargs', 'getclosure'],
        'ReflectionProperty' => ['setaccessible', 'getvalue', 'setvalue'],
        'Closure' => ['bindto', 'call'],
    ];

    public function __construct(
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
        $class = $scope->getClassReflection();
        if (!Level::enables($this->level, self::LEVEL) || $class === null || !TestMethod::isTestCase($class) || !self::reachesPrivateState($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'The test calls %s() to reach private state: it is coupled to how the class is written, not to what it does.',
                self::describe($node),
            ))
                ->identifier('testPractices.noReflectionOnSut')
                ->tip('Test the behaviour through the public methods that use this state; if it cannot be reached, it may deserve its own class.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }

    private static function reachesPrivateState(Expr\CallLike $node, Scope $scope): bool
    {
        if ($node instanceof Expr\StaticCall) {
            return $node->class instanceof Node\Name && strtolower(ltrim($node->class->toString(), '\\')) === 'closure' && strtolower((string) TestMethod::callName($node)) === 'bind';
        }

        if (!$node instanceof Expr\MethodCall) {
            return false;
        }

        $name = strtolower((string) TestMethod::callName($node));
        $receiver = $scope->getType($node->var);
        foreach (self::ACCESSORS as $type => $methods) {
            if (in_array($name, $methods, true) && (new ObjectType($type))->isSuperTypeOf($receiver)->yes()) {
                return true;
            }
        }

        return false;
    }

    private static function describe(Expr\CallLike $node): string
    {
        return $node instanceof Expr\StaticCall ? 'Closure::bind' : (string) TestMethod::callName($node);
    }
}
