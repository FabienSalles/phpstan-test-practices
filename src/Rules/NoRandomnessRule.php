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
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoRandomnessRule implements Rule
{
    public const LEVEL = 1;
    private const FUNCTIONS = ['rand', 'mt_rand', 'random_int', 'random_bytes', 'lcg_value', 'uniqid', 'shuffle', 'array_rand', 'str_shuffle'];
    private const STATIC_CALLS = [
        'ramsey\uuid\uuid::uuid4',
        'symfony\component\uid\uuid::v4',
        'symfony\component\uid\uuid::v7',
        'symfony\component\uid\ulid::generate',
    ];
    private const FAKER = 'faker\factory::create';

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
        $method = $node->getOriginalNode();
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTestCase($node->getClassReflection())) {
            return [];
        }

        $seeded = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => strtolower(TestMethod::callName($node) ?? '') === 'seed') as $seed) {
            $faker = $seed instanceof Expr\MethodCall ? TestMethod::rootVariable($seed) : null;
            if ($faker !== null) {
                $seeded[$faker] = true;
            }
        }

        $seededFakers = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name) && self::name($node->expr) === self::FAKER) as $assign) {
            if ($assign instanceof Expr\Assign && $assign->var instanceof Expr\Variable && is_string($assign->var->name) && isset($seeded[$assign->var->name])) {
                $seededFakers[spl_object_id($assign->expr)] = false;
            }
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => self::isRandom($node) && !array_key_exists(spl_object_id($node), $seededFakers)) as $call) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s() draws a random value with %s(): the test may behave differently on every run.',
                $method->name->toString(),
                self::display($call),
            ))
                ->identifier('testPractices.noRandomness')
                ->line($call->getStartLine())
                ->tip('Use fixed values, or inject a generator returning a fixed sequence; seed Faker with $faker->seed(...) when you need it.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isRandom(Node $node): bool
    {
        $name = self::name($node);

        return $name !== null && ($node instanceof Expr\FuncCall ? in_array($name, self::FUNCTIONS, true) : in_array($name, [...self::STATIC_CALLS, self::FAKER], true));
    }

    private static function display(Node $node): string
    {
        return match (true) {
            $node instanceof Expr\FuncCall && $node->name instanceof Node\Name => ltrim($node->name->toString(), '\\'),
            $node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier => $node->class->getLast() . '::' . $node->name->toString(),
            default => '',
        };
    }

    private static function name(Node $node): ?string
    {
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            return strtolower(ltrim($node->name->toString(), '\\'));
        }

        if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier) {
            return strtolower(ltrim($node->class->toString(), '\\') . '::' . $node->name->toString());
        }

        return null;
    }
}
