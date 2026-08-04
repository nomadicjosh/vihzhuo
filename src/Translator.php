<?php

declare(strict_types=1);

namespace Vihzhuo;

class Translator
{
    /**
     * Return customized translations.
     *
     * @param array<string, mixed> $translations
     * @return array<string, mixed>
     */
    public function customize(array $translations): array
    {
        return $translations;
    }
}
