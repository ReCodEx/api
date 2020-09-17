<?php

namespace App\Helpers\FileStorage;

use App\Helpers\TmpFilesHelper;
use Nette\Utils\Arrays;
use Nette\SmartObject;
use ZipArchive;
use Exception;

/**
 * Simplified implementation of file storage over single ZIP file.
 * It does not support nested ZIP files.
 */
class ZipFileStorage implements IFileStorage
{
    use SmartObject;

    /**
     * @var TmpFilesHelper
     */
    protected $tmpFilesHelper;

    /**
     * @var string
     * Path to the actual ZIP file.
     */
    protected $archivePath;

    /**
     * @var string|null
     * Path to ZIP file within external storage, null if the ZIP file stands alone.
     */
    protected $archiveStoragePath;

    /**
     * @var ZipArchive|null
     * Reference to open zip archive object.
     */
    protected $zip = null;

    private function openZipArchive($flags = 0): void
    {
        $res = $this->zip->open($this->archivePath, $flags);
        if ($res !== true) {
            throw new FileStorageException("Unable to open ZIP archive (error code $res)", $this->archivePath);
        }
    }

    /**
     * Close the internal zip archive (propagate changes immediately).
     */
    private function closeZipArchive(): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        if (!$this->zip->close()) {
            throw new FileStorageException(
                "The archive did not close correctly. Some modifications may have been lost.",
                $this->archivePath
            );
        }
        touch($this->archivePath); // make sure the archive exists even if empty
    }

    /**
     * Constructor
     * @param string $archivePath path to the actual ZIP file
     * @param string $archiveStoragePath path to ZIP file within external storage (null if standalone)
     * @param bool $overwrite whether the OVERWRITE flag should be set when opening the archive
     */
    public function __construct(TmpFilesHelper $tmpFilesHelper, string $archivePath, string $archiveStoragePath = null, bool $overwrite = false)
    {
        $this->tmpFilesHelper = $tmpFilesHelper;
        $this->archivePath = $archivePath;
        $this->archiveStoragePath = $archiveStoragePath;
        $this->zip = new ZipArchive();
        $this->openZipArchive(ZipArchive::CREATE | ($overwrite ? ZipArchive::OVERWRITE : 0));
    }

    /**
     * Close the underlying ZIP object. Most methods will fail after the archive is closed.
     */
    public function close()
    {
        $this->closeZipArchive();
        $this->zip = null;
    }

    public function __destructor()
    {
        if ($this->zip) {
            $this->zip->close();
        }
    }

    /**
     * Retrieve size of given entry
     * @param ZipArchive $zip already opened ZIP archive object
     * @param string $zipArchivePath path to the opened ZIP archive (for reporting if exception is thrown)
     * @param string $entry zip file entry to be extracted
     * @return int size in bytes
     * @throws FileStorageException if the entry does not exist
     */
    public static function getZipEntrySize(ZipArchive $zip, string $zipArchivePath, string $entry): int
    {
        $stats = $zip->statName($entry);
        if (!$stats || !array_key_exists('size', $stats)) {
            throw new FileStorageException(
                "The ZIP archive is unable to give stats for entry '$entry'",
                $zipArchivePath
            );
        }
        return $stats['size'];
    }


    /**
     * Helper method for extracting one entry in a specific file.
     * @param ZipArchive $zip already opened ZIP archive object
     * @param string $zipArchivePath path to the opened ZIP archive (for reporting if exception is thrown)
     * @param string $entry zip file entry to be extracted
     * @param string $dstLocalPath target path where the file will be extracted
     */
    public static function extractZipEntryToFile(
        ZipArchive $zip,
        string $zipArchivePath,
        string $entry,
        string $dstLocalPath
    ): void {
        // open entry stream for reading
        $fpIn = $zip->getStream($entry);
        if (!$fpIn) {
            throw new FileStorageException(
                "The ZIP archive is unable to open stream for entry '$entry'",
                $zipArchivePath
            );
        }

        // open target file for writing
        $fpOut = fopen($dstLocalPath, "wb");
        if (!$fpOut) {
            fclose($fpIn);
            throw new FileStorageException("Unable to open target file for writing.", $dstLocalPath);
        }

        try {
            // copy the file by 1MiB chunks
            $copied = 0;
            $size = self::getZipEntrySize($zip, $zipArchivePath, $entry);
            while ($copied < $size && !feof($fpIn)) {
                $buf = fread($fpIn, 1024 * 1024);
                if (!$buf) {
                    throw new FileStorageException("Reading of ZIP entry '$entry' stream failed.", $zipArchivePath);
                }

                $copied += strlen($buf);
                if (fwrite($fpOut, $buf) !== strlen($buf)) {
                    throw new FileStorageException("Writing to target file failed.", $dstLocalPath);
                }
            }

            // check the entire file has been copied
            if ($copied !== $size || !feof($fpIn)) {
                throw new FileStorageException(
                    "Extraction of '$entry' from ZIP archive was not completed entirely.",
                    $zipArchivePath
                );
            }
        } finally {
            fclose($fpIn);
            fclose($fpOut);
        }
    }

    /**
     * Extract given entry and return its complete contents.
     * @param string $entry zip file entry to be extracted
     * @return string file contents
     */
    public function extractToString(string $entry): string
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        return $this->zip->getFromName($entry);
    }

    /**
     * Extract given entry into target file. The file is overwritten if exists.
     * @param string $entry zip file entry to be extracted
     * @param string $dstLocalPath target path where the file will be extracted
     */
    public function extractToFile(string $entry, string $dstLocalPath): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        self::extractZipEntryToFile($this->zip, $this->archivePath, $entry, $dstLocalPath);
    }


    /*
     * IFileStorage
     */

    public function fetch(string $path): ?IImmutableFile
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        if (!$this->zip->statName($path)) {
            return null;
        }
        return new ArchivedImmutableFile($this->archivePath, $path, ($this->archiveStoragePath ?? '') . '#' . $path);
    }

    public function fetchOrThrow(string $path): IImmutableFile
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        $file = $this->fetch($path);
        if (!$file) {
            throw new FileStorageException("File not found within the storage.", $path);
        }
        return $file;
    }

    public function storeFile(string $localPath, string $storagePath, bool $move = true, bool $overwrite = false): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }

        // make sure input file exists and is readable
        if (!file_exists($localPath) || !is_file($localPath)) {
            throw new FileStorageException("Given local file not found.", $localPath);
        }

        if (!is_readable($localPath)) {
            throw new FileStorageException("Given file is not accessible for reading.", $localPath);
        }

        // TODO: PHP 8.0 introduced addFile() flags (especially ZipArchive::FL_OVERWRITE), which may replace this.
        if ($overwrite) {
            $this->zip->deleteName($storagePath);
        } elseif ($this->zip->statName($storagePath)) {
            throw new FileStorageException("Target entry already exists.", $storagePath);
        }

        if (!$this->zip->addFile($localPath, $storagePath)) {
            throw new FileStorageException("Unable to add file into ZIP archive.", $localPath);
        }
        if ($move) {
            $this->flush(); // we need to save the file before we unlink it
            unlink($localPath); // cannot actually move, but we can delete afterwards
        }
    }

    public function storeContents($contents, string $storagePath, bool $overwrite = false): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }

        // TODO: PHP 8.0 introduced addFile() flags (especially ZipArchive::FL_OVERWRITE), which may replace this.
        if ($overwrite) {
            $this->zip->deleteName($storagePath);
        } elseif ($this->zip->statName($storagePath)) {
            throw new FileStorageException("Target entry already exists.", $storagePath);
        }

        if (!$this->zip->addFromString($storagePath, $contents)) {
            throw new FileStorageException("Unable to add contents into ZIP archive.", $this->archivePath);
        }
    }

    /**
     * At present, the getStream() method of ZipArchive only supports reading,
     * so we store the stream in tmp file and then use storeFile().
     * In the future, this implementation may be optimized.
     */
    public function storeStream($stream, string $storagePath, bool $overwrite = false): void
    {
        $tmpPath = $this->tmpFilesHelper->createTmpFile("rexzip");
        $fp = fopen($tmpPath, "wb");
        if (!$fp) {
            throw new FileStorageException("Unable to open tmp file for writing.", $tmpPath);
        }

        if (stream_copy_to_stream($stream, $fp) === false || !fclose($fp)) {
            throw new FileStorageException(
                "Copying stream data into tmp file failed.",
                $tmpPath
            );
        }

        try {
            $this->storeFile($tmpPath, $storagePath, false, $overwrite);
            $this->flush();
        } finally {
            unlink($tmpPath);
        }
    }

    /**
     * Copying is perhaps the most complex procedure, since it has no direct support from ZIP archive.
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        if ($src === $dst) {
            throw new FileStorageException(
                "Unable to create copy of a file within the ZIP archive (src and dst paths are identical).",
                $this->archivePath
            );
        }

        if (!$overwrite && $this->zip->statName($dst)) {
            throw new FileStorageException(
                "Unable to copy file to '$dst', target entry already exists.",
                $this->archivePath
            );
        }

        if (self::getZipEntrySize($this->zip, $this->archivePath, $src) < 4096 * 1024) {
            // load data in memory to make a copy
            $contents = $this->extractToString($src);
            $this->storeContents($contents, $dst, $overwrite);
        } else {
            // fallback to copy via file system
            $tmpFile = $this->tmpFilesHelper->createTmpFile("rexzip");
            try {
                $this->extractToFile($src, $tmpFile);
                $this->storeFile($tmpFile, $dst, $overwrite);
            } finally {
                unlink($tmpFile);
            }
        }
    }

    public function move(string $src, string $dst, bool $overwrite = false): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        if ($src === $dst) {
            return; // nothing to be done
        }
        if ($overwrite) {
            $this->zip->deleteName($dst);
        }
        if (!$this->zip->renameName($src, $dst)) {
            throw new FileStorageException(
                "Unable to rename an entry '$src' to '$dst' in the ZIP archive.",
                $this->archivePath
            );
        }
    }

    public function extract(string $storagePath, string $localPath, bool $overwrite = false): void
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }

        if (!$overwrite && file_exists($localPath)) {
            throw new FileStorageException("Target file exists.", $localPath);
        }

        self::extractZipEntryToFile($this->zip, $this->archivePath, $storagePath, $localPath);
        $this->delete($storagePath);
    }

    public function delete(string $path): bool
    {
        if (!$this->zip) {
            throw new FileStorageException("The ZIP archive has already been closed.", $this->archivePath);
        }
        return $this->zip->deleteName($path);
    }

    public function flush(): void
    {
        $this->closeZipArchive();
        $this->openZipArchive();
    }
}
