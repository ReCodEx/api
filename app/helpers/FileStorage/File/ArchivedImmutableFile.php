<?php

namespace App\Helpers\FileStorage;

use App\Helpers\TmpFilesHelper;
use Nette\SmartObject;
use ZipArchive;

/**
 * Abstraction that represents one immutable (read-only) file inside a ZIP archive.
 */
class ArchivedImmutableFile implements IImmutableFile
{
    use SmartObject;

    /**
     * @var string
     * Actual real path to ZIP archive on a local file system.
     */
    private $archivePath;

    /**
     * @var string
     * Path to an entry within the ZIP file
     */
    private $entry;

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
     * @var TmpFilesHelper|null
     * Injected helper for handling tmp files.
     */
    private $tmpFilesHelper = null;

    /**
     * Initialize the object
     * @param string $archivePath path to an actual ZIP file
     * @param string $entry path within the ZIP file
     * @param string|null $storagePath presented virtual path within specific storage
     *                                 (if null, it is constructed as archivePath#entry)
     */
    public function __construct(
        string $archivePath,
        string $entry,
        string $storagePath = null,
        TmpFilesHelper $tmpFilesHelper = null
    ) {
        $this->archivePath = $archivePath;
        $this->entry = $entry;
        $this->storagePath = $storagePath ?? "$archivePath#$entry";
        $this->tmpFilesHelper = $tmpFilesHelper;
    }

    /**
     * Creates and opens corresponding ZIP archive as read-only.
     * @return ZipArchive
     */
    private function openZip()
    {
        $zip = new ZipArchive();
        // TODO: ZipArchive::RDONLY flag would be nice here, but it requires PHP 7.4.3+
        $res = $zip->open($this->archivePath);
        if ($res !== true) {
            throw new FileStorageException("Unable to open ZIP archive (code $res).", $this->archivePath);
        }
        return $zip;
    }

    /*
     * IImmutableFile
     */

    public function getName(): string
    {
        return basename($this->entry);
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    private function loadInternalStats(): void
    {
        if ($this->fileSize === null || $this->fileTime === null) {
            $zip = $this->openZip();
            $stats = $zip->statName($this->entry);
            if (!$stats || !array_key_exists('size', $stats) || !array_key_exists('mtime', $stats)) {
                throw new FileStorageException(
                    "The ZIP archive is unable to give stats for entry '$this->entry'",
                    $this->archivePath
                );
            }

            $this->fileSize = (int)$stats['size'];
            $this->fileTime = (int)$stats['mtime'];
            $zip->close();
        }
    }

    public function getSize(): int
    {
        $this->loadInternalStats();
        return $this->fileSize;
    }

    public function getTime(): int
    {
        $this->loadInternalStats();
        return $this->fileTime;
    }

    public function getContents(int $sizeLimit = 0): string
    {
        $zip = $this->openZip();
        $contents = $zip->getFromName($this->entry, $sizeLimit);
        $zip->close();

        if ($contents === false) {
            throw new FileStorageException(
                "The ZIP archive is unable to retrive contents of entry '$this->entry'",
                $this->archivePath
            );
        }

        return $contents;
    }

    public function getDigest(string $algorithm = IImmutableFile::DIGEST_ALGORITHM_SHA1): ?string
    {
        if ($algorithm === IImmutableFile::DIGEST_ALGORITHM_SHA1) {
            $contents = $this->getContents();
            return sha1($contents);
        }

        return null; // algorithm not implemented
    }

    public function isZipArchive(): bool
    {
        $sourceZip = $this->openZip();
        $path = $this->tmpFilesHelper->createTmpFile('aif');
        ZipFileStorage::extractZipEntryToFile($sourceZip, $this->archivePath, $this->entry, $path);

        $zip = new ZipArchive();
        // TODO: ZipArchive::RDONLY flag would be nice here, but it requires PHP 7.4.3+
        $res = $zip->open($path);
        if ($res === true) {
            $zip->close();
        }
        return $res === true;
    }

    public function getZipEntries(): array
    {
        throw new FileStorageException("Nested ZIP files cannot be listed for entries.", $this->archivePath);
    }

    public function saveAs(string $path): void
    {
        $zip = $this->openZip();
        ZipFileStorage::extractZipEntryToFile($zip, $this->archivePath, $this->entry, $path);
    }

    public function addToZip(ZipArchive $zip, string $entryName): void
    {
        $sourceZip = $this->openZip();
        $path = $this->tmpFilesHelper->createTmpFile('aif');
        ZipFileStorage::extractZipEntryToFile($sourceZip, $this->archivePath, $this->entry, $path);
        if (!$zip->addFile($path, $entryName)) {
            $src = $this->archivePath . '#' . $this->entry;
            throw new FileStorageException("Error while adding immutable file to ZIP archive.", $src);
        }
    }

    public function passthru(): void
    {
        $zip = $this->openZip();
        $fp = $zip->getStream($this->entry);

        if ($fp === false) {
            $zip->close();
            throw new FileStorageException(
                "The ZIP archive is unable to provide readable stream for entry '$this->entry'",
                $this->archivePath
            );
        }

        // if output buffering is active, turn it off so the file goes directly to output
        if (ob_get_level()) {
            ob_end_clean();
        }

        fpassthru($fp);

        fclose($fp);
        $zip->close();
    }
}
