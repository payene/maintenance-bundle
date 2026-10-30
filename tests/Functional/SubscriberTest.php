<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Tests\Functional;

use Payene\MaintenanceBundle\EventSubscriber\MaintenanceSubscriber;
use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Payene\MaintenanceBundle\Storage\MaintenanceStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class SubscriberTest extends TestCase
{
    private string $templatesDir;

    protected function setUp(): void
    {
        $this->templatesDir = sys_get_temp_dir() . '/mud_tpl_' . bin2hex(random_bytes(4));
        mkdir($this->templatesDir, 0777, true);
        file_put_contents(
            $this->templatesDir . '/maintenance.html.twig',
            'under maintenance',
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->templatesDir . '/maintenance.html.twig');
        @rmdir($this->templatesDir);
    }

    public function testDoesNothingWhenMaintenanceDisabled(): void
    {
        $event = $this->createEvent('https://example.com/', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(enabled: false))->onMaintenance($event);

        self::assertNull($event->getResponse());
    }

    public function testBlocksWhenEnabledAndNotWhitelisted(): void
    {
        $event = $this->createEvent('https://example.com/', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(
            enabled: true,
            whitelistIps: ['10.0.0.1'],
            whitelistPaths: [],
        ))->onMaintenance($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('3600', $response->headers->get('Retry-After'));
    }

    public function testBypassWhitelistedIp(): void
    {
        $event = $this->createEvent('https://example.com/', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(
            enabled: true,
            whitelistIps: ['8.8.8.8'],
            whitelistPaths: [],
        ))->onMaintenance($event);

        self::assertNull($event->getResponse());
    }

    public function testBypassWhitelistedPath(): void
    {
        $event = $this->createEvent('https://example.com/health', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(
            enabled: true,
            whitelistIps: [],
            whitelistPaths: ['/health'],
        ))->onMaintenance($event);

        self::assertNull($event->getResponse());
    }

    public function testBypassAdminPanelAlways(): void
    {
        $event = $this->createEvent('https://example.com/admin/maintenance', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(
            enabled: true,
            whitelistIps: [],
            whitelistPaths: [],
        ))->onMaintenance($event);

        self::assertNull($event->getResponse());
    }

    public function testRetryAfterHeaderMatchesState(): void
    {
        $event = $this->createEvent('https://example.com/', '8.8.8.8');

        $this->createSubscriber(new MaintenanceState(
            enabled: true,
            retryAfter: 900,
            whitelistIps: [],
            whitelistPaths: [],
        ))->onMaintenance($event);

        self::assertSame('900', $event->getResponse()?->headers->get('Retry-After'));
    }

    private function createSubscriber(MaintenanceState $state): MaintenanceSubscriber
    {
        $storage = new class($state) implements MaintenanceStorageInterface {
            public function __construct(private MaintenanceState $state) {}
            public function load(): MaintenanceState { return $this->state; }
            public function save(MaintenanceState $state): void { $this->state = $state; }
        };

        $loader = new FilesystemLoader();
        $loader->addPath($this->templatesDir, 'Maintenance');
        $twig = new Environment($loader);

        return new MaintenanceSubscriber(
            $twig,
            new NullLogger(),
            new MaintenanceManager($storage),
            '/admin/maintenance',
        );
    }

    private function createEvent(string $uri, string $ip): RequestEvent
    {
        $request = Request::create($uri, 'GET', server: ['REMOTE_ADDR' => $ip]);

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}