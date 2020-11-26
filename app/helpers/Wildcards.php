<?php

namespace App\Helpers;

use Generator;
use Nette\StaticClass;

class Wildcards
{
    use StaticClass;

    /**
     * Check if given string matches a shell-like wildcard expression
     * @param string $wildcard the expression
     * @param string $string the string to check
     * @return bool
     */
    public static function match(string $wildcard, string $string): bool
    {
        foreach (static::expandPattern($wildcard) as $pattern) {
            if (fnmatch($pattern, $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Expand a wildcard expression containing curly braces into a sequence of strings
     * @param string $pattern
     * @return Generator
     */
    public static function expandPattern($pattern)
    {
        $offset = 0;
        $len = strlen($pattern);

        if ($len === 0) {
            yield "";
        }

        while ($offset < $len) {
            $start = strpos($pattern, "{", $offset);

            if ($start === false) {
                if ($offset === 0) {
                    yield $pattern;
                }
                break;
            } else {
                $counter = 0;

                for ($i = $start; $i < $len; $i++) {
                    if ($pattern[$i] === "{") {
                        $counter += 1;
                    }
                    if ($pattern[$i] === "}") {
                        $counter -= 1;

                        if ($counter === 0) {
                            $end = $offset = $i;
                            $head = substr($pattern, 0, $start);
                            $tail = substr($pattern, $end + 1);
                            $subPatterns = static::splitPattern(substr($pattern, $start + 1, $end - $start - 1));

                            foreach ($subPatterns as $subPattern) {
                                foreach (static::expandPattern($subPattern) as $newPattern) {
                                    foreach (static::expandPattern($tail) as $tailExpansion) {
                                        yield $head . $newPattern . $tailExpansion;
                                    }
                                }
                            }

                            break 2;
                        }
                    }
                }
            }
        }
    }

    /**
     * Split a comma-separated pattern into top-level parts (i.e. split only on commas that are not inside curly brackets)
     * @param string $pattern the pattern to split (not enclosed in curly braces)
     * @return Generator
     */
    public static function splitPattern($pattern)
    {
        $start = 0;
        $len = strlen($pattern);
        $counter = 0;

        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] === "{") {
                $counter += 1;
            }
            if ($pattern[$i] === "}") {
                $counter -= 1;
            }

            if ($counter === 0 && $pattern[$i] === ",") {
                yield substr($pattern, $start, $i - $start);
                $start = $i + 1;
            }
        }

        if ($start <= $len) {
            yield substr($pattern, $start);
        }
    }
}
