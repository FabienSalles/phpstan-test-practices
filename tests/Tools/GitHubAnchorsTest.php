<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Tools;

use FabienSalles\PHPStanTestPractices\Tools\SkillLinks\GitHubAnchors;
use PHPUnit\Framework\TestCase;

final class GitHubAnchorsTest extends TestCase
{
    public function testComputesTheAnchorsGitHubRendersForEachHeading(): void
    {
        $markdown = <<<'MD'
            # Testing — Cross-Language Principles

            ## 5. AAA / GWT Pattern

            ## 7. Parameterized Tests (data provider / it.each)

            ### Use the `built-in` assertions before the crawler

            ```php
            # not a heading
            ```

            ## Quick Reference

            ## Quick Reference

            ## PHP-specific: Exception Test Naming
            MD;

        $anchors = GitHubAnchors::fromMarkdown($markdown);

        self::assertSame([
            'testing--cross-language-principles',
            '5-aaa--gwt-pattern',
            '7-parameterized-tests-data-provider--iteach',
            'use-the-built-in-assertions-before-the-crawler',
            'quick-reference',
            'quick-reference-1',
            'php-specific-exception-test-naming',
        ], $anchors);
    }
}
