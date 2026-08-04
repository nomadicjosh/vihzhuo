<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

use Vihzhuo\ThemeBlock;
use Vihzhuo\ThemeLayout;

interface ThemeContract
{
    /**
     * Return all blocks of this theme.
     *
     * @return array<string, ThemeBlock> Array of ThemeBlock instances.
     */
    public function getThemeBlocks(): array;

    /**
     * Return all layouts of this theme.
     *
     * @return array<string, ThemeLayout> Array of ThemeLayout instances.
     */
    public function getThemeLayouts(): array;

    /**
     * Return the folder of this theme.
     *
     * @return string
     */
    public function getFolder(): string;
}
