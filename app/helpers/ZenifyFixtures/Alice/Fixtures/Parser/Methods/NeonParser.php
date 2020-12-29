<?php

declare(strict_types=1);

/*
 * This file is part of Zenify
 * Copyright (c) 2012 Tomas Votruba (http://tomasvotruba.cz)
 */

namespace Zenify\DoctrineFixtures\Alice\Fixtures\Parser\Methods;

use Exception;
use Nelmio\Alice\Parser\ChainableParserInterface;
use Nelmio\Alice\Throwable\Exception\InvalidArgumentExceptionFactory;
use Nelmio\Alice\Throwable\Exception\Parser\ParseExceptionFactory;
use Nelmio\Alice\Throwable\Exception\Parser\UnparsableFileException;
use Nette\Neon\Neon;


final class NeonParser implements ChainableParserInterface
{
    private const REGEX = '/.{1,}\.neon/i';

    /**
     * @inheritDoc
     */
    public function canParse(string $file): bool
    {
        if (false === stream_is_local($file)) {
            return false;
        }

        return 1 === preg_match(self::REGEX, $file);
    }

    /**
     * @inheritDoc
     */
    public function parse($file): array
    {
        if (false === is_file($file)) {
            throw InvalidArgumentExceptionFactory::createForFileCouldNotBeFound($file);
        }

        try {
            $data = Neon::decode(file_get_contents($file));

            if (null === $data) {
                throw new UnparsableFileException(sprintf('The file "%s" does not contain valid NEON.', $file));
            }

            return $data;
        } catch (Exception $exception) {
            if ($exception instanceof UnparsableFileException) {
                throw $exception;
            }

            throw ParseExceptionFactory::createForUnparsableFile($file, 0, $exception);
        }
    }
}
