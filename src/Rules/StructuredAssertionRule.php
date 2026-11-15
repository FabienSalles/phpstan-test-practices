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
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class StructuredAssertionRule implements Rule
{
    public const LEVEL = 2;
    private const TWO_ARGUMENTS = ['assertsame', 'assertequals', 'assertcount'];
    private const ONE_ARGUMENT = ['asserttrue', 'assertfalse', 'assertnull'];

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
        if (!Level::enables($this->level, self::LEVEL) || !TestMethod::isTest($method, $node->getClassReflection()) || count(TestMethod::outermostAssertions($method->stmts ?? [])) > 2) {
            return [];
        }

        $errors = [];
        $previous = null;
        foreach ($method->stmts ?? [] as $stmt) {
            $subject = self::subject($stmt);
            if ($subject !== null && $subject === $previous) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Test %s() checks $%s field by field: one assertion on the whole value shows every difference at once.',
                    $method->name->toString(),
                    $subject,
                ))
                    ->identifier('testPractices.structuredAssertion')
                    ->line($stmt->getStartLine())
                    ->tip('Compare the whole object with assertEquals(new X(...), $x), or the fields at once with assertSame([...], [...]).')
                    ->metadata(Severity::metadata($this->severity))
                    ->build();
                $subject = null;
            }

            $previous = $subject;
        }

        return $errors;
    }

    private static function subject(Stmt $stmt): ?string
    {
        if (!$stmt instanceof Stmt\Expression || !TestMethod::isPhpUnitAssertion($stmt->expr) || !$stmt->expr instanceof Expr\CallLike || $stmt->expr->isFirstClassCallable()) {
            return null;
        }

        $name = strtolower((string) TestMethod::callName($stmt->expr));
        $arguments = $stmt->expr->getArgs();
        $actual = match (true) {
            in_array($name, self::TWO_ARGUMENTS, true) => $arguments[1]->value ?? null,
            in_array($name, self::ONE_ARGUMENT, true) => $arguments[0]->value ?? null,
            default => null,
        };

        $isField = $actual instanceof Expr\PropertyFetch
            || $actual instanceof Expr\NullsafePropertyFetch
            || $actual instanceof Expr\ArrayDimFetch
            || (($actual instanceof Expr\MethodCall || $actual instanceof Expr\NullsafeMethodCall) && $actual->getArgs() === []);

        return $isField ? TestMethod::rootVariable($actual) : null;
    }
}
