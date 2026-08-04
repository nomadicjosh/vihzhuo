<?php

declare(strict_types=1);

namespace Vihzhuo\Repositories;

use ReflectionException;
use Vihzhuo\Contracts\SettingRepositoryContract;
use Vihzhuo\SettingRecord;

/** @extends BaseRepository<SettingRecord> */
class SettingRepository extends BaseRepository implements SettingRepositoryContract
{
    /**
     * The pages database table.
     *
     * @var string
     */
    protected string $table = 'settings';
    /** @var class-string<SettingRecord> */
    protected string $class = SettingRecord::class;

    /**
     * Replace all website settings by the given data.
     *
     * @param array<string, mixed> $data
     * @return bool
     * @throws ReflectionException
     */
    public function updateSettings(array $data): bool
    {
        $this->destroyAll();


        foreach ($data as $key => $value) {
            $isArray = is_array($value);
            if ($isArray) {
                $scalars = array_filter($value, static fn (mixed $item): bool => is_scalar($item));
                $value = implode(',', array_map('strval', $scalars));
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $this->createRecord([
                'setting' => $key,
                'value' => $value === null ? '' : (string) $value,
                'is_array' => $isArray,
            ]);
        }

        return true;
    }
}
