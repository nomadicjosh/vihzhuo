<?php

declare(strict_types=1);

namespace Vihzhuo;

use Vihzhuo\Contracts\DataRecordContract;

final class SettingRecord implements DataRecordContract
{
    public int|string $id;

    public string $setting;

    public string $value;

    public bool|int $is_array;

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $id = $data['id'] ?? '';
        $this->id = is_int($id) || is_string($id) ? $id : '';
        $this->setting = is_string($data['setting'] ?? null) ? $data['setting'] : '';
        $this->value = is_string($data['value'] ?? null) ? $data['value'] : '';
        $isArray = $data['is_array'] ?? false;
        $this->is_array = is_bool($isArray) || is_int($isArray) ? $isArray : false;
    }

    public function getId(): int|string
    {
        return $this->id;
    }
}
