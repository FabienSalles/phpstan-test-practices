<?php

declare(strict_types=1);

namespace FabienSalles\PHPStanTestPractices\Tools\SkillLinks;

final class GitHubAnchors
{
    /**
     * @return list<string>
     */
    public static function fromMarkdown(string $markdown): array
    {
        $anchors = [];
        $seen = [];
        $inFence = false;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = !$inFence;

                continue;
            }

            if ($inFence || preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $heading) !== 1) {
                continue;
            }

            $slug = self::slug($heading[1]);
            $count = $seen[$slug] ?? 0;
            $seen[$slug] = $count + 1;
            $anchors[] = $count === 0 ? $slug : $slug . '-' . $count;
        }

        return $anchors;
    }

    public static function slug(string $heading): string
    {
        $text = mb_strtolower(trim(str_replace('`', '', $heading)));

        return str_replace(' ', '-', (string) preg_replace('/[^\p{L}\p{M}\p{N}\p{Pc} -]/u', '', $text));
    }
}
