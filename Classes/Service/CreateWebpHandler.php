<?php

namespace Plan2net\Webp\Service;

use Plan2net\Webp\Converter\Exception\ConvertedFileLargerThanOriginalException;
use Plan2net\Webp\Converter\Exception\WillNotRetryWithConfigurationException;
use Plan2net\Webp\Service\Webp as WebpService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CreateWebpHandler
{
    public function __construct(private WebpService $webpService, private ProcessedFileRepository $processedFileRepository)
    {
    }

    public function __invoke(CreateWebp $command): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        try {
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

            $processedFile = $resourceFactory->getFileObjectFromCombinedIdentifier($command->processedFileIdentifier);
            $file = $resourceFactory->getFileObjectFromCombinedIdentifier($command->processedFileWebpIdentifier);

            $processedFileWebp = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration(
                $file,
                $command->taskType,
                $command->configuration + [
                    'webp' => true,
                ]
            );

            /** @var WebpService $service */
            $service = GeneralUtility::makeInstance(WebpService::class);
            $service->process($processedFile, $processedFileWebp);

            // This will add or update
            $this->processedFileRepository->add($processedFileWebp);
        } catch (WillNotRetryWithConfigurationException $e) {
            $logger->notice($e->getMessage());
        } catch (ConvertedFileLargerThanOriginalException $e) {
            $logger->warning($e->getMessage());
            $this->removeProcessedFile($processedFileWebp);
        } catch (\Exception $e) {
            $logger->error(
                \sprintf(
                    'Failed to convert image "%s" to webp with: %s',
                    $processedFile->getIdentifier(),
                    $e->getMessage()
                )
            );
            $this->removeProcessedFile($processedFileWebp);
        }
    }
}
