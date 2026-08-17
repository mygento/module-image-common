<?php

/**
 * @author Mygento Team
 * @copyright 2026 Mygento (https://www.mygento.com)
 * @package Mygento_ImageCommon
 */

namespace Mygento\ImageCommon\Model;

class Resizer
{
    public function __construct(
        private ImageProcessor $service,
        private string $srcPath = '',
        private string $outputPath = 'cache',
        private bool $lqip = false,
    ) {}

    /**
     * @return array{
     *     srcset: string,
     *     url: string,
     *     lqip?: string,
     *     list: array<array{
     *         width: int,
     *         url: string
     *     }>
     * }
     */
    public function execute(string $imagePath, int $width, ?int $height = null, ?string $ext = null): array
    {
        return $this->service->process(
            path: $imagePath,
            sourceDir: $this->srcPath,
            outputDir: $this->outputPath,
            width: $width,
            height: $height,
            ext: $ext,
            lqip: $this->lqip,
        );
    }

    public function getSourceDirectory(): string
    {
        return rtrim($this->srcPath, '/') . '/';
    }
}
