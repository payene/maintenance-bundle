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
            $this->addFlash('success', 'Mode maintenance désactivé.');
        } else {
            $this->manager->enable();
            $this->addFlash('success', 'Mode maintenance activé.');
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
                $this->addFlash('error', sprintf('Date invalide : "%s".', $rawUntil));

                return $this->redirectToRoute('payene_maintenance_index');
            }
        }

        $rawRetry = $request->request->get('retry_after');
        $retryAfter = ($rawRetry !== null && $rawRetry !== '') ? (int) $rawRetry : 3600;

        $this->manager->updateSettings($until, $message, $retryAfter, $overdueMessage);
        $this->addFlash('success', 'Paramètres enregistrés.');

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/whitelist/ip/add', name: 'whitelist_ip_add', methods: ['POST'])]
    public function addWhitelistIp(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_whitelist_ip_add');

        $ip = trim((string) $request->request->get('ip', ''));
        if ($ip === '') {
            $this->addFlash('error', 'Adresse IP vide.');

            return $this->redirectToRoute('payene_maintenance_index');
        }

        if ($this->manager->addWhitelistIp($ip)) {
            $this->addFlash('success', sprintf('IP %s ajoutée.', $ip));
        } else {
            $this->addFlash('warning', sprintf('IP %s déjà présente.', $ip));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/whitelist/ip/remove', name: 'whitelist_ip_remove', methods: ['POST'])]
    public function removeWhitelistIp(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_whitelist_ip_remove');

        $ip = (string) $request->request->get('ip', '');
        if ($this->manager->removeWhitelistIp($ip)) {
            $this->addFlash('success', sprintf('IP %s retirée.', $ip));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/whitelist/path/add', name: 'whitelist_path_add', methods: ['POST'])]
    public function addWhitelistPath(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_whitelist_path_add');

        $path = trim((string) $request->request->get('path', ''));
        if ($path === '' || !str_starts_with($path, '/')) {
            $this->addFlash('error', 'Le chemin doit commencer par /.');

            return $this->redirectToRoute('payene_maintenance_index');
        }

        if ($this->manager->addWhitelistPath($path)) {
            $this->addFlash('success', sprintf('Chemin %s ajouté.', $path));
        } else {
            $this->addFlash('warning', sprintf('Chemin %s déjà présent.', $path));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/whitelist/path/remove', name: 'whitelist_path_remove', methods: ['POST'])]
    public function removeWhitelistPath(Request $request): Response
    {
        $this->assertCsrf($request, 'maintenance_whitelist_path_remove');

        $path = (string) $request->request->get('path', '');
        if ($this->manager->removeWhitelistPath($path)) {
            $this->addFlash('success', sprintf('Chemin %s retiré.', $path));
        }

        return $this->redirectToRoute('payene_maintenance_index');
    }

    #[Route('/preview', name: 'preview', methods: ['GET'])]
    public function preview(Request $request): Response
    {
        $state = $this->manager->getState();

        // Override live via query params (pour l'aperçu temps réel)
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
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
    }
}