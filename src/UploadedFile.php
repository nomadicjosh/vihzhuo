<?php

namespace Vihzhuo;

use stdClass;

class UploadedFile extends stdClass
{
    public string $public_id;
    public string $original_file;
    /**
     * Return the URL of this uploaded file.
     */
    public function getUrl(): string
    {
        return phpb_full_url(phpb_config('general.uploads_url') . '/' . $this->public_id . '/' . $this->original_file);
    }
}
