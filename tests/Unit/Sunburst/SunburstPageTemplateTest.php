<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Unit\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\SunburstPageTemplate;

/**
 * The one piece of real logic in the HTML-page rendering path: injecting
 * the tree JSON into a static template. Everything downstream of this
 * (D3 layout, color, labels) lives in the vendored/adapter JS and isn't
 * ours to unit test -- same reasoning GD rendering was never unit
 * tested, just verified live. This class is small, but it's the one
 * spot where a real bug (breaking out of the <script> context via a
 * directory name) is both plausible and cheap to guard against, so it
 * earns a real test.
 */
final class SunburstPageTemplateTest extends TestCase
{
    public function testInjectsJsonDataAtThePlaceholder(): void
    {
        $template = new SunburstPageTemplate();
        $tree = ["name" => "root", "size" => 100, "type" => "dir", "children" => []];

        $rendered = $template->render("before __SUNBURST_DATA__ after", $tree);

        self::assertSame(
            'before {"name":"root","size":100,"type":"dir","children":[]} after',
            $rendered
        );
    }

    public function testThrowsWhenTemplateIsMissingThePlaceholder(): void
    {
        $template = new SunburstPageTemplate();

        $this->expectException(\InvalidArgumentException::class);

        $template->render("no placeholder here", ["name" => "root", "size" => 0, "type" => "dir", "children" => []]);
    }

    public function testANodeNameContainingAScriptCloseTagCannotBreakOutOfTheDataScriptBlock(): void
    {
        $template = new SunburstPageTemplate();
        $hostileName = "</script><script>alert(1)</script>";
        $tree = ["name" => $hostileName, "size" => 1, "type" => "dir", "children" => []];

        // A realistic slice of the real template: the placeholder sits
        // inside its own <script> block, same as the real one will.
        $rendered = $template->render(
            "<script>const data = __SUNBURST_DATA__;</script><script>renderChart(data);</script>",
            $tree
        );

        // The only two </script> tags in the output should be the two
        // this test itself wrote -- none contributed by the hostile
        // directory name breaking out of the data block.
        self::assertSame(2, substr_count($rendered, "</script>"));
        self::assertStringNotContainsString($hostileName, $rendered);
    }

    public function testAmpersandInANameIsAlsoEscapedSafely(): void
    {
        $template = new SunburstPageTemplate();
        $tree = ["name" => "Q&A archive", "size" => 1, "type" => "dir", "children" => []];

        $rendered = $template->render("__SUNBURST_DATA__", $tree);

        // The raw '&' must not survive unescaped (JSON_HEX_AMP). Derive
        // the expected escape sequence from json_encode itself rather
        // than hand-typing it, so the test can't drift from whatever
        // PHP's own escaping actually produces.
        $escapedAmpersand = trim(json_encode("&", JSON_HEX_AMP), '"');
        self::assertStringNotContainsString("Q&A archive", $rendered);
        self::assertStringContainsString("Q{$escapedAmpersand}A archive", $rendered);
    }
}
