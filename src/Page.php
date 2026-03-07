<?php

namespace Vihzhuo;

use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Repositories\PageTranslationRepository;

class Page implements PageContract
{
    /**
     * @var array|null $attributes
     */
    protected ?array $attributes;

    /**
     * @var array|null $translations
     */
    protected ?array $translations = null;

    /**
     * Set the data stored for this page.
     *
     * @param array|null $data
     * @param bool $fullOverwrite       whether to fully overwrite or extend existing data
     */
    public function setData(?array $data = null, bool $fullOverwrite = true): void
    {
        // if page builder data is set, try to decode json
        if (isset($data['data']) && is_string($data['data'])) {
            $data['data'] = json_decode($data['data'], true);
        }
        if ($fullOverwrite) {
            $this->attributes = $data;
        }  elseif (is_array($data)) {
            $this->attributes = is_null($this->attributes) ? [] : $this->attributes;
            foreach ($data as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Set the translation data of this page.
     *
     * @param array|null $translationData
     */
    public function setTranslations(?array $translationData = null): void
    {
        $this->translations = $translationData;
    }

    /**
     * Return all data stored for this page (page builder data and other data set via setData).
     *
     * @return array|null
     */
    public function getData(): ?array
    {
        return $this->attributes;
    }

    /**
     * Return the page builder data stored for this page.
     *
     * @return array|null
     */
    public function getBuilderData(): ?array
    {
        return $this->attributes['data'] ?? [];
    }

    /**
     * Return the id of this page.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->get('id');
    }

    /**
     * Return the name of this page.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->get('name');
    }

    /**
     * Return the layout (file name) of this page.
     *
     * @return string
     */
    public function getLayout(): string
    {
        return $this->get('layout');
    }

    /**
     * Return the translated settings of this page.
     *
     * @return array
     */
    public function getTranslations(): array
    {
        if (is_null($this->translations)) {
            $records = (new PageTranslationRepository)->findWhere(phpb_config('page.translation.foreign_key'), $this->getId());
            $translations = [];
            foreach ($records as $record) {
                if (in_array($record->locale, array_keys(phpb_active_languages()))) {
                    $translations[$record->locale] = (array) $record;
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
     * @return mixed|string|null
     */
    public function getTranslation(string $setting, ?string $locale = null): mixed
    {
        $translations = $this->getTranslations();
        if (empty($translations)) {
            return null;
        }
        $locale = $locale ?? phpb_config('general.language');
        return $translations[$locale][$setting] ??
            $translations['en'][$setting] ??
            $translations[array_keys($translations)[0]][$setting] ??
            null;
    }

    /**
     * Return the route of this page.
     *
     * @param string|null $locale
     * @return mixed|string|null
     */
    public function getRoute(?string $locale = null): mixed
    {
        $routeTranslation = $this->getTranslation('route', $locale);
        foreach (phpb_route_parameters() as $routeParameter => $value) {
            $routeTranslation = str_replace('{' . $routeParameter . '}', $value, $routeTranslation);
        }
        return $routeTranslation;
    }

    /**
     * Get the value of the given property of this Page.
     *
     * @param $property
     * @return mixed|null
     */
    public function get($property): mixed
    {
        if (property_exists($this, $property)) {
            return $this->{$property};
        }

        if ($this->attributes && is_array($this->attributes)) {
            return $this->attributes[$property] ?? null;
        }

        return null;
    }

    /**
     * Invalidate all cached variants of this page.
     */
    public function invalidateCache(): void
    {
        $cache = phpb_instance('cache');

        foreach ($this->getTranslations() as $locale => $translationData) {
            $languageRoute = $this->getRoute($locale);
            $cache->invalidate($languageRoute);
        }
    }
}
