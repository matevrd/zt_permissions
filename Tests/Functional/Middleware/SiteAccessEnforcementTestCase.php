<?php

namespace matevrd\ZtPermissions\Tests\Functional\Middleware;

use matevrd\ZtPermissions\Middleware\SiteAccessEnforcementMiddleware;
use matevrd\ZtPermissions\Tests\Functional\ZtPermissionsFunctionalTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

abstract class SiteAccessEnforcementTestCase extends ZtPermissionsFunctionalTestCase
{
    protected const REMOTE_ADDRESS = '203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendSiteAccess.csv');
        $this->writeSiteConfiguration('main', 1, 'https://main.example.com/');
        $this->writeSiteConfiguration('site_b', 2, 'https://site-b.example.com/');
    }

    protected function processAs(int $userUid, string $host): array
    {
        $this->setUpBackendUser($userUid);

        $handler = new class() implements RequestHandlerInterface {
            public bool $wasCalled = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->wasCalled = true;

                return new Response();
            }
        };

        $response = $this->get(SiteAccessEnforcementMiddleware::class)
            ->process($this->createBackendRequest($host), $handler);

        return [$response, $handler->wasCalled];
    }

    private function createBackendRequest(string $host): ServerRequestInterface
    {
        $serverParams = ['REMOTE_ADDR' => self::REMOTE_ADDRESS, 'HTTP_HOST' => $host];
        $request = new ServerRequest('https://' . $host . '/typo3/main', 'GET', null, [], $serverParams);

        return $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }
}
