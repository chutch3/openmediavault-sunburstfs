<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * The full scan -> build -> render pipeline, replacing
 * SunburstChartGenerator now that rendering is a standalone HTML+D3
 * page instead of a server-rendered PNG. Shared between the background
 * refresh script (the only thing that calls it) and kept out of the
 * interactive RPC path for the same reason the PNG version was: a large
 * filesystem's scan can take long enough to get omv-engined SIGKILLed
 * by its own service watchdog.
 *
 * Not unit tested itself: composition of already-tested pieces
 * (DiskUsageScannerInterface, SunburstTreeBuilder, SunburstPageTemplate)
 * plus a file read/write -- same "outer ring glue" role
 * SunburstChartGenerator held.
 */
final class SunburstPageGenerator
{
    private const D3_SOURCE_PLACEHOLDER = "__D3_SOURCE__";

    public function __construct(
        private readonly DiskUsageScannerInterface $scanner,
        private readonly SunburstTreeBuilder $builder,
        private readonly SunburstPageTemplate $pageTemplate,
        private readonly int $maxDepth,
        private readonly string $templatePath,
        private readonly string $d3SourcePath
    ) {
    }

    public function generate(string $rootPath): string
    {
        $sizesByPath = $this->scanner->scan($rootPath, $this->maxDepth);
        $tree = $this->builder->build($rootPath, $sizesByPath);

        $templateContents = file_get_contents($this->templatePath);
        // Trusted static content, substituted plainly -- unlike the
        // tree, doesn't need SunburstPageTemplate's escaping.
        $d3Source = file_get_contents($this->d3SourcePath);
        $templateContents = str_replace(self::D3_SOURCE_PLACEHOLDER, $d3Source, $templateContents);

        $html = $this->pageTemplate->render($templateContents, $tree);

        $filePath = tempnam(sys_get_temp_dir(), "sunburstfs-");
        file_put_contents($filePath, $html);
        // download.php runs as the web server user, not omv-engined's.
        chmod($filePath, 0644);

        return $filePath;
    }
}
