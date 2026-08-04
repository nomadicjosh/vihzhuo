<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface SettingRepositoryContract
{
    /**
     * Replace all website settings by the given data.
     *
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateSettings(array $data): bool;
}
