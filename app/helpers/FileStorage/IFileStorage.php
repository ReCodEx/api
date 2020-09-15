<?php

namespace App\Helpers\FileStorage;

/**
 * File storage that stores immutable files organized in sub-directories.
 * The storage also allows direct ZIP archive dereference (path/to/file.zip#zip).
 * The ZIP archive access allows only one-level of dereference (i.e., not accessing archives within archives).
 *
 * Furthermore, there are no explicit methods for managing directories. Directories (as well as archives) are
 * automatically created as needed (e.g., when file is being stored) and removed when empty (removed are only
 * directories, not archives).
 *
 * The storage may use caching, so reading a file that was just modified may return old data.
 * The storage offers flush() method that immediately writes all changes and invalidate reading caches.
 */
interface IFileStorage
{
    /**
     * Retrieve given file.
     * @param string $path relative path within storage (allowing zip archive dereference)
     * @return IImmutableFile|null an object representing the file (null if no such file exists)
     */
    public function fetch(string $path): ?IImmutableFile;

    /**
     * Retrieve given file, throw an exception if the file is missing.
     * @param string $path relative path within storage (allowing zip archive dereference)
     * @return IImmutableFile|null an object representing the file
     * @throws FileStorageException if the hash is not found in the storage
     */
    public function fetchOrThrow(string $path): IImmutableFile;

    /**
     * Stores a regular file from local file system into the storage.
     * @param string $localPath valid local path to an existing file
     * @param string $storagePath relative path within storage (allowing zip archive dereference)
     * @param bool $move the local file will moved instead of copied (more efficient on local filesystem)
     * @param bool $overwrite flag indicating whether existing file may be overriden
     *                            (if false, existing file will cause an error)
     * @throws FileStorageException if the file cannot be stored (for any reason)
     */
    public function storeFile(string $localPath, string $storagePath, bool $move = true, bool $overwrite = false): void;

    /**
     * Stores data as a file into the storage.
     * @param mixed $contents data to be stored as a new file (string or string-convertible entity)
     * @param string $storagePath relative path within storage (allowing zip archive dereference)
     * @param bool $overwrite flag indicating whether existing file may be overriden
     *                            (if false, existing file will cause an error)
     * @throws FileStorageException if the file cannot be stored (for any reason)
     */
    public function storeContents($contents, string $storagePath, bool $overwrite = false): void;

    /**
     * Stores data from an open stream into a storage file.
     * @param resource $stream
     * @param string $storagePath relative path within storage (allowing zip archive dereference)
     * @param bool $overwrite flag indicating whether existing file may be overriden
     *                            (if false, existing file will cause an error)
     * @throws FileStorageException if the file cannot be stored (for any reason)
     */
    public function storeStream($stream, string $storagePath, bool $overwrite = false): void;

    /**
     * Copy a file within the storage.
     * @param string $src path to the source file (allowing zip archive dereference)
     * @param string $dst path to the target file (allowing zip archive dereference)
     * @param bool $overwrite flag indicating whether existing dst file may be overriden
     *                            (if false, existing dst will cause an error)
     * @throws FileStorageException if the file cannot be stored (for any reason)
     */
    public function copy(string $src, string $dst, bool $overwrite = false): void;

    /**
     * Move (possibly rename) a file within the storage.
     * @param string $src path to the source file (allowing zip archive dereference)
     * @param string $dst path to the target file (allowing zip archive dereference)
     * @param bool $overwrite flag indicating whether existing dst file may be overriden
     *                            (if false, existing dst will cause an error)
     * @throws FileStorageException if the file cannot be stored (for any reason)
     */
    public function move(string $src, string $dst, bool $overwrite = false): void;

    /**
     * Remove given file from the storage.
     * @param string $path relative path within storage (allowing zip archive dereference)
     * @return bool true if the file was removed, false if no such path exists
     */
    public function delete(string $path): bool;

    /**
     * Make sure all modifications are written and all caches invalidated.
     */
    public function flush(): void;
}
