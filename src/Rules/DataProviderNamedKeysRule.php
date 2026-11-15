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
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassNode>
 */
final readonly class DataProviderNamedKeysRule implements Rule
{
    public const LEVEL = 2;
    private const DATA_PROVIDER = 'PHPUnit\Framework\Attributes\DataProvider';

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTestCase($node->getClassReflection())) {
            return [];
        }

        $methods = $node->getOriginalNode()->getMethods();
        $providers = [];
        foreach ($methods as $method) {
            foreach (self::providersOf($method) as $provider) {
                $providers[strtolower($provider)] = true;
            }
        }

        $errors = [];
        foreach ($methods as $method) {
            if (!isset($providers[$method->name->toLowerString()])) {
                continue;
            }

            foreach (self::unnamedDataSets($method) as $dataSet) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Data provider %s() yields a data set without a name: PHPUnit reports it as "data set #0".',
                    $method->name->toString(),
                ))
                    ->identifier('testPractices.dataProviderNamedKeys')
                    ->line($dataSet->getStartLine())
                    ->tip('Give each data set a string key that names the case: yield \'0%, not eligible\' => [...].')
                    ->metadata(Severity::metadata($this->severity))
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function providersOf(Stmt\ClassMethod $method): array
    {
        $providers = [];
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $name = $attribute->args[0]->value ?? null;
                if ($attribute->name->toString() === self::DATA_PROVIDER && $name instanceof Node\Scalar\String_) {
                    $providers[] = $name->value;
                }
            }
        }

        if (preg_match_all('/@dataProvider\s+(\w+)/', $method->getDocComment()?->getText() ?? '', $matches) > 0) {
            $providers = [...$providers, ...$matches[1]];
        }

        return $providers;
    }

    /**
     * @return list<Node>
     */
    private static function unnamedDataSets(Stmt\ClassMethod $method): array
    {
        $unnamed = [];
        foreach (TestMethod::findOutsideClosures($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Yield_ || $node instanceof Stmt\Return_) as $node) {
            if ($node instanceof Expr\Yield_ && !$node->key instanceof Node\Scalar\String_) {
                $unnamed[] = $node;
            }

            if (!$node instanceof Stmt\Return_ || !$node->expr instanceof Expr\Array_) {
                continue;
            }

            foreach ($node->expr->items as $item) {
                if (!$item->key instanceof Node\Scalar\String_) {
                    $unnamed[] = $item;
                }
            }
        }

        return $unnamed;
    }
}
