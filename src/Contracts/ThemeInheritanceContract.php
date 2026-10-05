<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

/** Optional capability; existing ThemeContract implementations need no changes. */
interface ThemeInheritanceContract extends ThemeContract
{
    /** @return array<string, string> Theme slug => folder, child first. */
    public function getThemeFolders(): array;
}
