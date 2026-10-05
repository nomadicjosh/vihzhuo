<?php

declare(strict_types=1);

namespace Vihzhuo\Core;

use Closure;
use Vihzhuo\Contracts\ThemeContract;

/** @internal Scoped theme for helpers called from PHP templates and block code. */
final class ThemeContext
{
    private static ?ThemeContract $theme = null;

    public static function current(): ?ThemeContract
    {
        return self::$theme;
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function run(ThemeContract $theme, Closure $callback): mixed
    {
        $previous = self::$theme;
        self::$theme = $theme;
        try {
            return $callback();
        } finally {
            self::$theme = $previous;
        }
    }

    /** @param array<string, mixed> $data */
    public static function render(ThemeContract $theme, string $view, array $data = []): string
    {
        return self::run($theme, fn (): string => View::render($view, $data));
    }
}
