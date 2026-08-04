<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface SettingContract
{
    /**
     * Return the value(s) of the given setting.
     *
     * @param string $key
     * @return string|list<string>|null
     */
    public static function get(string $key): string|array|null;

    /**
     * Return whether the given setting exists and has the given value.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public static function has(string $key, string $value): bool;
}
