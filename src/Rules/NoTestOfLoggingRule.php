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
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class NoTestOfLoggingRule implements Rule
{
    public const LEVEL = 3;
    private const LOGGER = 'Psr\Log\LoggerInterface';

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private bool $enabled,
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
        $class = $node->getClassReflection();
        if (!$this->enabled || !Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $class)) {
            return [];
        }

        $loggers = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) as $assign) {
            $factory = $assign instanceof Expr\Assign ? $assign->expr : null;
            while ($factory instanceof Expr\MethodCall && !TestMethod::isDoubleFactory($factory)) {
                $factory = $factory->var;
            }

            $doubled = $factory !== null && TestMethod::isDoubleFactory($factory) ? TestMethod::doubledClass($factory, $class->getName()) : null;
            if ($doubled !== null && $this->isLogger($doubled) && $assign instanceof Expr\Assign && $assign->var instanceof Expr\Variable && is_string($assign->var->name)) {
                $loggers['$' . $assign->var->name] = true;
            }
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => TestMethod::isVerification($node) || self::readsLogRecords($node)) as $check) {
            $double = $check instanceof Expr\MethodCall ? TestMethod::verifiedDouble($check) : null;
            if (!self::readsLogRecords($check) && ($double === null || !isset($loggers[$double]))) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Test %s() checks what is logged: logging is rarely the behaviour under test, and the test breaks when a message is reworded.',
                $method->name->toString(),
            ))
                ->identifier('testPractices.noTestOfLogging')
                ->line($check->getStartLine())
                ->tip('Keep this check only when the log is a business rule, an audited trace or a monitored metric, and ignore the line with that reason.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private function isLogger(string $doubled): bool
    {
        return strcasecmp($doubled, self::LOGGER) === 0
            || ($this->reflectionProvider->hasClass($doubled) && $this->reflectionProvider->getClass($doubled)->is(self::LOGGER));
    }

    private static function readsLogRecords(Node $node): bool
    {
        return $node instanceof Expr\MethodCall && preg_match('/^(has(Records?|Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)\w*|getRecords)$/i', (string) TestMethod::callName($node)) === 1;
    }
}
