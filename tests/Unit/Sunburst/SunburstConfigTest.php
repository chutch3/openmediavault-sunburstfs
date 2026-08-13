<?php
declare(strict_types=1);

namespace Sunburstfs\Tests\Unit\Sunburst;

use PHPUnit\Framework\TestCase;
use Sunburstfs\SunburstConfig;

/**
 * cachePathFor() is the single source of truth linking two things that
 * would otherwise drift silently: the extension the refresh script
 * writes (see SunburstPageGenerator's caller) and the extension
 * sunburstfs.inc looks for on every request. This exact string already
 * changed once (.png -> .html) with nothing to catch a mismatch in
 * either direction -- worth pinning down explicitly now that it has.
 */
final class SunburstConfigTest extends TestCase
{
    public function testCachePathForBuildsAnHtmlFileUnderTheCacheDirectory(): void
    {
        self::assertSame(
            SunburstConfig::CACHE_DIR . "/7cb98ed6-0e9c-4e0b-8808-74d5c3c8093f.html",
            SunburstConfig::cachePathFor("7cb98ed6-0e9c-4e0b-8808-74d5c3c8093f")
        );
    }
}
