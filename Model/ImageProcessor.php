<?php

/**
 * @author Mygento Team
 * @copyright 2026 Mygento (https://www.mygento.com)
 * @package Mygento_ImageCommon
 */

namespace Mygento\ImageCommon\Model;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ImageProcessor
{
    private ImageManager $imageManager;

    public function __construct(
        private Config $config,
        private Filesystem $filesystem,
        private Filesystem\Io\File $file,
        private StoreManagerInterface $storeManager,
    ) {
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
        $this->imageManager = new ImageManager($driver);
    }

    /**
     * @return array{
     *     srcset: string,
     *     url: string,
     *     lqip?: string|null,
     *     list: array<array{
     *         width: int,
     *         url: string
     *     }>
     * }
     */
    public function process(string $path, string $sourceDir, string $outputDir, int $width, ?int $height = null, ?string $ext = null, bool $lqip = false): array
    {
        $ext = $ext ? strtolower($ext) : $ext;
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $sourceDir = rtrim($sourceDir, '/') . '/';
        $srcPath = $sourceDir . $path;

        if (!$write->isExist($srcPath)) {
            throw new LocalizedException(__('Source image not found: %1', $path));
        }
        $imageInfo = $this->file->getPathInfo($path);

        $destFolder =  $outputDir . '/' . $width . 'x' . ($height ?? '') . '/' . ltrim($imageInfo['dirname'], '/');
        $head = $destFolder . '/' . $imageInfo['filename'];
        $tail = '.' . strtolower($ext ?? $imageInfo['extension']);

        $basicImg = $this->buildFilename($head, $tail, $width, $height);
        if (!$write->isExist($basicImg)) {
            // all not yet resized
            $write->create($destFolder);

            return $this->executeResize(
                head: $head,
                tail: $tail,
                srcImg: $path,
                sourceDir: $sourceDir,
                outputDir: $outputDir,
                width: $width,
                height: $height,
                ext: $ext,
                lqip: $lqip,
            );
        }

        $basicUrl = $this->fileToUrl($write->getAbsolutePath($basicImg));
        $list = [
            [
                'url' => $basicUrl,
                'width' => $width,
            ],
        ];
        $set = [$basicUrl . ' ' . $width . 'w'];

        for ($i = 2;$i <= 3;$i++) {
            $im = $this->buildFilename($head, $tail, $width, $height, scale: $i);
            if (!$write->isExist($im)) {
                continue;
            }
            $w = $width * $i;
            $url = $this->fileToUrl($write->getAbsolutePath($im));
            $list[] = [
                'url' => $url,
                'width' => $w,
            ];
            $set[] = $url . ' ' . $w . 'w';
        }

        return $this->makeResult(
            srcset: implode(', ', $set),
            list: $list,
            basicImage: $basicUrl,
            path: $path,
            sourceDir: $sourceDir,
            outputDir: $outputDir,
            ext: $ext,
            lqip: $lqip,
        );
    }

    /**
     * @return array{
     *     srcset: string,
     *     url: string,
     *     lqip?: string|null,
     *     list: array<array{
     *         width: int,
     *         url: string
     *     }>
     * }
     */
    private function executeResize(string $head, string $tail, string $srcImg, string $sourceDir, string $outputDir, int $width, ?int $height = null, ?string $ext = null, bool $lqip = false): array
    {
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $resized = $this->build(
            head: $head,
            tail: $tail,
            srcImg: $srcImg,
            sourceDir: $sourceDir,
            width: $width,
            height: $height,
        );
        $basic = $resized ?? $sourceDir . $srcImg;

        $basicUrl = $this->fileToUrl($write->getAbsolutePath($basic));
        $list = [
            [
                'url' => $basicUrl,
                'width' => $width,
            ],
        ];
        $set = [$basicUrl . ' ' . $width . 'w'];

        for ($i = 2;$i <= 3;$i++) {
            if (!$resized) {
                break;
            }
            $im = $this->build(
                head: $head,
                tail: $tail,
                srcImg: $srcImg,
                sourceDir: $sourceDir,
                width: $width,
                height: $height,
                scale: $i,
            );
            if ($im === null) {
                break;
            }
            $w = $width * $i;
            $url = $this->fileToUrl($write->getAbsolutePath($im));
            $list[] = [
                'url' => $url,
                'width' => $w,
            ];
            $set[] = $url . ' ' . $w . 'w';
        }

        return $this->makeResult(
            srcset: implode(', ', $set),
            list: $list,
            basicImage: $basicUrl,
            path: $srcImg,
            sourceDir: $sourceDir,
            outputDir: $outputDir,
            ext: $ext,
            lqip: $lqip,
        );
    }

