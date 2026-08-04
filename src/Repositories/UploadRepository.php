<?php

declare(strict_types=1);

namespace Vihzhuo\Repositories;

use ReflectionException;
use Vihzhuo\UploadedFile;

/** @extends BaseRepository<UploadedFile> */
class UploadRepository extends BaseRepository
{
    /**
     * The uploads database table.
     *
     * @var string
     */
    protected string $table = 'uploads';

    /**
     * The class that represents each uploaded file.
     *
     * @var class-string<UploadedFile>
     */
    protected string $class = UploadedFile::class;

    /**
     * Create a new uploaded file.
     *
     * @param array<string, mixed> $data
     * @return UploadedFile|false|null
     * @throws ReflectionException
     */
    public function create(array $data): UploadedFile|false|null
    {
        $fields = ['public_id', 'original_file', 'mime_type', 'server_file'];
        if (array_any($fields, fn($field) => !isset($data[$field]) || !is_string($data[$field]))) {
            return false;
        }

        $record = $this->createRecord([
            'public_id' => $data['public_id'],
            'original_file' => $data['original_file'],
            'mime_type' => $data['mime_type'],
            'server_file' => $data['server_file'],
        ]);
        return $record instanceof UploadedFile ? $record : null;
    }
}
