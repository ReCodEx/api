<?php

namespace App\Helpers\FileStorage;

use Nette\SmartObject;
use ZipArchive;

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
     * @var int|null
     * Internal cache for file modification timestamp.
     */
    private $fileTime = null;

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

    public function getName(): string
    {
        return basename($this->storagePath);
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getSize(): int
    {
        $this->checkExistence();
        if ($this->fileSize === null) {
            $this->fileSize = @filesize($this->realPath);
            if ($this->fileSize === false) {
                throw new FileStorageException("Unable to retrieve file size.", $this->realPath);
            }
        }
        return (int)$this->fileSize;
    }

    public function getTime(): int
    {
        $this->checkExistence();
        if ($this->fileTime === null) {
            $this->fileTime = @filemtime($this->realPath);
            if ($this->fileTime === false) {
                throw new FileStorageException("Unable to retrieve file modification time.", $this->realPath);
            }
        }
        return (int)$this->fileTime;
    }

    public function getContents(int $sizeLimit = 0): string
    {
        $this->checkExistence();
        return $sizeLimit
            ? @file_get_contents($this->realPath, false, null, 0, $sizeLimit)
            : @file_get_contents($this->realPath);
    }

    public function getDigest(string $algorithm = IImmutableFile::DIGEST_ALGORITHM_SHA1): ?string
    {
        if ($algorithm === IImmutableFile::DIGEST_ALGORITHM_SHA1) {
            return sha1_file($this->realPath);
        }

        return null; // algorithm not implemented
    }

    public function isZipArchive(): bool
    {
        $zip = new ZipArchive();
        // TODO: ZipArchive::RDONLY flag would be nice here, but it requires PHP 7.4.3+
        $res = $zip->open($this->realPath);
        if ($res === true) {
            $zip->close();
        }
        return $res === true;
    }

    public function getZipEntries(): array
    {
        $zip = new ZipArchive();
        // TODO: ZipArchive::RDONLY flag would be nice here, but it requires PHP 7.4.3+
        $res = $zip->open($this->realPath);
        if ($res !== true) {
            throw new FileStorageException(
                "Cannot list entries from a file which is not a ZIP archive.",
                $this->realPath
            );
        }

        // extract entries using the old fashion for-loop
        $entries = [];
        $count = $zip->count();
        for ($i = 0; $i < $count; ++$i) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }
            $entries[] = [
                'name' => $stat['name'],
                'size' => $stat['size'],
            ];
        }

        $zip->close();
        return $entries;
    }

    public function saveAs(string $path): void
    {
        $this->checkExistence();
        if (!@copy($this->realPath, $path)) {
            throw new FileStorageException("Unable to save the file as '$path'.", $this->realPath);
        }
    }

    public function addToZip(ZipArchive $zip, string $entryName): void
    {
        $this->checkExistence();
        if (!$zip->addFile($this->realPath, $entryName)) {
            throw new FileStorageException("Error while adding immutable file to ZIP archive.", $this->realPath);
        }
    }

    public function passthru(): void
    {
        $this->checkExistence();

        // if output buffering is active, turn it off so the file goes directly to output
        if (ob_get_level()) {
            ob_end_clean();
        }

        @readfile($this->realPath);
    }
}
