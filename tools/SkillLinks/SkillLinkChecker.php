<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tools\SkillLinks;

use Closure;

final readonly class SkillLinkChecker
{
    private const LINK = '~https://github\.com/FabienSalles/claude-marketplace/blob/main/([\w./-]+\.md)#([\w-]+)~u';
    private const RAW = 'https://raw.githubusercontent.com/FabienSalles/claude-marketplace/main/%s';

    /**
     * @param Closure(string): ?string $fetch returns the body at the URL, or null when it does not exist
     */
    public function __construct(
        private Closure $fetch,
    ) {
    }

    /**
     * @return list<string>
     */
    public function check(string $root): array
    {
        $skills = (string) file_get_contents($root . '/docs/skills.md');
        preg_match_all('/^\| \[`(\w+)`\]/m', $skills, $mapped);
        $problems = [];
        foreach (self::rules($root) as $rule) {
            if (!in_array($rule, $mapped[1], true)) {
                $problems[] = sprintf('Rule %s is missing from docs/skills.md.', $rule);
            }
        }

        $documents = ['docs/skills.md' => $skills];
        foreach (glob($root . '/docs/rules/*.md') ?: [] as $page) {
            $documents['docs/rules/' . basename($page)] = (string) file_get_contents($page);
        }

        $anchors = [];
        foreach ($documents as $document => $content) {
            preg_match_all(self::LINK, $content, $links, PREG_SET_ORDER);
            foreach ($links as [$url, $path, $anchor]) {
                if (!array_key_exists($path, $anchors)) {
                    $markdown = ($this->fetch)(sprintf(self::RAW, $path));
                    $anchors[$path] = $markdown === null ? null : GitHubAnchors::fromMarkdown($markdown);
                }

                if ($anchors[$path] === null) {
                    $problems[] = sprintf('%s links to %s, which no longer exists.', $document, $url);
                } elseif (!in_array($anchor, $anchors[$path], true)) {
                    $problems[] = sprintf('%s links to %s, whose heading no longer exists.', $document, $url);
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * @return list<string>
     */
    private static function rules(string $root): array
    {
        preg_match_all('/%testPractices\.severity\.(\w+)%/', (string) file_get_contents($root . '/extension.neon'), $rules);

        return array_values(array_unique($rules[1]));
    }
}
