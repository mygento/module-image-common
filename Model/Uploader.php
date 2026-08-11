<?php

/**
 * @author Mygento Team
 * @copyright 2026 Mygento (https://www.mygento.com)
 * @package Mygento_ImageCommon
 */

namespace Mygento\ImageCommon\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Uploader
{
    private WriteInterface $mediaDirectory;

    /**
     * @param array<string> $allowedExtensions
     * @param array<string> $allowedMimeTypes
     */
    public function __construct(
        private Filesystem $filesystem,
        private StoreManagerInterface $storeManager,
        private File\UploaderFactory $uploaderFactory,
        private LoggerInterface $logger,
        private string $basePath,
        private string $baseTmpPath,
        private array $allowedExtensions = [],
        private array $allowedMimeTypes = [],
    ) {
        $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @return array{
     *     name: string,
     *     url: string,
     *     file: string
     * }
     */
    public function saveFileToTmpDir(string $inputFile): array
    {
        /** @var File\Uploader $uploader */
        $uploader = $this->uploaderFactory->create(['fileId' => $inputFile]);
        $uploader->setAllowedExtensions($this->allowedExtensions);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(true);
        if (!$uploader->checkMimeType($this->allowedMimeTypes)) {
            throw new LocalizedException(__('File validation failed.'));
        }
        $result = $uploader->save($this->mediaDirectory->getAbsolutePath($this->baseTmpPath));

        if (!$result) {
            throw new LocalizedException(__('File can not be saved to the destination folder.'));
        }

        /**
         * Workaround for prototype 1.7 methods "isJSON", "evalJSON" on Windows OS
         */
        $result['tmp_name'] = isset($result['tmp_name']) ? str_replace('\\', '/', $result['tmp_name']) : '';

        $result['url'] = $this->storeManager
            ->getStore()
            ->getBaseUrl(
                UrlInterface::URL_TYPE_MEDIA,
            ) . $this->getFilePath(
                $this->baseTmpPath,
                $result['file'],
            );
        $result['name'] = $result['file'];
        unset($result['path']);

        return $result;
    }

    public function moveFileFromTmp(string $inputFile): string
    {
        $finalPath = $this->getFilePath($this->basePath, $inputFile);

        try {
            $this->mediaDirectory->renameFile(
                $this->getFilePath($this->baseTmpPath, $inputFile),
                $finalPath,
            );
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage(), ['exception' => $e]);

            throw new LocalizedException(__('Something went wrong while saving the file(s).'), $e);
        }

        return $finalPath;
    }

    private function getFilePath(string $path, string $imageName): string
    {
        return rtrim($path, '/') . '/' . ltrim($imageName, '/');
    }
}
