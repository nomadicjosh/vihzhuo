<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\GrapesJS\Block;

use Vihzhuo\Contracts\PageContract;

class BaseController
{
    protected ?BaseModel $model = null;

    protected ?PageContract $page = null;

    protected bool $forPageBuilder;

    /**
     * Pass essential data to this BaseController instance.
     *
     * @param BaseModel $model
     * @param PageContract $page
     * @param bool $forPageBuilder
     */
    public function init(BaseModel $model, PageContract $page, bool $forPageBuilder = false): void
    {
        $this->model = $model;
        $this->page = $page;
        $this->forPageBuilder = $forPageBuilder;
    }

    /**
     * Handle the current request.
     */
    public function handleRequest(): void
    {
    }
}
