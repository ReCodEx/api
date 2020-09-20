<?php

namespace App\Helpers\FileStorage;

use Nette\SmartObject;

/**
 * Abstraction that represents one immutable (read-only) file.
 */
class LocalImmutableFile implements IImmutableFile
{
    use SmartObject;

    /**
     * @var string
     * Actual real path on local file system.
     */
    private $realPath;

    /**
     * @var string
     * Storage path -- an identifier valid within the storage that created this object.
     */
    private $storagePath;

    /**
     * @var int|null
     * Internal cache for file size.
     */
    private $fileSize = null;

    /**
     * Initialize the object
     * @param string $realPath
     * @param string $storagePath
     */
    public function __construct(string $realPath, string $storagePath)
    {
        $this->realPath = $realPath;
        $this->storagePath = $storagePath;
    }
    
    private function checkExistence()
    {
        if (!file_exists($this->realPath) || !is_file($this->realPath)) {
            throw new FileStorageException("File does not exist or is not a regular file.", $this->realPath);
        }

        if (!is_readable($this->realPath)) {
            throw new FileStorageException("File is not readable, unable to retrieve its contents.", $this->realPath);
        }
    }

    /*
     * IImmutableFile
     */

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getSize(): int
    {
        $this->checkExistence();
        if ($this->fileSize === null) {
            $this->fileSize = filesize($this->realPath);
        }
        return $this->fileSize;
    }
    
    public function getContents(int $sizeLimit = 0): string
    {
        $this->checkExistence();
        return $sizeLimit
            ? @file_get_contents($this->realPath, false, null, 0, $sizeLimit)
            : @file_get_contents($this->realPath);
    }

    public function saveAs(string $path): void
    {
        $this->checkExistence();
        if (!copy($this->realPath, $path)) {
            throw new FileStorageException("Unable to save the file as '$path'.", $this->realPath);
        }
    }

    public function passthru(): void
    {
        $this->checkExistence();
        readfile($this->realPath);
    }
}
