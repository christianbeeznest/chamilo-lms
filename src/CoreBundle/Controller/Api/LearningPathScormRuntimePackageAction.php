<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\CoreBundle\Controller\Api;

use ApiPlatform\Metadata\Get;
use Chamilo\CoreBundle\Entity\Asset;
use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Entity\ResourceFile;
use Chamilo\CoreBundle\Entity\ResourceLink;
use Chamilo\CoreBundle\Helpers\CidReqHelper;
use Chamilo\CoreBundle\Repository\AssetRepository;
use Chamilo\CoreBundle\Repository\ResourceNodeRepository;
use Chamilo\CoreBundle\Service\LearningPath\ScormRuntimeManager;
use Chamilo\CoreBundle\State\LearningPath\LearningPathRuntimeProvider;
use Chamilo\CoreBundle\State\LearningPath\LearningPathStateHelperTrait;
use Chamilo\CourseBundle\Entity\CDocument;
use Chamilo\CourseBundle\Entity\CLp;
use Chamilo\CourseBundle\Entity\CLpItem;
use Chamilo\CourseBundle\Repository\CDocumentRepository;
use Chamilo\CourseBundle\Repository\CLpItemRepository;
use Chamilo\CourseBundle\Repository\CLpRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PhpZip\ZipFile;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

use const PATHINFO_EXTENSION;
use const PHP_SESSION_ACTIVE;

