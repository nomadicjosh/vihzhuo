<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface WebsiteManagerContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $route
     * @param string|null $action
     * @return ResponseInterface
     */
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null
    ): ResponseInterface;

    /**
     * Render the website manager overview page.
     */
    public function renderOverview(): ResponseInterface;

    /**
     * Render the website manager page settings (add/edit page form).
     *
     * @param ?PageContract $page
     */
    public function renderPageSettings(?PageContract $page = null): ResponseInterface;

    /**
     * Render the website manager menu settings (add/edit menu form).
     */
    public function renderMenuSettings(): ResponseInterface;

    /**
     * Render the welcome page.
     */
    public function renderWelcomePage(): ResponseInterface;
}
