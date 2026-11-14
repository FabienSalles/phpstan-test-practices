<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
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
final readonly class SpyOverMockRule implements Rule
{
    public const LEVEL = 3;

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
        if (!Level::enables($this->level, self::LEVEL)) {
            return [];
        }

        $method = $node->getOriginalNode();
        if (!TestMethod::isTest($method, $node->getClassReflection())) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], TestMethod::isMockExpectation(...)) as $call) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() calls %s(), which sets a verification before the act (mock). Prefer a spy verified after the act.',
                $method->name->toString(),
                TestMethod::callName($call),
            ))
                ->identifier('testPractices.spyOverMock')
                ->line($call->getStartLine())
                ->tip('Stub the call, act, then verify with Prophecy ->shouldHaveBeenCalled() or by reading a hand-written double.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }
}
