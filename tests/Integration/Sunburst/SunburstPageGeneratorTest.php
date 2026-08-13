<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Integration\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\DuDiskUsageScanner;
use Sunburstfs\SunburstPageGenerator;
use Sunburstfs\SunburstPageTemplate;
use Sunburstfs\SunburstTreeBuilder;
use Sunburstfs\TimeoutBoundedProcess;

/**
 * Outside-in wiring test: real scanner + real builder + real template
 * file, against a real fixture directory. Exists specifically to catch
 * the class of bug a set of per-class unit tests can't: a field-name or
 * wiring mismatch between the pieces (the exact kind of bug that once
 * slipped through here when a "branch" -> "siblingIndex" rename was
 * only half-completed and no test exercised the real pipeline
 * end-to-end).
 */
final class SunburstPageGeneratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = tempnam(sys_get_temp_dir(), "sunburstfs-pagegen-");
        unlink($this->root);
        mkdir($this->root);
        mkdir($this->root . "/sub1");
        file_put_contents($this->root . "/sub1/a.bin", str_repeat("a", 500));
        file_put_contents($this->root . "/root.bin", str_repeat("b", 200));
    }

    protected function tearDown(): void
    {
        exec("rm -rf " . escapeshellarg($this->root));
    }

    public function testGeneratesAnHtmlFileEmbeddingTheRealTreeAsJson(): void
    {
        $generator = new SunburstPageGenerator(
            new DuDiskUsageScanner(new TimeoutBoundedProcess(timeoutSeconds: 30)),
            new SunburstTreeBuilder(topN: 12),
            new SunburstPageTemplate(),
            maxDepth: 2,
            templatePath: __DIR__ . "/../../../usr/share/openmediavault/engined/rpc/sunburstfs/templates/chart.html",
            d3SourcePath: __DIR__ . "/../../../usr/share/openmediavault/engined/rpc/sunburstfs/templates/d3.v7.min.js"
        );

        $filePath = $generator->generate($this->root);

        self::assertFileExists($filePath);
        $html = file_get_contents($filePath);

        // Both placeholders must be gone -- proves both splices
        // actually ran, not just one, and not a plain untouched copy.
        self::assertStringNotContainsString("__SUNBURST_DATA__", $html);
        self::assertStringNotContainsString("__D3_SOURCE__", $html);
        self::assertStringContainsString("Copyright 2010-2023 Mike Bostock", $html);

        // Pull the embedded JSON back out and confirm it's the real
        // tree, not a stub or a mismatched shape -- this is what would
        // have caught the branch/siblingIndex-style wiring bug: the
        // template's JS reads specific field names (name, size, type,
        // children), so if this class ever passed a differently-shaped
        // array, this assertion (not the JS, which nothing here
        // executes) is what catches it.
        self::assertMatchesRegularExpression('/var data = (\{.+\});/', $html, "Could not find embedded data script line");
        preg_match('/var data = (\{.+\});/', $html, $matches);
        $decoded = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(basename($this->root), $decoded["name"]);
        self::assertSame(700, $decoded["size"]);
        self::assertSame("dir", $decoded["type"]);
        self::assertCount(2, $decoded["children"]);

        unlink($filePath);
    }
}
