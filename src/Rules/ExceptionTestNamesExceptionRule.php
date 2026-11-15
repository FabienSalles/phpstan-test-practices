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
final readonly class ExceptionTestNamesExceptionRule implements Rule
{
    public const LEVEL = 2;

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
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => strtolower(TestMethod::callName($node) ?? '') === 'expectexception') as $call) {
            $exception = $call instanceof Expr\CallLike && !$call->isFirstClassCallable() ? ($call->getArgs()[0]->value ?? null) : null;
            if (!$exception instanceof Expr\ClassConstFetch || !$exception->class instanceof Node\Name) {
                continue;
            }

            $short = $exception->class->getLast();
            if (self::isNamed($method->name->toLowerString(), $short)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() expects %s but its name does not say so: the name should tell which error the test is about.',
                $method->name->toString(),
                $short,
            ))
                ->identifier('testPractices.exceptionTestNamesException')
                ->line($call->getStartLine())
                ->tip(sprintf('Name the test after the exception, for instance itThrows%sWhen...().', preg_replace('/Exception$/', '', $short)))
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function isNamed(string $test, string $exception): bool
    {
        $withoutSuffix = (string) preg_replace('/Exception$/i', '', $exception);
        foreach ([$exception, $withoutSuffix, (string) preg_replace('/^Cannot/i', '', $withoutSuffix)] as $candidate) {
            if ($candidate !== '' && str_contains($test, strtolower($candidate))) {
                return true;
            }
        }

        return false;
    }
}
