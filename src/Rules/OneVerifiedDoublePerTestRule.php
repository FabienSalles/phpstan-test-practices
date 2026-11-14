<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

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
final readonly class OneVerifiedDoublePerTestRule implements Rule
{
    public function __construct(
        private string $severity,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $node->getOriginalNode();
        if (!TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $doubles = [];
        foreach (TestMethod::find($method->stmts ?? [], TestMethod::isVerification(...)) as $call) {
            if (!$call instanceof Expr\MethodCall) {
                continue;
            }

            $double = TestMethod::verifiedDouble($call) ?? sprintf('(anonymous, line %d)', $call->getStartLine());
            $doubles[$double] = $double;
        }

        if (count($doubles) <= 1) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test %s() verifies %d doubles (%s). One intention per test: verify a single double.',
                $method->name->toString(),
                count($doubles),
                implode(', ', $doubles),
            ))
                ->identifier('testPractices.oneVerifiedDoublePerTest')
                ->line($method->getStartLine())
                ->tip('Split the test by intention, one verified double each; keep the other collaborators as stubs.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
