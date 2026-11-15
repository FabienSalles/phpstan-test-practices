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
final readonly class PermissiveStubRule implements Rule
{
    public const LEVEL = 3;
    private const PERMISSIVE = ['any', 'cetera'];

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

        $stubs = [];
        $verified = [];
        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\MethodCall) as $call) {
            if (!$call instanceof Expr\MethodCall || !$call->var instanceof Expr\MethodCall) {
                continue;
            }

            $prophecy = $call->var;
            $key = self::key($prophecy);
            if ($key === null) {
                continue;
            }

            $name = strtolower((string) TestMethod::callName($call));
            if (str_starts_with($name, 'should')) {
                $verified[$key] = true;
            } elseif (str_starts_with($name, 'will')) {
                $stubs[$key][] = $prophecy;
            }
        }

        $errors = [];
        foreach (array_diff_key($stubs, $verified) as $key => $prophecies) {
            if (count($prophecies) > 1) {
                continue;
            }

            $prophecy = $prophecies[0];
            if ($prophecy->isFirstClassCallable() || array_filter($prophecy->getArgs(), self::isConstraining(...)) === []) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'The stub of %s() constrains its arguments: a stub provides data, it does not check how it is called.',
                $key,
            ))
                ->identifier('testPractices.permissiveStub')
                ->line($prophecy->getStartLine())
                ->tip('Use Argument::any() or Argument::cetera() in a stub; check the arguments with a spy verification when they matter.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        foreach (TestMethod::find($method->stmts ?? [], static fn (Node $node): bool => $node instanceof Expr\MethodCall && strtolower((string) TestMethod::callName($node)) === 'with') as $with) {
            $chain = [];
            for ($link = $with; $link instanceof Expr\MethodCall; $link = $link->var) {
                $chain[] = strtolower((string) TestMethod::callName($link));
            }

            if (!in_array('method', $chain, true) || in_array('expects', $chain, true)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message('The stub configured with with() constrains its arguments: a stub provides data, it does not check how it is called.')
                ->identifier('testPractices.permissiveStub')
                ->line($with->getStartLine())
                ->tip('Drop with() from the stub; check the arguments with a spy verification when they matter.')
                ->metadata(Severity::metadata($this->severity))
                ->build();
        }

        return $errors;
    }

    private static function key(Expr\MethodCall $prophecy): ?string
    {
        $name = TestMethod::callName($prophecy);
        $double = TestMethod::verifiedDouble($prophecy);
        if ($name === null || $double === null || $prophecy->var instanceof Expr\MethodCall || in_array(strtolower($name), ['method', 'reveal', 'expects'], true)) {
            return null;
        }

        return $double . '->' . $name;
    }

    private static function isConstraining(Node\Arg $argument): bool
    {
        $value = $argument->value;
        if ($value instanceof Expr\StaticCall && $value->class instanceof Node\Name && $value->class->toString() === 'Prophecy\Argument') {
            return !in_array(strtolower((string) TestMethod::callName($value)), self::PERMISSIVE, true);
        }

        return true;
    }
}
