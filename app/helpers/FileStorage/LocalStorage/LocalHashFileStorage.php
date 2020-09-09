<?php

namespace App\Helpers\FileStorage;

use Nette\Utils\Arrays;
use Nette\SmartObject;

/**
 * Hash storage that uses local file system (all files are stored within given directory).
 */
class LocalHashFileStorage implements IHashFileStorage
{
    use SmartObject;

    /**
     * Root directory in which the storage operates.
     */
    private $rootDirectory = null;

    /**
     * How many characters from each hash are taken as a prefix, that is used to divide files into sub-folders.
     * This technique lowers the number of entries in each directory, which might be important on some filesystems.
     * Default value is 3, which will lead to 16^3 subdirectories in root directory. That should be sufficient,
     * for most filesystems, if the number of files is in the order of 10-100 millions.
     */
    private $subdirPrefixLength;

    /**
     * Constructor
     * @param array $params initial configuration
     */
    public function __construct(array $params = [])
    {
        $this->rootDirectory = Arrays::get($params, "root", null);
        $this->subdirPrefixLength = Arrays::get($params, "prefixLength", 3);
        if (!$this->rootDirectory || !is_dir($this->rootDirectory)) {
            throw new FileStorageException(
                "Specified hash storage root must be an existing directory.",
                $this->rootDirectory
            );
        }
    }

    /**
     * Get real path on local FS where given file is located.
     * @param string $hash
     * @return string real path
     */
    private function getRealPath(string $hash): string
    {
        if (!preg_match('/^[a-fA-F0-9]+$/', $hash)) {
            throw new FileStorageException("Given file hash contains invalid characters.", $hash);
        }

        $path = $this->rootDirectory;
        if ($this->subdirPrefixLength > 0 && $this->subdirPrefixLength < strlen($hash)) {
            $path .= '/' . substr($hash, 0, $this->subdirPrefixLength);
        }
        return "$path/$hash";
    }

    /*
     * IHashFileStorage
     */

    public function fetch(string $hash): ?IImmutableFile
    {
        $path = $this->getRealPath($hash);
        if (!file_exists($path)) {
            return null;
        }
        return new LocalImmutableFile($path, $hash);
    }

    public function fetchOrThrow(string $hash): IImmutableFile
    {
        $file = $this->fetch($hash);
        if (!$file) {
            throw new FileStorageException("File hash not found.", $hash);
        }
        return $file;
    }

    public function storeFile(string $path): string
    {
        if (!file_exists($path) || !is_file($path)) {
            throw new FileStorageException("Given local file not found.", $path);
        }

        if (!is_readable($path)) {
            throw new FileStorageException("Given file is not accessible for reading.", $path);
        }

        $hash = sha1_file($path);
        $newPath = $this->getRealPath($hash);
        if (!@mkdir(dirname($newPath), 0775, true)) { // true = recursive
            throw new FileStorageException("Unable to create organizational sub-directories in hash store.", $newPath);
        }
        if (!@copy($path, $newPath)) {
            throw new FileStorageException("File copying failed.", $path);
        }
        
        return $hash;
    }

    public function storeContents($contents): string
    {
        $hash = sha1($contents);
        $newPath = $this->getRealPath($hash);
        if (!@mkdir(dirname($newPath), 0775, true)) { // true = recursive
            throw new FileStorageException("Unable to create organizational sub-directories in hash store.", $newPath);
        }
        if (file_put_contents($newPath, $contents) === false) {
            throw new FileStorageException("Saving contents into hash store failed.", $newPath);
        }
        
        return $hash;
    }

    public function delete(string $hash): bool
    {
        $path = $this->getRealPath($hash);
        if (file_exists($path)) {
            if (!@unlink($path)) {
                throw new FileStorageException("Unable to remove given hash.", $path);
            }
            return true;
        }
        return false;
    }
}
