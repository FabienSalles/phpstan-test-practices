<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Arg;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Expr\CallLike>
 */
final readonly class BuiltInWebAssertionsRule implements Rule
{
    public const LEVEL = 3;
    private const SCOPED = ['eq', 'first', 'last', 'children', 'siblings'];

    public function __construct(
        private string $severity,
        private int|string $level,
    ) {
    }

    public function getNodeType(): string
    {
        return Expr\CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $scope->getClassReflection();
        if (!Level::enables($this->level, self::LEVEL) || $class === null || !TestMethod::isTestCase($class) || !$class->hasNativeMethod('assertResponseStatusCodeSame') || !TestMethod::isPhpUnitAssertion($node) || $node->isFirstClassCallable()) {
            return [];
        }

        $name = strtolower((string) TestMethod::callName($node));
        $arguments = $node->getArgs();
        $expected = $arguments[0]->value ?? null;
        $actual = $arguments[1]->value ?? null;
        if ($expected === null || $actual === null) {
            return [];
        }

        if (in_array($name, ['assertsame', 'assertequals'], true) && self::readsStatusCode($actual)) {
            return [$this->error($class, 'assertResponseStatusCodeSame', $node, new Expr\StaticCall(new Name('self'), new Identifier('assertResponseStatusCodeSame'), [new Arg($expected)]))];
        }

        $replacement = match (true) {
            in_array($name, ['assertsame', 'assertequals'], true) && self::readsSelectorText($actual) => 'assertSelectorTextSame',
            $name === 'assertstringcontainsstring' && self::readsSelectorText($actual) => 'assertSelectorTextContains',
            $name === 'assertcount' && self::isFilter($actual) => 'assertSelectorCount',
            default => null,
        };

        return $replacement === null ? [] : [$this->error($class, $replacement, $node, null)];
    }

    private function error(ClassReflection $class, string $replacement, Expr\CallLike $node, ?Expr $fix): IdentifierRuleError
    {
        $error = RuleErrorBuilder::message(sprintf(
            'Use self::%s() instead of reading the response by hand: it reads better and fails with the response in the message.',
            $replacement,
        ))
            ->identifier('testPractices.builtInWebAssertions')
            ->line($node->getStartLine())
            ->tip(sprintf('%s provides the assertions of Symfony\'s BrowserKitAssertionsTrait and DomCrawlerAssertionsTrait.', $class->getDisplayName()))
            ->metadata(Severity::metadata($this->severity));
        if ($fix !== null) {
            $error->fixNode($node, static fn (): Expr => $fix);
        }

        return $error->build();
    }

    private static function readsStatusCode(Expr $actual): bool
    {
        return $actual instanceof Expr\MethodCall && strtolower((string) TestMethod::callName($actual)) === 'getstatuscode'
            && $actual->var instanceof Expr\MethodCall && strtolower((string) TestMethod::callName($actual->var)) === 'getresponse';
    }

    private static function readsSelectorText(Expr $actual): bool
    {
        if ($actual instanceof Expr\FuncCall && $actual->name instanceof Name && $actual->name->toLowerString() === 'trim' && !$actual->isFirstClassCallable()) {
            $actual = $actual->getArgs()[0]->value ?? null;
        }

        return $actual instanceof Expr\MethodCall && strtolower((string) TestMethod::callName($actual)) === 'text' && self::isFilter($actual->var);
    }

    private static function isFilter(Expr $expr): bool
    {
        if (!$expr instanceof Expr\MethodCall || strtolower((string) TestMethod::callName($expr)) !== 'filter') {
            return false;
        }

        for ($link = $expr->var; $link instanceof Expr\MethodCall; $link = $link->var) {
            if (in_array(strtolower((string) TestMethod::callName($link)), self::SCOPED, true)) {
                return false;
            }
        }

        return true;
    }
}
