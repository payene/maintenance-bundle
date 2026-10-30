<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\EventSubscriber;

use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment as TwigEnvironment;

final class MaintenanceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TwigEnvironment $twig,
        private readonly LoggerInterface $logger,
        private readonly MaintenanceManager $manager,
        private readonly string $panelPath,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onMaintenance', \PHP_INT_MAX - 1000],
            ],
        ];
    }

    public function onMaintenance(RequestEvent $event): void
    {
        $state = $this->manager->getState();

        if (!$state->enabled) {
            return;
        }

        $request = $event->getRequest();
        $ip      = $request->getClientIp();
        $path    = $request->getPathInfo();

        // Garde-fou : l'interface d'administration reste toujours accessible
        if (str_starts_with($path, $this->panelPath)) {
            $this->logger->debug('Maintenance: admin panel bypass', ['path' => $path]);

            return;
        }

        if ($state->isIpWhitelisted($ip)) {
            $this->logger->debug('Maintenance: IP whitelisted', ['ip' => $ip]);

            return;
        }

        if ($state->isPathWhitelisted($path)) {
            $this->logger->debug('Maintenance: path whitelisted', ['path' => $path]);

            return;
        }

        $overdue = $state->isOverdue();

        $response = new Response(
            $this->twig->render('@Maintenance/maintenance.html.twig', [
                'message'         => $state->message,
                'overdue_message' => $state->overdueMessage,
                'since'           => $state->since,
                'until'           => $overdue ? null : $state->until,
                'overdue'         => $overdue,
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
        );

        $response->headers->set('Retry-After', (string) $state->retryAfter);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        $this->logger->notice('Maintenance: blocking request', [
            'ip'   => $ip,
            'path' => $path,
        ]);

        $event->setResponse($response);
        $event->stopPropagation();
    }
}