<?php

declare(strict_types=1);

namespace Vihzhuo\Examples\Blocks\PageBanner;

use Vihzhuo\Modules\GrapesJS\Block\BaseModel;

final class Model extends BaseModel
{
    private string $pageName = 'Untitled page';

    private string $renderingContext = '';

    protected function init(): void
    {
        $pageName = $this->page?->getName();
        if (is_string($pageName) && $pageName !== '') {
            $this->pageName = $pageName;
        }
    }

    public function pageName(): string
    {
        return $this->pageName;
    }

    public function setRenderingContext(string $renderingContext): void
    {
        $this->renderingContext = $renderingContext;
    }

    public function renderingContext(): string
    {
        return $this->renderingContext;
    }

    public function linkUrl(): string
    {
        $url = trim($this->setting('link_url', true));
        if (str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return phpb_e($url);
        }

        return '#';
    }
}
