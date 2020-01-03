<?php

namespace App\Helpers;

use Symfony\Component\Yaml\Yaml as SymfonyYaml;
use Symfony\Component\Yaml\Exception\RuntimeException as SymfonyYamlException;
use RuntimeException;

class YamlException extends RuntimeException
{
}

/**
 * A Yaml wrapper to simplify transition from Symfony/Yaml to PECL yaml package.
 * Historical note: we have switched to PECL since it is several orders of magnitude faster
 * and performance become an issue when processing MiB-sized backend result yaml files.
 *
 * Fallback to Symfony/Yaml is provided when extra formatting options are required.
 */
class Yaml
{
    public static function parseFile(string $filename, int $flags = 0)
    {
        if ($flags) {
            try {
                return SymfonyYaml::parseFile($filename, $flags);
            } catch (SymfonyYamlException $e) {
                throw new YamlException("Parsing YAML file '$filename' failed.", 0, $e);
            }
        } else {
            $res = @yaml_parse_file($filename);
            if ($res === false) {
                throw new YamlException("Parsing YAML file '$filename' failed.");
            }
            return $res;
        }
    }

    public static function parse(string $input, int $flags = 0)
    {
        if ($flags || !$input) {
            try {
                return SymfonyYaml::parse($input, $flags);
            } catch (SymfonyYamlException $e) {
                throw new YamlException("Parsing YAML string input failed.", 0, $e);
            }
        } else {
            $res = @yaml_parse($input);
            if ($res === false) {
                throw new YamlException("Parsing YAML string input failed.");
            }
            return $res;
        }
    }

    public static function dump($data, ...$options): string
    {
        if ($options) {
            try {
                return SymfonyYaml::dump($data, ...$options);
            } catch (SymfonyYamlException $e) {
                throw new YamlException("Serializing data structure in YAML failed.", 0, $e);
            }
        } else {
            return @yaml_emit($data, YAML_ANY_ENCODING, YAML_LN_BREAK);
        }
    }
}
