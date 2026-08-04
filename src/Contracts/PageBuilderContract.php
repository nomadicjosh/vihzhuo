<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PageBuilderContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $route
     * @param string|null $action
     * @param PageContract|null $page
     * @return ResponseInterface|null
     */
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null,
        ?PageContract $page = null
    ): ?ResponseInterface;

    /**
     * Render the given page inside the PageBuilder.
     *
     * @param PageContract $page
     */
    public function renderPageBuilder(PageContract $page): ResponseInterface;

    /**
     * Render the given page.
     *
     * @param PageContract $page
     * @param string|null $language
     * @return string
     */
    public function renderPage(PageContract $page, ?string $language = null): string;

    /**
     * Update the given page with the given data (an array of HTML blocks)
     *
     * @param PageContract $page
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updatePage(PageContract $page, array $data): bool;

    /**
     * Get or set custom css for customizing layout of the page builder.
     *
     * @param string|null $css
     * @return string|null
     */
    public function customStyle(?string $css = null): ?string;

    /**
     * Get or set custom scripts for customizing behaviour of the page builder.
     *
     * @param string $location head|body
     * @param string|null $scripts
     * @return string
     */
    public function customScripts(string $location, ?string $scripts = null): string;

    /**
     * Set a theme for the page builder.
     *
     * @param ThemeContract $theme
     */
    public function setTheme(ThemeContract $theme): void;
}
