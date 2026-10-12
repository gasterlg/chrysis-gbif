<?php

namespace App\Support;

/** Maps route names to the guide partial shown in the right sidebar (resources/views/help/<page>.blade.php). */
class Help
{
    public const PAGES = [
        'home' => 'home',
        'species.index' => 'species-index',
        'species.show' => 'species-show',
        'review.matches' => 'matches',
        'review.occurrences' => 'occurrences',
        'review.changes' => 'changes',
        'history' => 'history',
        'exports' => 'exports',
        'datasets' => 'datasets',
        'users.index' => 'users',
        'audit.index' => 'audit',
        'login' => 'login',
    ];

    public static function partialFor(?string $routeName): ?string
    {
        $page = self::PAGES[$routeName ?? ''] ?? null;

        return $page && view()->exists("help.{$page}") ? "help.{$page}" : null;
    }
}
