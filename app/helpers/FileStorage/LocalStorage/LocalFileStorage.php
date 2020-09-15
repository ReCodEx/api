<?php

namespace App\Helpers\FileStorage;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nette\SmartObject;
use ZipArchive;

/**
 * File storage that stores immutable files organized in sub-directories.
 * The storage also allows direct ZIP archive dereference (path/to/file.zip#zip).
 * The ZIP archive access allows only one-level of dereference (i.e., not accessing archives within archives).
 *
 * Furthermore, there are no explicit methods for managing directories. Directories (as well as archives) are
 * automatically created as needed (e.g., when file is being stored) and removed when empty (removed are only
 * directories, not archives).
 *
 * The local archive must be entirely on one partition, so the files may be moved by rename function.
 */
class LocalFileStorage implements IFileStorage
{
    use SmartObject;

    protected $rootDirectory;

    public function getRootDirectory(): string
    {
        return $this->rootDirectory;
    }

    /**
     * Constructor
     * @param array $params initial configuration
     */
    public function __construct(array $params = [])
    {
        $this->rootDirectory = Arrays::get($params, "root", null);
        if (!$this->rootDirectory || !is_dir($this->rootDirectory)) {
            throw new FileStorageException(
                "Specified file storage root must be an existing directory.",
                $this->rootDirectory
            );
        }
    }

    /**
     * Verify and normalize relative storage path. The path must not contain '..' as a substring.
     * @param string $path to normalize
     * @return string normalized path
     * @throws FileStorageException if the path is invalid
     */
    private static function normalizePath(string $path): string
    {
        if (Strings::contains($path, '..')) {
            throw new FileStorageException("Substring '..' must not be present in any path.", $path);
        }
        $path = preg_replace('@/[.]/@', '/', $path);
        $path = preg_replace('@[/\\\\]+@', '/', $path);
        $path = preg_replace('@(^[.]/)|(/[.]?$)@', '', $path);
        return $path;
    }

