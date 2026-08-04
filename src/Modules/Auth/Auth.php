<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\Auth;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\RedirectResponseFactory;
use Vihzhuo\Contracts\AuthContract;
use Vihzhuo\Core\View;

class Auth implements AuthContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $action
     * @return ResponseInterface|null
     * @throws Exception
     */
    public function handleRequest(ServerRequestInterface $request, ?string $action = null): ?ResponseInterface
    {
        if (!phpb_in_module('auth')) {
            return null;
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;
        if ($action === 'login' && is_string($username) && is_string($password)) {
            if ($username === phpb_config('auth.username') && $password === phpb_config('auth.password')) {
                $_SESSION['phpb_logged_in'] = true;
                return RedirectResponseFactory::create(phpb_url('website_manager'));
            }
            $_SESSION['phpb_flash'] = [
                'message-type' => 'warning',
                'message' => phpb_trans('auth.invalid-credentials')
            ];
            return RedirectResponseFactory::create(phpb_url('website_manager'));
        }
        if ($action === 'logout') {
            unset($_SESSION['phpb_logged_in']);
            return RedirectResponseFactory::create(phpb_url('website_manager'));
        }

        return $this->renderLoginForm();
    }

    /**
     * Return whether the current request has an authenticated session.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['phpb_logged_in']);
    }

    /**
     * If the user is not authenticated, show the login form.
     *
     * @throws Exception
     */
    public function requireAuth(): ?ResponseInterface
    {
        if (! $this->isAuthenticated()) {
            return $this->renderLoginForm();
        }

        return null;
    }

    /**
     * Render the login form.
     *
     * @throws Exception
     */
    public function renderLoginForm(): ResponseInterface
    {
        return HtmlResponseFactory::create(View::render(
            __DIR__ . '/resources/views/layout.php',
            ['viewFile' => 'login-form']
        ));
    }
}
