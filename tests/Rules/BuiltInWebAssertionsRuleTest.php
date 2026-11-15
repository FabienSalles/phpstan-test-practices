<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Rules;

use FabienSalles\PHPStanTestPractices\Rules\BuiltInWebAssertionsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<BuiltInWebAssertionsRule>
 */
final class BuiltInWebAssertionsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BuiltInWebAssertionsRule('warning', 'max');
    }

    public function testFlagsHandRolledResponseReads(): void
    {
        $this->analyse([__DIR__ . '/../Fixtures/Rules/BuiltInWebAssertions.php'], [
            ['Use self::assertResponseStatusCodeSame() instead of reading the response by hand: it reads better and fails with the response in the message.', 57, 'Fixtures\\BuiltInWebAssertions\\BuiltInWebAssertionsTest provides the assertions of Symfony\'s BrowserKitAssertionsTrait and DomCrawlerAssertionsTrait.'],
            ['Use self::assertSelectorTextSame() instead of reading the response by hand: it reads better and fails with the response in the message.', 58, 'Fixtures\\BuiltInWebAssertions\\BuiltInWebAssertionsTest provides the assertions of Symfony\'s BrowserKitAssertionsTrait and DomCrawlerAssertionsTrait.'],
            ['Use self::assertSelectorTextContains() instead of reading the response by hand: it reads better and fails with the response in the message.', 59, 'Fixtures\\BuiltInWebAssertions\\BuiltInWebAssertionsTest provides the assertions of Symfony\'s BrowserKitAssertionsTrait and DomCrawlerAssertionsTrait.'],
            ['Use self::assertSelectorCount() instead of reading the response by hand: it reads better and fails with the response in the message.', 60, 'Fixtures\\BuiltInWebAssertions\\BuiltInWebAssertionsTest provides the assertions of Symfony\'s BrowserKitAssertionsTrait and DomCrawlerAssertionsTrait.'],
        ]);
    }

    public function testReplacesAStatusCodeReadByTheBuiltInAssertion(): void
    {
        $this->fix(__DIR__ . '/../Fixtures/Fix/BuiltInWebAssertions.php', __DIR__ . '/../Fixtures/Fix/BuiltInWebAssertions.php.fixed');
    }
}
