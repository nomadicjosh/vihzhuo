<?php

declare(strict_types=1);

namespace Vihzhuo\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface AuthContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $action
     * @return ResponseInterface|null
     */
    public function handleRequest(ServerRequestInterface $request, ?string $action = null): ?ResponseInterface;

    /**
     * Return whether the current request has an authenticated session.
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * If the current user is not authenticated, show the login form.
     */
    public function requireAuth(): ?ResponseInterface;

    /**
     * Render the login form.
     */
    public function renderLoginForm(): ResponseInterface;
}
