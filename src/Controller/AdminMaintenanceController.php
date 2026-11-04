<?php

declare(strict_types=1);

namespace Payene\MaintenanceBundle\Controller;

use Payene\MaintenanceBundle\Model\MaintenanceState;
use Payene\MaintenanceBundle\Service\MaintenanceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'payene_maintenance_')]
final class AdminMaintenanceController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@Maintenance/admin/index.html.twig', [
            'state' => $this->manager->getState(),
        ]);
    }

    #[Route('/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_toggle');

        if ($this->manager->isEnabled()) {
            $this->manager->disable();
            $this->addFlash('success', 'Maintenance mode disabled.');
        } else {
            $this->manager->enable();
            $this->addFlash('success', 'Maintenance mode enabled.');
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/settings', name: 'settings', methods: ['POST'])]
    public function settings(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_settings');

        $message        = trim((string) $request->request->get('message', '')) ?: null;
        $overdueMessage = trim((string) $request->request->get('overdue_message', '')) ?: null;

        $until = null;
        $rawUntil = trim((string) $request->request->get('until', ''));
        if ($rawUntil !== '') {
            try {
                $until = new \DateTimeImmutable($rawUntil);
            } catch (\Exception) {
                $this->addFlash('error', sprintf('Invalid date: "%s".', $rawUntil));

                return $this->redirectToRoute('payene_maintenance_index');
            }
        }

        $rawRetry = $request->request->get('retry_after');
        $retryAfter = ($rawRetry !== null && $rawRetry !== '') ? (int) $rawRetry : 3600;

        $this->manager->updateSettings($until, $message, $retryAfter, $overdueMessage);
        $this->addFlash('success', 'Settings saved.');

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/allowed/ip/add', name: 'allowed_ip_add', methods: ['POST'])]
    public function addAllowedIp(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_allowed_ip_add');

        $ip = trim((string) $request->request->get('ip', ''));
        if ($ip === '') {
            $this->addFlash('error', 'IP address cannot be empty.');

            return $this->redirectToRoute('payene_maintenance_index');
        }

        if ($this->manager->addAllowedIp($ip)) {
            $this->addFlash('success', sprintf('IP %s added.', $ip));
        } else {
            $this->addFlash('warning', sprintf('IP %s is already present.', $ip));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/allowed/ip/remove', name: 'allowed_ip_remove', methods: ['POST'])]
    public function removeAllowedIp(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_allowed_ip_remove');

        $ip = (string) $request->request->get('ip', '');
        if ($this->manager->removeAllowedIp($ip)) {
            $this->addFlash('success', sprintf('IP %s removed.', $ip));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/allowed/path/add', name: 'allowed_path_add', methods: ['POST'])]
    public function addAllowedPath(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_allowed_path_add');

        $path = trim((string) $request->request->get('path', ''));
        if ($path === '' || !str_starts_with($path, '/')) {
            $this->addFlash('error', 'Path must start with /.');

            return $this->redirectToRoute('payene_maintenance_index');
        }

        if ($this->manager->addAllowedPath($path)) {
            $this->addFlash('success', sprintf('Path %s added.', $path));
        } else {
            $this->addFlash('warning', sprintf('Path %s is already present.', $path));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/allowed/path/remove', name: 'allowed_path_remove', methods: ['POST'])]
    public function removeAllowedPath(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_allowed_path_remove');

        $path = (string) $request->request->get('path', '');
        if ($this->manager->removeAllowedPath($path)) {
            $this->addFlash('success', sprintf('Path %s removed.', $path));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/preview', name: 'preview', methods: ['GET'])]
    public function preview(Request $request): Response
    {
        $state = $this->manager->getState();

        // Live override via query params (for real-time preview)
        $message = $request->query->has('message')
            ? trim((string) $request->query->get('message'))
            : $state->message;

        $overdueMessage = $request->query->has('overdue_message')
            ? trim((string) $request->query->get('overdue_message'))
            : $state->overdueMessage;

        $until = $state->until;
        if ($request->query->has('until')) {
            $rawUntil = trim((string) $request->query->get('until'));
            if ($rawUntil === '') {
                $until = null;
            } else {
                try {
                    $until = new \DateTimeImmutable($rawUntil);
                } catch (\Exception) {
                    $until = null;
                }
            }
        }

        $overdue = $until !== null && $until < new \DateTimeImmutable();

        return $this->render('@Maintenance/maintenance.html.twig', [
            'message'         => $message ?: null,
            'overdue_message' => $overdueMessage ?: null,
            'since'           => $state->since,
            'until'           => $overdue ? null : $until,
            'overdue'         => $overdue,
        ]);
    }

    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}