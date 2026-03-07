<?php

namespace Vihzhuo\Contracts;

interface PageTranslationContract
{
    /**
     * Return the page this translation belongs to.
     *
     * @return object|null
     */
    public function getPage(): ?object;

    /**
     * Return pages for navigation.
     *
     * @return array
     */
    public function getPages(): array;
}
