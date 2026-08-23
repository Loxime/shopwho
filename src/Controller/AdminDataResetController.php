<?php

namespace App\Controller;

use App\DataReset\DataResetManager;
use App\DataReset\ResetEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/data-reset')]
final class AdminDataResetController extends AbstractController
{
    private const SESSION_KEY = 'data_reset_previews';
    private const MAX_FILE_SIZE = 10_000_000;
    private const TOKEN_TTL = 1800;

    #[Route('', name: 'admin_data_reset', methods: ['GET', 'POST'])]
    public function index(Request $request, DataResetManager $manager): Response
    {
        $result = null;
        $token = null;
        $type = (string) $request->request->get('type', 'users');

        if ($request->isMethod('POST')) {
            try {
                $file = $request->files->get('reset_file');
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    throw new \InvalidArgumentException('Sélectionnez un fichier lisible.');
                }
                if ($file->getSize() > self::MAX_FILE_SIZE) {
                    throw new \InvalidArgumentException('Le fichier dépasse la taille maximale de 10 MB.');
                }
                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, ['json', 'xlsx'], true)) {
                    throw new \InvalidArgumentException('Seuls les fichiers JSON et XLSX sont acceptés.');
                }
                [$resetType, $payload] = $manager->read($type, $file->getPathname(), $extension);
                $result = $manager->previewReferences($resetType->value, $payload->references, $payload->issues);
                $token = bin2hex(random_bytes(32));
                $previews = $this->activePreviews($request);
                $previews[$token] = [
                    'type' => $resetType->value,
                    'references' => $payload->references,
                    'issues' => array_map(static fn (ResetEntry $entry): array => $entry->jsonSerialize(), $payload->issues),
                    'expiresAt' => time() + self::TOKEN_TTL,
                ];
                $request->getSession()->set(self::SESSION_KEY, $previews);
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('admin/data_reset/index.html.twig', compact('result', 'token', 'type'));
    }

    #[Route('/apply', name: 'admin_data_reset_apply', methods: ['POST'])]
    public function apply(Request $request, DataResetManager $manager): Response
    {
        $token = (string) $request->request->get('preview_token');
        if (!$this->isCsrfTokenValid('data-reset-apply-'.$token, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide. Aucune suppression effectuée.');
            return $this->redirectToRoute('admin_data_reset');
        }

        $previews = $this->activePreviews($request);
        $preview = $previews[$token] ?? null;
        if (!is_array($preview)) {
            $this->addFlash('error', 'Prévisualisation invalide ou expirée. Aucune suppression effectuée.');
            return $this->redirectToRoute('admin_data_reset');
        }

        $issues = array_map(static fn (array $entry): ResetEntry => new ResetEntry(
            $entry['externalRef'], $entry['status'], $entry['reason'], $entry['record'], $entry['relatedCount'],
        ), $preview['issues']);

        try {
            $result = $manager->applyReferences($preview['type'], $preview['references'], $issues);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Échec transactionnel : '.$exception->getMessage());
            return $this->redirectToRoute('admin_data_reset');
        }

        unset($previews[$token]);
        $request->getSession()->set(self::SESSION_KEY, $previews);
        $request->getSession()->set('data_reset_last_result', $result);
        $this->addFlash('success', sprintf('%d suppression(s) appliquée(s).', $result->getDeleted()));

        return $this->redirectToRoute('admin_data_reset_result');
    }

    #[Route('/result', name: 'admin_data_reset_result', methods: ['GET'])]
    public function result(Request $request): Response
    {
        $result = $request->getSession()->remove('data_reset_last_result');
        if (!$result instanceof \App\DataReset\ResetResult) {
            return $this->redirectToRoute('admin_data_reset');
        }

        return $this->render('admin/data_reset/index.html.twig', [
            'result' => $result,
            'token' => null,
            'type' => 'users',
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    private function activePreviews(Request $request): array
    {
        $previews = $request->getSession()->get(self::SESSION_KEY, []);

        return array_filter(is_array($previews) ? $previews : [], static fn (mixed $preview): bool => is_array($preview) && ($preview['expiresAt'] ?? 0) >= time());
    }
}
