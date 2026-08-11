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
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
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
     *     lqip: string
     * }
     */
    public function process(string $path, string $sourceDir, string $outputDir, int $width, ?int $height = null, ?string $ext = null): array
    {
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $srcPath = rtrim($sourceDir, '/') . $path;

        if (!$write->isExist($srcPath)) {
            throw new GraphQlInputException(__('Source image not found: %1', $path));
        }

        $basic = $this->build($path, $sourceDir, $outputDir, $width, $height, ext: $ext) ?? $sourceDir . $path;
        $images = [$width => $basic];

        for ($i = 2;$i <= 3;$i++) {
            $im = $this->build(
                $path,
                $sourceDir,
                $outputDir,
                width: $width,
                height: $height,
                scale: $i,
                ext: $ext,
            );
            if ($im === null) {
                continue;
            }
            $images[$width * $i] = $im;
        }

        $set = [];
        foreach ($images as $w => $p) {
            $set[] = $this->fileToUrl($write->getAbsolutePath($p)) . ' ' . $w . 'w';
        }

        return [
            'srcset' => implode(', ', $set),
            'url' => $this->fileToUrl($write->getAbsolutePath($basic)),
            'lqip' => $this->lqip(
                $path,
                $sourceDir,
                $outputDir,
                $ext,
            ),
        ];
    }

    public function build(string $srcImg, string $sourceDir, string $outputDir, int $width, ?int $height = null, int $scale = 1, ?string $ext = null): ?string
    {
        $write = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $imageInfo = $this->file->getPathInfo($srcImg);

        $destFolder =  $outputDir . '/' . $width . 'x' . ($height ?? '') . '/' . ltrim($imageInfo['dirname'], '/');
        $write->create($destFolder);
        $w = $width * $scale;
        $h = $height !== null ? $height * $scale : null;
        $suffix = $scale > 1 ? '_' . $w . 'x' . ($h ?? '') : '';
        $destImg = $destFolder . '/' . $imageInfo['filename'] . $suffix . '.' . ($ext ?? $imageInfo['extension']);

        if ($write->isExist($destImg)) {
            return $destImg;
        }

        $result = $this->resize($write->getAbsolutePath($sourceDir . $srcImg), $write->getAbsolutePath($destImg), $w, $h);
        if ($result === null) {
            return $result;
        }

        return $destImg;
    }

    public function resize(string $srcImg, string $destImg, int $width, ?int $height = null): ?string
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

    public function lqip(string $srcImg, string $sourceDir, string $outputDir, ?string $ext = null): ?string
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
