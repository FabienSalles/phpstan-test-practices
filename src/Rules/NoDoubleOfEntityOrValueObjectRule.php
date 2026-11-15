<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Rules;

use FabienSalles\PHPStanTestPractices\Level;
use FabienSalles\PHPStanTestPractices\Severity\Severity;
use FabienSalles\PHPStanTestPractices\TestMethod;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Expr\CallLike>
 */
final readonly class NoDoubleOfEntityOrValueObjectRule implements Rule
{
    public const LEVEL = 2;
    private const MAPPING_ATTRIBUTES = ['doctrine\orm\mapping\entity', 'doctrine\orm\mapping\embeddable'];
    private const MAPPING_ANNOTATION = '/@(ORM\\\\)?(Entity|Embeddable)\b/';

    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private array $patterns,
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
        $kind = $doubled === null ? null : $this->kind($doubled);
        if ($kind === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Test doubles %s, %s: a plain object without I/O is built, not doubled.',
                $doubled,
                $kind,
            ))
                ->identifier('testPractices.noDoubleOfEntityOrValueObject')
                ->tip('Create the real object, with a factory or an object mother when building it takes several lines.')
                ->metadata(Severity::metadata($this->severity))
                ->build(),
        ];
    }

    private function kind(string $doubled): ?string
    {
        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $doubled, FNM_NOESCAPE)) {
                return sprintf('which matches %s', $pattern);
            }
        }

        if (!$this->reflectionProvider->hasClass($doubled)) {
            return null;
        }

        $reflection = $this->reflectionProvider->getClass($doubled);

        return match (true) {
            self::isMapped($reflection) => 'a Doctrine entity or embeddable',
            $reflection->isEnum() => 'an enum',
            $reflection->isReadOnly() => 'a readonly class',
            default => null,
        };
    }

    private static function isMapped(ClassReflection $reflection): bool
    {
        foreach ($reflection->getNativeReflection()->getAttributes() as $attribute) {
            if (in_array(strtolower($attribute->getName()), self::MAPPING_ATTRIBUTES, true)) {
                return true;
            }
        }

        return preg_match(self::MAPPING_ANNOTATION, (string) $reflection->getNativeReflection()->getDocComment()) === 1;
    }
}
