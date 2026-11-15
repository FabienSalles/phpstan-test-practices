<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class FrozenClockRule implements Rule
{
    public const LEVEL = 1;
    private const DATE_CLASSES = ['datetime', 'datetimeimmutable'];
    private const NOW_FUNCTIONS = ['time', 'microtime', 'hrtime', 'now'];
    private const NOW_STATIC_CALLS = ['carbon\carbon::now', 'carbon\carbonimmutable::now', 'symfony\component\clock\clock::get'];
    private const ABSOLUTE = '/^\s*(@\d+|\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}\/\d{2,4}|\d{4}\d{2}\d{2}T)/';

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTestCase($node->getClassReflection())) {
            return [];
        }

        $errors = [];
        foreach (TestMethod::find($method->stmts ?? [], self::readsTheSystemClock(...)) as $read) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s() reads the system clock: the test depends on when it runs.',
                $method->name->toString(),
            ))
                ->identifier('testPractices.frozenClock')
                ->line($read->getStartLine())
                ->tip('Use an absolute date (new \DateTimeImmutable(\'2026-11-12 09:00\')) and inject a frozen clock (Symfony MockClock, a FrozenClock) into the code under test.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function readsTheSystemClock(Node $node): bool
    {
        if ($node instanceof Expr\New_ && $node->class instanceof Node\Name && in_array(strtolower(ltrim($node->class->toString(), '\\')), self::DATE_CLASSES, true)) {
            return self::isRelative($node->getArgs()[0]->value ?? null);
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $name = strtolower(ltrim($node->name->toString(), '\\'));
            $arguments = $node->isFirstClassCallable() ? [] : $node->getArgs();

            return in_array($name, self::NOW_FUNCTIONS, true)
                || ($name === 'date' && count($arguments) < 2)
                || ($name === 'mktime' && count($arguments) < 6)
                || ($name === 'strtotime' && count($arguments) < 2 && self::isRelative($arguments[0]->value ?? null));
        }

        return $node instanceof Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier
            && in_array(strtolower(ltrim($node->class->toString(), '\\') . '::' . $node->name->toString()), self::NOW_STATIC_CALLS, true);
    }

    private static function isRelative(?Expr $argument): bool
    {
        if ($argument === null) {
            return true;
        }

        return $argument instanceof String_ && preg_match(self::ABSOLUTE, $argument->value) !== 1;
    }
}
