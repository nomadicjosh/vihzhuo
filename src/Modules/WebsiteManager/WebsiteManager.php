<?php

declare(strict_types=1);

namespace Vihzhuo\Modules\WebsiteManager;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Http\Factories\HtmlResponseFactory;
use Qubus\Http\Factories\TextResponseFactory;
use Vihzhuo\Contracts\PageContract;
use Vihzhuo\Contracts\WebsiteManagerContract;
use Vihzhuo\Core\View;
use Vihzhuo\Repositories\PageRepository;
use Vihzhuo\Repositories\SettingRepository;

class WebsiteManager implements WebsiteManagerContract
{
    /**
     * Process the current GET or POST request and redirect or render the requested page.
     *
     * @param ServerRequestInterface $request
     * @param string|null $route
     * @param string|null $action
     * @return ResponseInterface
     * @throws Exception
     */
    public function handleRequest(
        ServerRequestInterface $request,
        ?string $route = null,
        ?string $action = null
    ): ResponseInterface {
        if (is_null($route)) {
            return $this->renderOverview();
        }

        if ($route === 'settings') {
            if ($action === 'renderBlockThumbs') {
                return $this->renderBlockThumbs();
            }
            if ($action === 'update') {
                return $this->handleUpdateSettings($request);
            }
        }

        if ($route === 'page_settings') {
            if ($action === 'create') {
                return $this->handleCreate($request);
            }

            $pageId = $request->getQueryParams()['page'] ?? null;
            $pageRepository = new PageRepository;
            $page = (is_int($pageId) || is_string($pageId)) ? $pageRepository->findWithId($pageId) : null;
            if (! ($page instanceof PageContract)) {
                return phpb_redirect(phpb_url('website_manager'));
            }

            if ($action === 'edit') {
                return $this->handleEdit($request, $page);
            } elseif ($action === 'destroy') {
                return $this->handleDestroy($page);
            }
        }

        return TextResponseFactory::create('Website manager page not found.', 404);
    }

    /**
     * Handle requests for creating a new page.
     *
     * @throws Exception
     */
    public function handleCreate(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = $request->getParsedBody();
            $pageRepository = new PageRepository;
            $page = $pageRepository->create(is_array($body) ? $this->stringKeyedArray($body) : []);
            if ($page) {
                return phpb_redirect(phpb_url('website_manager'), [
                    'message-type' => 'success',
                    'message' => phpb_trans('website-manager.page-created')
                ]);
            }
        }

        return $this->renderPageSettings();
    }

    /**
     * Handle requests for editing the given page.
     *
     * @param ServerRequestInterface $request
     * @param PageContract $page
     * @return ResponseInterface
     * @throws Exception
     */
    public function handleEdit(ServerRequestInterface $request, PageContract $page): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = $request->getParsedBody();
            $pageRepository = new PageRepository;
            $success = $pageRepository->update($page, is_array($body) ? $this->stringKeyedArray($body) : []);
            if ($success) {
                return phpb_redirect(phpb_url('website_manager'), [
                    'message-type' => 'success',
                    'message' => phpb_trans('website-manager.page-updated')
                ]);
            }
        }

        return $this->renderPageSettings($page);
    }

    /**
     * Handle requests to destroy the given page.
     *
     * @param PageContract $page
     * @return ResponseInterface
     */
    public function handleDestroy(PageContract $page): ResponseInterface
    {
        $pageRepository = new PageRepository;
        $pageRepository->destroy($page->getId());
        return phpb_redirect(phpb_url('website_manager'), [
            'message-type' => 'success',
            'message' => phpb_trans('website-manager.page-deleted')
        ]);
    }

    /**
     * Handle requests for updating the website settings.
     */
    public function handleUpdateSettings(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = $request->getParsedBody();
            $settingRepository = new SettingRepository;
            $success = $settingRepository->updateSettings(is_array($body) ? $this->stringKeyedArray($body) : []);
            if ($success) {
                return phpb_redirect(phpb_url('website_manager', ['tab' => 'settings']), [
                    'message-type' => 'success',
                    'message' => phpb_trans('website-manager.settings-updated')
                ]);
            }
        }
        return phpb_redirect(phpb_url('website_manager', ['tab' => 'settings']));
    }

    /**
     * Render the website manager overview page.
     *
     * @throws Exception
     */
    public function renderOverview(): ResponseInterface
    {
        $pageRepository = new PageRepository;
        $pages = $pageRepository->getAll();

        return HtmlResponseFactory::create(View::render(
            __DIR__ . '/resources/layouts/master.php',
            ['viewFile' => 'overview', 'pages' => $pages]
        ));
    }

    /**
     * Render the website manager page settings (add/edit page form).
     *
     * @param PageContract|null $page
     * @return ResponseInterface
     * @throws Exception
     */
    public function renderPageSettings(?PageContract $page = null): ResponseInterface
    {
        $action = isset($page) ? 'edit' : 'create';
        $theme = phpb_instance('theme', [
            phpb_config('theme'), 
            phpb_config('theme.active_theme')
        ]);

        return HtmlResponseFactory::create(View::render(__DIR__ . '/resources/layouts/master.php', [
            'viewFile' => 'page-settings',
            'action' => $action,
            'theme' => $theme,
            'page' => $page,
        ]));
    }

    /**
     * Render the website manager menu settings (add/edit menu form).
     *
     * @throws Exception
     */
    public function renderMenuSettings(): ResponseInterface
    {
        return HtmlResponseFactory::create(View::render(
            __DIR__ . '/resources/layouts/master.php',
            ['viewFile' => 'menu-settings']
        ));
    }

    /**
     * Render a thumbnail for each theme block.
     *
     * @throws Exception
     */
    public function renderBlockThumbs(): ResponseInterface
    {
        return HtmlResponseFactory::create(View::render(
            __DIR__ . '/resources/layouts/master.php',
            ['viewFile' => 'block-thumbs']
        ));
    }

    /**
     * Render the website manager welcome page for installations without a homepage.
     *
     * @throws Exception
     */
    public function renderWelcomePage(): ResponseInterface
    {
        return HtmlResponseFactory::create(View::render(
            __DIR__ . '/resources/layouts/empty.php',
            ['viewFile' => 'welcome']
        ));
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $data): array
    {
        return array_filter($data, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
