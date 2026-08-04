<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface PageContract
{
    /**
     * Set the data stored for this page.
     *
     * @param array<string, mixed>|null $data
     * @param bool $fullOverwrite Whether to fully overwrite or extend existing data
     */
    public function setData(?array $data = null, bool $fullOverwrite = true): void;

    /**
     * Set the translation data of this page.
     *
     * @param array<string, array<string, mixed>>|null $translationData
     */
    public function setTranslations(?array $translationData = null): void;

    /**
     * Return all data stored for this page (page builder data and other data set via setData).
     *
     * @return array<string, mixed>|null
     */
    public function getData(): ?array;

    /**
     * Return the page builder data stored for this page.
     *
     * @return array<string, mixed>
     */
    public function getBuilderData(): array;

    /**
     * Return the id of this page.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Return the layout (file name) of this page.
     *
     * @return string
     */
    public function getLayout(): string;

    /**
     * Return the name of this page.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Return the given language dependant setting for this page, in the current or in the given language.
     *
     * @param string $setting
     * @param string|null $locale
     * @return mixed
     */
    public function getTranslation(string $setting, ?string $locale = null): mixed;

    /**
     * Return the translated settings of this page.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTranslations(): array;

    /**
     * Return the route of this page, in the current or in the given language.
     *
     * @param string|null $locale
     * @return string
     */
    public function getRoute(?string $locale = null): string;

    /**
     * Get the value of the given property of this Page.
     *
     * @param string $property
     * @return mixed|null
     */
    public function get(string $property): mixed;

    /**
     * Invalidate all cached variants of this page.
     */
    public function invalidateCache(): void;
}
