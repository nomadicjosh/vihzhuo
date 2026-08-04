<?php

declare(strict_types=1);

namespace Vihzhuo;

use ReflectionException;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\CacheContract;
use Vihzhuo\Repositories\PageTranslationRepository;

use function Qubus\Support\Helpers\is_null__;

use const ARRAY_FILTER_USE_KEY;

class Page implements PageContract
{
    /** @var array<string, mixed>|null */
    protected ?array $attributes = null;

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $translations = null;

    /**
     * Set the data stored for this page.
     *
     * @param array<string, mixed>|null $data
     * @param bool $fullOverwrite Whether to fully overwrite or extend existing data
     */
    public function setData(?array $data = null, bool $fullOverwrite = true): void
    {
        // if page builder data is set, try to decode json
        if (isset($data['data']) && is_string($data['data'])) {
            $decoded = json_decode($data['data'], true);
            $data['data'] = is_array($decoded) ? $decoded : [];
        }

        if ($fullOverwrite) {
            $this->attributes = $data === null ? null : array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
        } elseif (is_array($data)) {
            $this->attributes = is_null__($this->attributes) ? [] : $this->attributes;
            foreach ($data as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Set the translation data of this page.
     *
     * @param array<string, array<string, mixed>>|null $translationData
     */
    public function setTranslations(?array $translationData = null): void
    {
        $this->translations = $translationData;
    }

    /**
     * Return all data stored for this page (page builder data and other data set via setData).
     *
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->attributes;
    }

    /**
     * Return the page builder data stored for this page.
     *
     * @return array<string, mixed>
     */
    public function getBuilderData(): array
    {
        $data = $this->attributes['data'] ?? [];
        return is_array($data) ? array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * Return the id of this page.
     *
     * @return string
     */
    public function getId(): string
    {
        $id = $this->get('id');
        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * Return the name of this page.
     *
     * @return string
     */
    public function getName(): string
    {
        $name = $this->get('name');
        return is_string($name) ? $name : '';
    }

    /**
     * Return the layout (file name) of this page.
     *
     * @return string
     */
    public function getLayout(): string
    {
        $layout = $this->get('layout');
        return is_string($layout) ? $layout : '';
    }

    /**
     * Return the translated settings of this page.
     *
     * @return array<string, array<string, mixed>>
     * @throws ReflectionException
     */
    public function getTranslations(): array
    {
        if ($this->translations === null) {
            $foreignKey = phpb_config('page.translation.foreign_key');
            $records = new PageTranslationRepository()
                ->findWhere(is_string($foreignKey) ? $foreignKey : 'page_id', $this->getId());
            $translations = [];
            foreach ($records as $record) {
                if (isset(phpb_active_languages()[$record->getLocale()])) {
                    $translations[$record->getLocale()] = $record->toArray();
                }
            }
            $this->translations = $translations;
        }
        return $this->translations;
    }

    /**
     * Return the given language dependant setting for this page, in the current or in the given language.
     *
     * @param string $setting
     * @param string|null $locale
     * @return mixed
     * @throws ReflectionException
     */
    public function getTranslation(string $setting, ?string $locale = null): mixed
    {
        $translations = $this->getTranslations();
        if (empty($translations)) {
            return null;
        }
        $configuredLocale = phpb_config('general.language');
        $locale ??= is_string($configuredLocale) ? $configuredLocale : 'en';

        return $translations[$locale][$setting] ??
        $translations['en'][$setting] ??
        $translations[array_keys($translations)[0]][$setting] ??
        null;
    }

    /**
     * Return the route of this page.
     *
     * @param string|null $locale
     * @return string
     * @throws ReflectionException
     */
    public function getRoute(?string $locale = null): string
    {
        $routeTranslation = $this->getTranslation('route', $locale);
        foreach (phpb_route_parameters() as $routeParameter => $value) {
            $routeTranslation = str_replace(
                '{' . $routeParameter . '}',
                $value,
                is_string($routeTranslation) ? $routeTranslation : ''
            );
        }
        return is_string($routeTranslation) ? $routeTranslation : '';
    }

    /**
     * Get the value of the given property of this Page.
     *
     * @param string $property
     * @return mixed|null
     */
    public function get(string $property): mixed
    {
        if ($this->attributes !== null) {
            return $this->attributes[$property] ?? null;
        }

        return null;
    }

    /**
     * Invalidate all cached variants of this page.
     *
     * @throws ReflectionException
     */
    public function invalidateCache(): void
    {
        $cache = phpb_instance('cache');
        if (!$cache instanceof CacheContract) {
            return;
        }

        foreach ($this->getTranslations() as $locale => $translationData) {
            $languageRoute = $this->getRoute($locale);
            $cache->invalidate($languageRoute);
        }
    }
}
