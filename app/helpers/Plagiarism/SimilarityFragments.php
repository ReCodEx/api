<?php

namespace App\Helpers\Plagiarism;

use App\Exceptions\ParseException;
use JsonSerializable;

/**
 * Helper that handles validation and compression of similarity fragments structue.
 * The structure holds an array of fragments, each fragment is a pair (array with 2 items)
 * of references (first one is tested file, second one is another file with detected similarities).
 * The reference is an object (encoded as associative array) with 'offset' and 'length' in human-friendly
 * version and 'o', 'l' in compressed version.
 */
class SimilarityFragments implements JsonSerializable
{
    private $fragments = [];

    /**
     * Get non-negative int from an array trying various keys.
     * @param array $array
     * @return int|null null if no such value was found (trying all the keys)
     */
    private static function getPositiveInt(array $array, ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array) && is_int($array[$key]) && $array[$key] >= 0) {
                return $array[$key];
            }
        }
        return null;
    }

    /**
     * Parse a fragment reference (structure with offset and length).
     * @param mixed $ref structure to be validated
     * @param int $index of the fragment (so it can be reported in an exception on failure)
     * @return array structure with 'o' and 'l' keys (compressed version)
     */
    private static function loadFragmentRef(mixed $ref, int $index): array
    {
        if (!is_array($ref) || count($ref) !== 2) {
            throw new ParseException(
                "Reference (in fragment #$index) parsing failed. It should be a structure with offset and length."
            );
        }

        $offset = self::getPositiveInt($ref, 'o', 'offset');
        $length = self::getPositiveInt($ref, 'l', 'length');
        if ($offset === null || $length === null) {
            throw new ParseException(
                "Reference (in fragment #$index) parsing failed. It should be a structure with offset and length."
            );
        }

        return [ 'o' => $offset, 'l' => $length ]; // compressed version
    }

    /**
     * Verify and process a fragment (a pair of references).
     * @param mixed $fragment to be parsed
     * @param int $index of the fragment (so it can be reported in an exception on failure)
     * @return array normalized compressed version of the fragment (array with exactly 2 references)
     */
    private static function loadFragment(mixed $fragment, int $index): array
    {
        if (!is_array($fragment) || count($fragment) !== 2) {
            throw new ParseException(
                "Fragment #$index parsing failed. The fragment is expected to be a pair of references."
            );
        }

        return array_map(function ($ref) use ($index) {
            return self::loadFragmentRef($ref, $index);
        }, array_values($fragment));
    }

    /**
     * Verify and load given parsed json structure.
     * Accepts both human-friendly and compressed notations, converts the data to compressed.
     * @param array $fragments parsed JSON structure
     * @throws ParseException if the input validation fails
     */
    public function load(array $fragments): void
    {
        $this->fragments = [];
        foreach ($fragments as $fragment) {
            $this->fragments[] = self::loadFragment($fragment, count($this->fragments) + 1);
        }
    }

    public function jsonSerialize(): mixed
    {
        return $this->fragments;
    }
}