#[AsController]
final readonly class LearningPathScormRuntimePackageAction
{
    use LearningPathStateHelperTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AssetRepository $assetRepository,
        private ResourceNodeRepository $resourceNodeRepository,
        private CDocumentRepository $documentRepository,
        private CLpRepository $learningPathRepository,
        private CLpItemRepository $learningPathItemRepository,
        private LearningPathRuntimeProvider $runtimeProvider,
        private ScormRuntimeManager $runtimeManager,
        private CidReqHelper $cidReqHelper,
    ) {}

    public function __invoke(int $lpId, Request $request): Response
    {
        $itemId = $request->query->getInt('itemId');
        if ($lpId <= 0 || $itemId <= 0) {
            throw new BadRequestHttpException('A valid SCORM learning path item is required.');
        }

        $learningPath = $this->learningPathRepository->find($lpId);
        $item = $this->learningPathItemRepository->find($itemId);
        if (!$learningPath instanceof CLp
            || !$item instanceof CLpItem
            || (int) $item->getLp()->getIid() !== $lpId
            || !$this->runtimeManager->isScormLearningPath($learningPath)
            || !$this->runtimeManager->isScormPackageItem($item)
        ) {
            throw new NotFoundHttpException('SCORM runtime package not found.');
        }

        $course = $this->cidReqHelper->requireDoctrineCourseEntity();
        $session = $this->cidReqHelper->getDoctrineSessionEntity();
        $group = $this->getContextGroup($this->entityManager, $this->cidReqHelper, $course);
        $resourceNode = $learningPath->getResourceNode();
        if (null === $resourceNode || !$this->security->isGranted('VIEW', $resourceNode)) {
            throw new AccessDeniedHttpException('The SCORM learning path is not available.');
        }

        $resourceLink = $this->getContextResourceLink($learningPath, $course, $session, $group);
        if (!$resourceLink instanceof ResourceLink) {
            throw new AccessDeniedHttpException('The SCORM learning path is not linked to this context.');
        }
        if (!$this->canManageLearningPaths($this->security)
            && ResourceLink::VISIBILITY_PUBLISHED !== $resourceLink->getVisibility()
        ) {
            throw new AccessDeniedHttpException('The SCORM learning path is not visible.');
        }

        $runtime = $this->runtimeProvider->provide(
            new Get(),
            ['lpId' => $lpId],
            ['runtime_item_id' => $itemId],
        );
        if (!$runtime->runtimeSupported
            || $runtime->currentItemId !== $itemId
            || '' === (string) ($runtime->scorm['packageEntryPath'] ?? '')
        ) {
            throw new AccessDeniedHttpException('The SCORM item is not available.');
        }

        $source = $runtime->isCStudioContent
            ? $this->createCStudioRuntimePackageSource($learningPath)
            : $this->openOriginalPackageSource($learningPath, $course);
        $stream = $source['stream'];
        $fileSize = $source['size'];
        $cleanupPath = $source['cleanupPath'];

        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $response = new StreamedResponse(static function () use ($stream, $cleanupPath): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
                if (null !== $cleanupPath) {
                    @unlink($cleanupPath);
                }
            }
        });
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $source['downloadName'],
                $this->asciiFallbackName($source['downloadName']),
            ),
        );
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Length', (string) $fileSize);
        $response->headers->set(
            'X-Chamilo-Scorm-Fingerprint',
            (string) ($runtime->scorm['packageFingerprint'] ?? ''),
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    /**
     * @return array{stream: resource, size: int, downloadName: string, cleanupPath: null}
     */
    private function openOriginalPackageSource(CLp $learningPath, Course $course): array
    {
        $source = $this->resolvePackageSource($learningPath, $course);
        $stream = $source['filesystem']->readStream($source['path']);
        if (!\is_resource($stream)) {
            throw new NotFoundHttpException('The SCORM ZIP package could not be read.');
        }

        return [
            'stream' => $stream,
            'size' => $source['filesystem']->fileSize($source['path']),
            'downloadName' => $source['downloadName'],
            'cleanupPath' => null,
        ];
    }

    /**
     * CStudio imports an initial SCORM ZIP and then renders the editable project
     * into the extracted asset folder. The original ZIP therefore does not
     * necessarily contain the latest rendered project. Runtime playback must
     * package the extracted folder instead of returning that stale source ZIP.
     *
     * @return array{stream: resource, size: int, downloadName: string, cleanupPath: string}
     */
    private function createCStudioRuntimePackageSource(CLp $learningPath): array
    {
        $asset = $learningPath->getAsset();
        if (!$asset instanceof Asset) {
            throw new NotFoundHttpException('The CStudio runtime package asset is missing.');
        }

        $filesystem = $this->assetRepository->getFileSystem();
        $assetFolder = trim((string) $this->assetRepository->getFolder($asset), '/');
        $learningPathFolder = $this->normalizePackageFolder((string) $learningPath->getPath());
        if ('' === $assetFolder || '' === $learningPathFolder) {
            throw new NotFoundHttpException('The CStudio runtime package folder could not be resolved.');
        }

        $folder = $assetFolder.'/'.$learningPathFolder;
        if (!$filesystem->directoryExists($folder)) {
            throw new NotFoundHttpException('The CStudio runtime package folder could not be found.');
        }

        $temporaryZip = rtrim(sys_get_temp_dir(), '/').'/cstudio-runtime-'.bin2hex(random_bytes(16)).'.zip';
        $zip = new ZipFile();
        $fileCount = 0;

        try {
            $folderPrefix = $folder.'/';
            foreach ($filesystem->listContents($folder, true) as $entry) {
                if (!$entry->isFile()) {
                    continue;
                }

                $entryPath = trim(str_replace('\\', '/', $entry->path()), '/');
                if (!str_starts_with($entryPath, $folderPrefix)) {
                    continue;
                }

                $relativePath = substr($entryPath, \strlen($folderPrefix));
                $relativePath = $this->normalizePackageFilePath($relativePath);
                if ('' === $relativePath) {
                    continue;
                }

                $zip->addFromString($relativePath, $filesystem->read($entry->path()));
                ++$fileCount;
            }

            if (0 === $fileCount) {
                throw new NotFoundHttpException('The CStudio runtime package is empty.');
            }

            $zip->saveAsFile($temporaryZip);
        } catch (Throwable $exception) {
            @unlink($temporaryZip);

            if ($exception instanceof NotFoundHttpException) {
                throw $exception;
            }

            throw new NotFoundHttpException('The CStudio runtime package could not be created.', $exception);
        } finally {
            $zip->close();
        }

        $fileSize = @filesize($temporaryZip);
        $stream = @fopen($temporaryZip, 'rb');
        if (false === $fileSize || !\is_resource($stream)) {
            @unlink($temporaryZip);

            throw new NotFoundHttpException('The CStudio runtime package could not be read.');
        }

        return [
            'stream' => $stream,
            'size' => (int) $fileSize,
            'downloadName' => $this->downloadName($learningPath, $asset, null),
            'cleanupPath' => $temporaryZip,
        ];
    }

    private function normalizePackageFolder(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ('' === $normalized) {
            return '';
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ('' === $segment || '..' === $segment) {
                return '';
            }

            if ('.' === $segment) {
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function normalizePackageFilePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path), '/');
        if ('' === $normalized) {
            return '';
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                return '';
            }
        }

        return implode('/', $segments);
    }

    /**
     * @return array{filesystem: FilesystemOperator, path: string, downloadName: string}
     */
    private function resolvePackageSource(CLp $learningPath, Course $course): array
    {
        $asset = $learningPath->getAsset();
        if ($asset instanceof Asset) {
            $assetPath = trim((string) $this->assetRepository->getStorage()->resolveUri($asset));
            $assetFilesystem = $this->assetRepository->getFileSystem();
            if ('' !== $assetPath && $assetFilesystem->fileExists($assetPath)) {
                return [
                    'filesystem' => $assetFilesystem,
                    'path' => $assetPath,
                    'downloadName' => $this->downloadName($learningPath, $asset, null),
                ];
            }
        }

        $document = $this->documentRepository->findScormZipDocument($course, $learningPath);
        if ($document instanceof CDocument) {
            $resourceFile = $document->getResourceNode()?->getFirstResourceFile();
            if ($resourceFile instanceof ResourceFile) {
                $resourcePath = trim((string) $this->resourceNodeRepository->getFilename($resourceFile));
                $resourceFilesystem = $this->resourceNodeRepository->getFileSystem();
                if ('' !== $resourcePath && $resourceFilesystem->fileExists($resourcePath)) {
                    return [
                        'filesystem' => $resourceFilesystem,
                        'path' => $resourcePath,
                        'downloadName' => $this->downloadName($learningPath, $asset, $resourceFile),
                    ];
                }
            }
        }

        throw new NotFoundHttpException('The original SCORM ZIP package cannot be found.');
    }

    private function downloadName(CLp $learningPath, ?Asset $asset, ?ResourceFile $resourceFile): string
    {
        foreach ([
            $asset?->getOriginalName(),
            $resourceFile?->getOriginalName(),
            $asset?->getTitle(),
            $learningPath->getTitle(),
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ('' === $candidate) {
                continue;
            }

            $candidate = str_replace(["\r", "\n"], '', basename(str_replace('\\', '/', $candidate)));
            if ('' === $candidate) {
                continue;
            }
            if ('zip' !== strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION))) {
                $candidate .= '.zip';
            }

            return $candidate;
        }

        return \sprintf('learning-path-%d-scorm.zip', (int) $learningPath->getIid());
    }

    private function asciiFallbackName(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', false === $ascii ? '' : $ascii);
        $fallback = trim((string) $fallback, '-.');

        return '' !== $fallback ? $fallback : 'scorm-package.zip';
    }
}
