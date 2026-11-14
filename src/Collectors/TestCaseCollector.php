<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Collectors;

use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;

/**
 * @phpstan-type TestCase array{class: string, lineage: list<string>, line: int}
 * @implements Collector<InClassNode, TestCase>
 */
final readonly class TestCaseCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $class = $node->getClassReflection();
        if (!TestMethod::isTestCase($class) || $class->isAbstract()) {
            return null;
        }

        return [
            'class' => $class->getName(),
            'lineage' => array_map(static fn (ClassReflection $parent): string => $parent->getName(), [$class, ...$class->getParents()]),
            'line' => $node->getStartLine(),
        ];
    }
}