    /**
     * Decode a storage path.
     * @param string $path path is also normalized (that is why it is passed by reference)
     * @param bool|null $exists If true/false, (non)existence checks are peformed, null = no checks.
     *                          Checks are performed on real path only (not inside ZIP archives).
     * @param bool $mkdir whether to make sure all underlying sub-directories exist (mkdir if necessary)
     * @return array a tuple containing real path (first) and ZIP file entry or null (second)
     */
    private function decodePath(string &$path, bool $exists = null, $mkdir = false): array
    {
        $path = self::normalizePath($path);

        $tokens = explode('#', $path, 2);
        array_push($tokens, null); // make sure second item always exists
        [$realPath, $zipEntry] = $tokens;

        $realPath = $this->rootDirectory . '/' . $realPath;
        if (is_dir($realPath)) {
            throw new FileStorageException("Given path refers to a directory.", $path);
        }

        if ($exists === true) {
            // checking the real path exists (not the zip entry)
            if (!file_exists($realPath) || !is_file($realPath)) {
                throw new FileStorageException("File not found within the storage.", $path);
            }
        }

        if ($exists === false && !$zipEntry) {
            // check the file does not exist (skipped for zip entries, since we are not openning the ZIP archive here)
            if (file_exists($realPath)) {
                throw new FileStorageException("File already exists.", $path);
            }
        }

        if ($mkdir) {
            $dir = dirname($realPath);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0775, true)) {
                    throw new FileStorageException("Unable to create a directory path.", $dir);
                }
            }
        }

        return [$realPath, $zipEntry];
    }

    /**
     * Verify that given directory is empty and remove it.
     * Works recursively up to storage root.
     * @param string $path relative (storage) path to the directory.
     */
    private function removeEmptyDirectory(string $path): void
    {
        if (!$path || $path === '.') {
            return;
        }

        $realPath = $this->rootDirectory . '/' . $path;
        if (is_dir($realPath)) {
            $dh = opendir($realPath);
            if (!$dh) {
                return; // something is very odd, this should have worked
            }

            // check emptiness
            while (($file = readdir($dh)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                return; // we found at least one valid entry -> cannot delete
            }

            // It is empty, let's proceed!
            if (rmdir($realPath) && Strings::contains($path, '/')) {
                $this->removeEmptyDirectory(dirname($path));
            }
        }
    }

    /*
     * IFileStorage
     */

    public function fetch(string $path): ?IImmutableFile
    {
        [$realPath, $zipEntry] = $this->decodePath($path);
        if (!file_exists($realPath) || !is_file($realPath)) {
            return null;
        }
        if ($zipEntry) {
            $zip = new ZipArchive();
            if ($zip->open($realPath) !== true || !$zip->statName($zipEntry)) {
                $zip->close();
                return null;
            }
            return new ArchivedImmutableFile($realPath, $zipEntry, $path);
        }
        return new LocalImmutableFile($realPath, $path);
    }

    public function fetchOrThrow(string $path): IImmutableFile
    {
        $file = $this->fetch($path);
        if (!$file) {
            throw new FileStorageException("File not found within the storage.", $path);
        }
        return $file;
    }

    public function storeFile(string $localPath, string $storagePath, bool $move = true, bool $overwrite = false): void
    {
        // make sure input file exists and is readable
        if (!file_exists($localPath) || !is_file($localPath)) {
            throw new FileStorageException("Given local file not found.", $localPath);
        }

        if (!is_readable($localPath)) {
            throw new FileStorageException("Given file is not accessible for reading.", $localPath);
        }

        [$realPath, $zipEntry] = $this->decodePath(
            $storagePath,
            $overwrite ? null : false, // no overwrite -> not exist check
            true // construct directory path
        );

        if (!$zipEntry) {
            // saving an actual local file
            if (file_exists($realPath) && $overwrite) {
                unlink($realPath);
            }

            if (!$move || !rename($localPath, $realPath)) { // rename fails -> fallback to copying
                if (!copy($localPath, $realPath)) {
                    throw new FileStorageException(
                        "Unable to add given file into file storage as '$storagePath'.",
                        $localPath
                    );
                }
                if ($move) {
                    unlink($localPath); // copy used to simulated move -> delete original
                }
            }
        } else {
            // saving a ZIP entry
            $zip = new ZipFileStorage($realPath, null, false);
            $zip->storeFile($localPath, $zipEntry, $move, $overwrite);
            $zip->close();
        }
    }

    public function storeContents($contents, string $storagePath, bool $overwrite = false): void
    {
        [$realPath, $zipEntry] = $this->decodePath(
            $storagePath,
            $overwrite ? null : false, // no overwrite -> not exist check
            true // construct directory path
        );

        if (!$zipEntry) {
            if (file_put_contents($realPath, $contents) === false) {
                throw new FileStorageException(
                    "Unable to save data as file in the storage.",
                    $storagePath
                );
            }
        } else {
            // saving a ZIP entry
            $zip = new ZipFileStorage($realPath, null, false);
            $zip->storeContents($contents, $zipEntry, $overwrite);
            $zip->close();
        }
    }

    public function storeStream($stream, string $storagePath, bool $overwrite = false): void
    {
        [$realPath, $zipEntry] = $this->decodePath(
            $storagePath,
            $overwrite ? null : false, // no overwrite -> not exist check
            true // construct directory path
        );

        if (!$zipEntry) {
            $fp = fopen($realPath, "wb");
            if (!$fp) {
                throw new FileStorageException(
                    "Unable to open target file in the storage for writing.",
                    $storagePath
                );
            }

            if (stream_copy_to_stream($stream, $fp) === false || !fclose($fp)) {
                throw new FileStorageException(
                    "Copying stream data into target file failed.",
                    $storagePath
                );
            }
        } else {
            // saving a ZIP entry
            $zip = new ZipFileStorage($realPath, null, false);
            $zip->storeStream($stream, $zipEntry, $overwrite);
            $zip->close();
        }
    }

    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        [$srcReal, $srcZip] = $this->decodePath($src, true); // true = check exists
        [$dstReal, $dstZip] = $this->decodePath(
            $dst,
            $overwrite ? null : false, // no overwrite -> not exist check
            true // construct directory path
        );

        if ($srcReal === $dstReal && $srcZip === $dstZip) {
            throw new FileStorageException("Unable to copy file to itself.", $src);
        }
        
        if ($srcReal === $dstReal && ($srcZip || $dstZip) && (!$srcZip || !$dstZip)) {
            throw new FileStorageException(
                "Unable to manipulate with ZIP archive and its contents in one copy procedure.",
                $src
            );
        }
        
        if ($srcZip && $dstZip && $srcReal === $dstReal) {
            // copy witin one archive -> use ZipFileStorage implementation
            $zip = new ZipFileStorage($srcReal, null, false);
            $zip->copy($srcZip, $dstZip, $overwrite);
            $zip->close();
        } elseif ($srcZip || $dstZip) {
            // source or destination (or both) are ZIP archives -> fetch & store
            $srcFile = $this->fetch($src);
            if ($srcFile === null) {
                throw new FileStorageException("File not found within the storage.", $src);
            }

            if ($srcFile->getSize() < 4096 * 1024) {
                // copy via memory
                $contents = $srcFile->getContents();
                $this->storeContents($contents, $dst, $overwrite);
            } else {
                // fallback to tmp file
                $tmpFile = tempnam(sys_get_temp_dir(), "recodex");
                try {
                    $srcFile->saveAs($tmpFile);
                    $this->storeFile($tmpFile, $dst, $overwrite);
                } finally {
                    unlink($tmpFile);
                }
            }
        } else {
            // copy regular file within the storage (using internal copy() function)
            if (file_exists($dstReal) && $overwrite) { // file exists and not overwrite is handled in decodePath
                if (!unlink($dstReal)) {
                    throw new FileStorageException("Unable to overwrite target file.", $dst);
                }
            }
            if (!copy($srcReal, $dstReal)) {
                throw new FileStorageException("Copying failed.", $src);
            }
        }
    }

    public function move(string $src, string $dst, bool $overwrite = false): void
    {
        [$srcReal, $srcZip] = $this->decodePath($src, true); // true = check exists
        [$dstReal, $dstZip] = $this->decodePath(
            $dst,
            $overwrite ? null : false, // no overwrite -> not exist check
            true // construct directory path
        );

        if ($srcReal === $dstReal && $srcZip === $dstZip) {
            return; // nothing to move
        }
        
        if ($srcReal === $dstReal && ($srcZip || $dstZip) && (!$srcZip || !$dstZip)) {
            throw new FileStorageException(
                "Unable to manipulate with ZIP archive and its contents in one move procedure.",
                $src
            );
        }

        if ($srcZip && $dstZip && $srcReal === $dstReal) {
            // move witin one archive
            $zip = new ZipFileStorage($srcReal, null, false);
            $zip->move($srcZip, $dstZip, $overwrite);
            $zip->close();
        } elseif ($srcZip || $dstZip) {
            // move with some archive involvment, but not simply in one archive -> fallback to copy
            $this->copy($src, $dst, $overwrite);
            if (!$this->delete($src)) {
                throw new FileStorageException("Unable to remove source file after copy-moving procedure.", $src);
            }
        } else {
            // move regular file within the storage (using internal rename() with fallback to copy function)
            if (file_exists($dstReal) && $overwrite) { // file exists and not overwrite is handled in decodePath
                if (!unlink($dstReal)) {
                    throw new FileStorageException("Unable to overwrite target file.", $dst);
                }
            }
            if (!rename($srcReal, $dstReal)) {
                throw new FileStorageException("Moving failed.", $src);
            }
        }
    }

    public function delete(string $path): bool
    {
        [$realPath, $zipEntry] = $this->decodePath($path);

        if (!$zipEntry) {
            // removing actual file
            if (!file_exists($realPath)) {
                return false;
            }
            if (!unlink($realPath)) {
                throw new FileStorageException("Unable to delete file in storage.", $path);
            }
            if (Strings::contains($path, '/')) {
                // removing unnecessary empty directories
                $this->removeEmptyDirectory(dirname($path));
            }
            return true;
        } else {
            // removing a ZIP entry
            $zip = new ZipFileStorage($realPath, null, false);
            $res = $zip->delete($zipEntry);
            $zip->close();
            return $res;
        }
    }

    public function flush(): void
    {
        // local fs does not require flush, all changes are made immediately
    }
}
