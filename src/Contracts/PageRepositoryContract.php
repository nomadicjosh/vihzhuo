<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

interface PageRepositoryContract
{
    /**
     * Create a new page.
     *
     * @param array<string, mixed> $data
     * @return bool|object
     */
    public function create(array $data): PageContract|false;

    /**
     * Update the given page with the given updated data.
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     */
    public function update(PageContract $page, array $data): bool;
}
