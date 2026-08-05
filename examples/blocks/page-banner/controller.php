<?php

declare(strict_types=1);

namespace Vihzhuo\Examples\Blocks\PageBanner;

use Vihzhuo\Modules\GrapesJS\Block\BaseController;

final class Controller extends BaseController
{
    public function handleRequest(): void
    {
        if (!$this->model instanceof Model) {
            return;
        }

        $context = $this->forPageBuilder
        ? 'Page-builder preview'
        : 'Public route: ' . $this->page->getRoute();

        $this->model->setRenderingContext($context);
    }
}
