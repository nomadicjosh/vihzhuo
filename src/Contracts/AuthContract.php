<?php

namespace Vihzhuo\Contracts;

interface AuthContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param string $action
     */
    public function handleRequest(string $action): void;

    /**
     * Return whether the current request has an authenticated session.
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * If the current user is not authenticated, show the login form.
     */
    public function requireAuth();

    /**
     * Render the login form.
     */
    public function renderLoginForm();
}
