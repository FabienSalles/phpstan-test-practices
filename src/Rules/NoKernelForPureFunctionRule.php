<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final readonly class NoKernelForPureFunctionRule implements Rule
{
    public const LEVEL = 3;
    private const KERNEL_TEST_CASES = ['symfony\bundle\frameworkbundle\test\kerneltestcase', 'symfony\bundle\frameworkbundle\test\webtestcase'];
    private const KERNEL_CALLS = ['bootkernel', 'getcontainer', 'createclient', 'createkernel', 'getclient'];

    public function __construct(
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $parent = $class->getParentClass();
        if (!Level::enables($this->level, self::LEVEL) || $class->isAbstract() || $parent === null || !in_array(strtolower($parent->getName()), self::KERNEL_TEST_CASES, true)) {
            return [];
        }

        $usesKernel = TestMethod::find($node->getOriginalNode()->stmts, static fn (Node $node): bool => in_array(strtolower(TestMethod::callName($node) ?? ''), self::KERNEL_CALLS, true)
            || ($node instanceof Expr\PropertyFetch && $node->name instanceof Node\Identifier && $node->name->toLowerString() === 'client')) !== [];
        if ($usesKernel) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test class %s extends %s but never boots the kernel: a test of plain objects is a TestCase.',
                $class->getDisplayName(),
                $parent->getDisplayName(false),
            ))
                ->identifier('testPractices.noKernelForPureFunction')
                ->tip('Extend PHPUnit\Framework\TestCase: the test runs faster and does not depend on the container configuration.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }
}
