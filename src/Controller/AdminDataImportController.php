<?php

namespace App\Controller;

use App\Import\ImportManager;
use App\Import\ImportResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Import\ImportSchema;
use App\Import\ImportTemplateGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

#[Route('/admin/data-import')]
final class AdminDataImportController extends AbstractController
{
    private const MAX_FILE_SIZE = 10_000_000;

    #[Route(
        '',
        name: 'admin_data_import',
        methods: ['GET', 'POST']
    )]
    public function index(
        Request $request,
        ImportManager $manager
    ): Response {
        $result = null;
        $type = $request->isMethod('POST')
            ? (string) $request->request->get(
                'type',
                'products'
            )
            : 'products';

        $mode = (string) $request
            ->request
            ->get(
                'mode',
                'preview'
            );

        if ($request->isMethod('POST')) {
            try {
                if (
                    !$this->isCsrfTokenValid(
                        'admin-data-import',
                        (string) $request
                            ->request
                            ->get('_token')
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Le jeton de sécurité est invalide.'
                    );
                }

                if (
                    !in_array(
                        $type,
                        ImportManager::SUPPORTED_TYPES,
                        true
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Le type d’import est invalide.'
                    );
                }

                $file = $request
                    ->files
                    ->get('import_file');

                if (
                    !$file instanceof UploadedFile
                    || !$file->isValid()
                ) {
                    throw new \InvalidArgumentException(
                        'Sélectionnez un fichier lisible.'
                    );
                }

                if (
                    null !== $file->getSize()
                    && $file->getSize()
                        > self::MAX_FILE_SIZE
                ) {
                    throw new \InvalidArgumentException(
                        'Le fichier dépasse la taille maximale de 10 MB.'
                    );
                }

                $extension = strtolower(
                    $file
                        ->getClientOriginalExtension()
                );

                if (
                    !in_array(
                        $extension,
                        ImportManager::SUPPORTED_EXTENSIONS,
                        true
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Seuls les fichiers JSON et XLSX sont acceptés.'
                    );
                }

                if (
                    !in_array(
                        $mode,
                        ['preview', 'import'],
                        true
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Le mode d’import est invalide.'
                    );
                }

                $temporaryFile = sprintf(
                    '%s/shopwho-import-%s.%s',
                    sys_get_temp_dir(),
                    bin2hex(random_bytes(16)),
                    $extension
                );

                if (
                    !copy(
                        $file->getPathname(),
                        $temporaryFile
                    )
                ) {
                    throw new \RuntimeException(
                        'Impossible de préparer le fichier temporaire.'
                    );
                }

                try {
                    $result = $manager->import(
                        $type,
                        $temporaryFile,
                        'preview' === $mode
                    );
                } finally {
                    if (is_file($temporaryFile)) {
                        @unlink($temporaryFile);
                    }
                }

                if ('preview' === $mode) {
                    $this->addFlash(
                        'success',
                        'Prévisualisation terminée : aucune donnée n’a été enregistrée.'
                    );
                } elseif (
                    $result instanceof ImportResult
                    && 0 === $result->getFailed()
                ) {
                    $this->addFlash(
                        'success',
                        'Import terminé avec succès.'
                    );
                } else {
                    $this->addFlash(
                        'error',
                        'Import terminé avec une ou plusieurs erreurs.'
                    );
                }
            } catch (\Throwable $exception) {
                $this->addFlash(
                    'error',
                    $exception->getMessage()
                );
            }
        }

        return $this->render(
            'admin/data_import/index.html.twig',
            [
                'result' => $result,
                'type' => $type,
                'mode' => $mode,
                'supportedTypes' =>
                    ImportManager::SUPPORTED_TYPES,
            ]
        );
    }

    #[Route(
        '/template/{type}.{format}',
        name: 'admin_data_import_template',
        methods: ['GET']
    )]
    public function template(
        string $type,
        string $format,
        ImportTemplateGenerator $templates
    ): Response {
        if (
            !in_array(
                $type,
                ImportSchema::TYPES,
                true
            )
            || !in_array(
                $format,
                ImportSchema::FORMATS,
                true
            )
        ) {
            throw $this
                ->createNotFoundException();
        }

        $filename = sprintf(
            'shopwho-%s-template.%s',
            $type,
            $format
        );

        if ('json' === $format) {
            $response = new Response(
                $templates->json($type)
            );

            $response->headers->set(
                'Content-Type',
                'application/json; charset=UTF-8'
            );
        } else {
            $response = new BinaryFileResponse(
                $templates->xlsx($type)
            );

            $response->deleteFileAfterSend(
                true
            );
        }

        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename
            )
        );

        return $response;
    }
}
