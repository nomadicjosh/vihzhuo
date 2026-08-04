<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface PageTranslationContract
{
    /**
     * Return the page this translation belongs to.
     *
     * @return PageContract|null
     */
    public function getPage(): ?PageContract;

    public function getId(): string;

    public function getPageId(): string;

    public function getLocale(): string;

    public function getRoute(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;

    /**
     * Return pages for navigation.
     *
     * @return list<array<string, mixed>>
     */
    public function getPages(): array;
}
