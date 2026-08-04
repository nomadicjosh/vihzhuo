<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface DataRecordContract
{
    /** @param array<string, mixed> $data */
    public function setData(array $data): void;

    public function getId(): int|string;
}
