<?php

declare(strict_types=1);

/*
 * This file is part of Zenify
 * Copyright (c) 2012 Tomas Votruba (http://tomasvotruba.cz)
 */

namespace Zenify\DoctrineFixtures\Alice;

use App\Helpers\ZenifyFixtures\Alice\CustomNativeLoader;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Finder;
use SplFileInfo;
use Zenify\DoctrineFixtures\Contract\Alice\AliceLoaderInterface;
use Zenify\DoctrineFixtures\Exception\MissingSourceException;

final class AliceLoader implements AliceLoaderInterface
{

    /**
     * @var CustomNativeLoader
     */
    private $aliceLoader;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;


    public function __construct(CustomNativeLoader $aliceLoader, EntityManagerInterface $entityManager)
    {
        $this->aliceLoader = $aliceLoader;
        $this->entityManager = $entityManager;
    }


    /**
     * @param string|array $sources
     * @return object[]
     */
    public function load($sources): array
    {
        if (!is_array($sources) && is_dir($sources)) {
            $sources = $this->getFilesFromDirectory($sources);
        } elseif (!is_array($sources)) {
            $sources = [$sources];
        }

        $this->checkExistence($sources);

        $entities = $this->aliceLoader->loadFiles($sources)->getObjects();
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        return $entities;
    }

    private function checkExistence(array $sources)
    {
        foreach ($sources as $source) {
            if (!is_file($source)) {
                throw new MissingSourceException(
                    sprintf('Source "%s" was not found.', $source)
                );
            }

            if (!is_readable($source)) {
                throw new MissingSourceException(sprintf('Source "%s" is not readable', $source));
            }
        }
    }

    private function getFilesFromDirectory(string $path): array
    {
        $files = [];
        foreach (Finder::find('*.neon', '*.yaml', '*.yml')->from($path) as $file) {
            /** @var SplFileInfo $file */
            $files[] = $file->getPathname();
        }
        return $files;
    }
}
