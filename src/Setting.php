<?php

declare(strict_types=1);

namespace Vihzhuo;

use ReflectionException;
use Vihzhuo\Contracts\SettingContract;
use Vihzhuo\Repositories\SettingRepository;

use function Qubus\Support\Helpers\is_null__;

class Setting implements SettingContract
{
    /** @var array<string, string|list<string>>|null */
    protected static ?array $settings = null;

    /**
     * Load all settings from database.
     *
     * @throws ReflectionException
     */
    protected static function loadSettings(): void
    {
        self::$settings = [];
        $settingsRepository = new SettingRepository();
        foreach ($settingsRepository->getAll() as $setting) {
            self::$settings[$setting->setting] = $setting->is_array ? explode(',', $setting->value) : $setting->value;
        }
    }

    /** @return string|list<string>|null */
    public static function get(string $key): string|array|null
    {
        if (is_null__(self::$settings)) {
            self::loadSettings();
        }

        if (isset(self::$settings[$key])) {
            return self::$settings[$key];
        }
        return null;
    }

    /**
     * Return whether the given setting exists and has the given value.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public static function has(string $key, string $value): bool
    {
        if (is_null__(self::$settings)) {
            self::loadSettings();
        }

        return isset(self::$settings[$key]) && (
                (is_array(self::$settings[$key]) && in_array($value, self::$settings[$key])) ||
                (! is_array(self::$settings[$key]) && self::$settings[$key] === $value)
        );
    }
}