    private function build(string $head, string $tail, string $srcImg, string $sourceDir, int $width, ?int $height = null, int $scale = 1): ?string
    {
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $destImg = $this->buildFilename($head, $tail, $width, $height, $scale);
        $w = $width * $scale;
        $h = $height !== null ? $height * $scale : null;

        $result = $this->resize($write->getAbsolutePath($sourceDir . $srcImg), $write->getAbsolutePath($destImg), $w, $h);
        if ($result === null) {
            return $result;
        }

        return $destImg;
    }

    /**
     * @param array<array{
     *     width: int,
     *     url: string
     * }> $list,
     * @return array{
     *     srcset: string,
     *     url: string,
     *     lqip?: string|null,
     *     list: array<array{
     *         width: int,
     *         url: string
     *     }>
     * }
     */
    private function makeResult(string $srcset, array $list, string $basicImage, string $path, string $sourceDir, string $outputDir, ?string $ext = null, bool $lqip = false): array
    {
        $result = [
            'srcset' => $srcset,
            'url' => $basicImage,
            'list' => $list,
        ];

        if ($lqip) {
            $result['lqip'] = $this->lqip(
                $path,
                $sourceDir,
                $outputDir,
                $ext,
            );
        }

        return $result;
    }

    private function buildFilename(string $head, string $tail, int $width, ?int $height = null, int $scale = 1): string
    {
        $w = $width * $scale;
        $h = $height !== null ? $height * $scale : null;
        $suffix = $scale > 1 ? '_' . $w . 'x' . ($h ?? '') : '';

        return $head . $suffix . $tail;
    }

    private function resize(string $srcImg, string $destImg, int $width, ?int $height = null): ?string
    {
        $ext = $this->file->getPathInfo($destImg)['extension'];
        if ($ext === null) {
            return null;
        }

        try {
            $srcImage = $this->imageManager->decode($srcImg);
            $origW = $srcImage->width();
            $origH = $srcImage->height();
            if ($width > $origW) {
                return null;
            }
            if (!is_null($height) && $height > $origH) {
                return null;
            }
            $dstImage = $srcImage->scaleDown($width, $height);
            $this->saveImage($dstImage, $destImg, $ext);
        } catch (\Throwable) {
            return null;
        }

        return $destImg;
    }

    private function lqip(string $srcImg, string $sourceDir, string $outputDir, ?string $ext = null): ?string
    {
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $imageInfo = $this->file->getPathInfo($srcImg);
        $destFolder =  $outputDir . '/lqip/' . ltrim($imageInfo['dirname'], '/');
        $write->create($destFolder);
        $destImg = $destFolder . '/' . $imageInfo['filename'] . '.' . ($ext ?? strtolower($imageInfo['extension']));
        $sourceImagePath = $write->getAbsolutePath($sourceDir . $srcImg);

        if ($write->isExist($destImg)) {
            $path = $write->getAbsolutePath($destImg);
            $mime = new Filesystem\Driver\File\Mime();

            return 'data:' . $mime->getMimeType($path) . ';base64,' . base64_encode($this->file->read($path));
        }

        try {
            $srcImage = $this->imageManager->decode($sourceImagePath);
            $dstImage = $srcImage->scaleDown(16)->blur(10);
            $dstImage->save($write->getAbsolutePath($destImg));

            return $dstImage->encodeUsingFileExtension($ext, quality: 30)->toDataUri();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fileToUrl(string $file): string
    {
        $mediaBaseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
        $mediaPath = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath();

        return str_replace($mediaPath, $mediaBaseUrl, $file);
    }

    private function saveImage(ImageInterface $dstImage, string $destImg, string $ext): void
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $dstImage->save(
                    $destImg,
                    progressive: $this->config->isProgressiveEnabled(),
                    quality: $this->config->getImageQuality('jpeg'),
                );
                break;
            case 'png':
                $dstImage->save($destImg, interlaced: $this->config->isInterlaceEnabled());
                break;
            case 'webp':
            case 'avif':
                $dstImage->save($destImg, quality: $this->config->getImageQuality($ext));
                break;
            default:
                $dstImage->save($destImg, quality: $this->config->getImageQuality('jpeg'));
        }
    }
}
