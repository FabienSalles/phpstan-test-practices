<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Expr\CallLike>
 */
final readonly class DontMockWhatYouDontOwnRule implements Rule
{
    public const LEVEL = 2;

    /**
     * @param list<string> $classes
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $classes,
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
        if (!Level::enables($this->level, self::LEVEL) || $class === null || !TestMethod::isTestCase($class) || !TestMethod::isDoubleFactory($node)) {
            return [];
        }

        $doubled = TestMethod::doubledClass($node, $class->getName());
        $owned = $doubled === null ? null : $this->thirdPartyType($doubled);
        if ($owned === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test doubles %s, a type the project does not own (%s): the test copies how the library is called and breaks when that changes.',
                $doubled,
                $owned,
            ))
                ->identifier('testPractices.dontMockWhatYouDontOwn')
                ->tip('Double your own port (a repository, a client interface) and cover its adapter with an integration test, or use the real implementation.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }

    private function thirdPartyType(string $doubled): ?string
    {
        foreach ($this->classes as $denied) {
            if (strcasecmp($doubled, $denied) === 0) {
                return $denied;
            }

            if ($this->reflectionProvider->hasClass($doubled) && $this->reflectionProvider->hasClass($denied) && $this->reflectionProvider->getClass($doubled)->is($denied)) {
                return $denied;
            }
        }

        return null;
    }
}
