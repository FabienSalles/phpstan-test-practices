<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoControlFlowInTestRule implements Rule
{
    public const LEVEL = 1;
    private const STATEMENTS = [
        Stmt\If_::class => 'an if',
        Stmt\For_::class => 'a for',
        Stmt\Foreach_::class => 'a foreach',
        Stmt\While_::class => 'a while',
        Stmt\Do_::class => 'a do-while',
        Stmt\Switch_::class => 'a switch',
    ];

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::findOutsideClosures($method->stmts ?? [], self::isControlFlow(...)) as $flow) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() contains %s: a test runs one straight path, otherwise some of its branches are never checked.',
                $method->name->toString(),
                self::STATEMENTS[$flow::class] ?? 'a match with side effects',
            ))
                ->identifier('testPractices.noControlFlowInTest')
                ->line($flow->getStartLine())
                ->tip('Write one test per case, use a data provider, or move the loop into a helper outside the test method.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isControlFlow(Node $node): bool
    {
        if (isset(self::STATEMENTS[$node::class])) {
            return true;
        }

        if (!$node instanceof Expr\Match_) {
            return false;
        }

        foreach ($node->arms as $arm) {
            if (TestMethod::find($arm->body, static fn (Node $inner): bool => $inner instanceof Expr\CallLike || $inner instanceof Expr\Assign || $inner instanceof Expr\Throw_) !== []) {
                return true;
            }
        }

        return false;
    }
}
