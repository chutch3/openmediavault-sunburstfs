<?php
declare(strict_types=1);

namespace Sunburstfs;

/**
 * Injects a tree into the static chart HTML template. Pure string
 * handling, no filesystem access -- reading the template file itself
 * and writing the rendered result out are left to
 * {@see SunburstPageGenerator}, the same split GD's writePng() had from
 * the pure geometry that fed it.
 *
 * JSON_HEX_TAG + JSON_HEX_AMP are load-bearing, not decorative: a real
 * directory name is untrusted input that ends up inside a <script>
 * block, so '<', '>', and '&' must never survive unescaped -- otherwise
 * a directory literally named e.g. "</script><script>..." could break
 * out of the data block. json_encode()'s default slash-escaping already
 * covers "</script>" specifically; the HEX flags cover the general case.
 */
final class SunburstPageTemplate
{
    private const DATA_PLACEHOLDER = "__SUNBURST_DATA__";

    public function render(string $templateContents, array $tree): string
    {
        if (!str_contains($templateContents, self::DATA_PLACEHOLDER)) {
            throw new \InvalidArgumentException(sprintf(
                "Template is missing the required '%s' placeholder.",
                self::DATA_PLACEHOLDER
            ));
        }

        $json = json_encode($tree, JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);

        return str_replace(self::DATA_PLACEHOLDER, $json, $templateContents);
    }
}
