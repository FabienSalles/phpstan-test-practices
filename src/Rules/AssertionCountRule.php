<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class AssertionCountRule implements Rule
{
    public function __construct(
        private int $warningFrom,
        private int $errorFrom,
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

        $count = count(TestMethod::outermostAssertions($method->stmts ?? []));
        if ($count < min($this->warningFrom, $this->errorFrom)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test %s() makes %d assertions. Prefer one structured assertion, or split the test by intention.',
                $method->name->toString(),
                $count,
            ))
                ->identifier('testPractices.assertionCount')
                ->line($method->getStartLine())
                ->tip('Compare every expected field at once: self::assertSame([\'status\' => \'CONFIRMED\', \'total\' => 120.0], [\'status\' => $order->status, \'total\' => $order->total]).')
                ->metadata(Severity::metadata($count >= $this->errorFrom ? Severity::ERROR : Severity::WARNING))
                ->build(),
        ];
    }
}
