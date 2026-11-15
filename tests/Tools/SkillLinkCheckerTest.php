<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tests\Tools;

use FabienSalles\PHPStanTestPractices\Tools\SkillLinks\SkillLinkChecker;
use PHPUnit\Framework\TestCase;

final class SkillLinkCheckerTest extends TestCase
{
    private const BLOB = 'https://github.com/FabienSalles/claude-marketplace/blob/main/';
    private const RAW = 'https://raw.githubusercontent.com/FabienSalles/claude-marketplace/main/';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/skill-links-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/docs/rules', recursive: true);
    }

    protected function tearDown(): void
    {
        exec(sprintf('rm -rf %s', escapeshellarg($this->root)));
    }

    public function testNamesEachBrokenLinkAndEachUnmappedRule(): void
    {
        file_put_contents($this->root . '/extension.neon', "severity: %testPractices.severity.mapped%\nseverity: %testPractices.severity.unmapped%\n");
        file_put_contents($this->root . '/docs/skills.md', sprintf(
            "| Rule | Skill sections |\n|---|---|\n| [`mapped`](rules/mapped.md) | [ok](%1\$sa/SKILL.md#5-aaa--gwt-pattern)<br>[renamed](%1\$sa/SKILL.md#old-heading) |\n",
            self::BLOB,
        ));
        file_put_contents($this->root . '/docs/rules/mapped.md', sprintf("Related skill: [gone](%sb/SKILL.md#anything).\n", self::BLOB));
        $checker = new SkillLinkChecker(static fn (string $url): ?string => $url === self::RAW . 'a/SKILL.md' ? "## 5. AAA / GWT Pattern\n" : null);

        $problems = $checker->check($this->root);

        self::assertSame([
            'Rule unmapped is missing from docs/skills.md.',
            'docs/skills.md links to ' . self::BLOB . 'a/SKILL.md#old-heading, whose heading no longer exists.',
            'docs/rules/mapped.md links to ' . self::BLOB . 'b/SKILL.md#anything, which no longer exists.',
        ], $problems);
    }

    public function testFindsNothingWhenEveryRuleIsMappedAndEveryLinkResolves(): void
    {
        file_put_contents($this->root . '/extension.neon', "severity: %testPractices.severity.mapped%\n");
        file_put_contents($this->root . '/docs/skills.md', sprintf("| [`mapped`](rules/mapped.md) | [ok](%sa/SKILL.md#5-aaa--gwt-pattern) |\n", self::BLOB));
        $checker = new SkillLinkChecker(static fn (string $url): ?string => "## 5. AAA / GWT Pattern\n");

        $problems = $checker->check($this->root);

        self::assertSame([], $problems);
    }
}
