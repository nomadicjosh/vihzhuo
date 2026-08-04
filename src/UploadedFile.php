<?php

declare(strict_types=1);

namespace Vihzhuo;

use stdClass;
use Vihzhuo\Contracts\DataRecordContract;

class UploadedFile extends stdClass implements DataRecordContract
{
    public int|string $id;

    public string $public_id;

    public string $original_file;

    public string $mime_type;

    public string $server_file;

    /** @param array<string, mixed> $data */
    public function setData(array $data): void
    {
        $id = $data['id'] ?? '';
        $this->id = is_int($id) || is_string($id) ? $id : '';
        $this->public_id = is_string($data['public_id'] ?? null) ? $data['public_id'] : '';
        $this->original_file = is_string($data['original_file'] ?? null) ? $data['original_file'] : '';
        $this->mime_type = is_string($data['mime_type'] ?? null) ? $data['mime_type'] : 'application/octet-stream';
        $this->server_file = is_string($data['server_file'] ?? null) ? $data['server_file'] : '';
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    /**
     * Return the URL of this uploaded file.
     */
    public function getUrl(): string
    {
        $uploadsUrl = phpb_config('general.uploads_url');
        return phpb_full_url((is_string($uploadsUrl) ? $uploadsUrl : '') . '/' . $this->public_id . '/' . $this->original_file);
    }
}
