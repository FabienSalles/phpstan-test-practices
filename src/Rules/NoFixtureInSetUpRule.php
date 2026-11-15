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
final readonly class NoFixtureInSetUpRule implements Rule
{
    public const LEVEL = 3;
    private const SET_UP = ['setup', 'setupbeforeclass'];
    private const SET_UP_ATTRIBUTES = ['PHPUnit\Framework\Attributes\Before', 'PHPUnit\Framework\Attributes\BeforeClass'];

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTestCase($node->getClassReflection()) || !self::isSetUp($method)) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Assign && ($node->var instanceof Expr\PropertyFetch || $node->var instanceof Expr\StaticPropertyFetch)) as $assign) {
            if (!$assign instanceof Expr\Assign) {
                continue;
            }

            $kind = self::kind($assign->expr);
            if ($kind === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s() creates a %s shared by every test of the class: the scenario of each test is hidden in %s().',
                $method->name->toString(),
                $kind,
                $method->name->toString(),
            ))
                ->identifier('testPractices.noFixtureInSetUp')
                ->line($assign->getStartLine())
                ->tip('Create it in the arrange of the tests that need it, with a helper or a factory when several do; keep setUp() for the environment (kernel, container, clock).')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isSetUp(Node\Stmt\ClassMethod $method): bool
    {
        if (in_array($method->name->toLowerString(), self::SET_UP, true)) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array($attribute->name->toString(), self::SET_UP_ATTRIBUTES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function kind(Expr $value): ?string
    {
        $root = $value;
        while ($root instanceof Expr\MethodCall) {
            if (TestMethod::isDoubleFactory($root)) {
                return 'test double';
            }

            $root = $root->var;
        }

        if (TestMethod::isDoubleFactory($root)) {
            return 'test double';
        }

        return $root instanceof Expr\New_ ? 'fixture' : null;
    }
}
