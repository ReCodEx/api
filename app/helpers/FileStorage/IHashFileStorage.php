<?php

namespace App\Helpers\FileStorage;

/**
 * File storage that stores immutable files under their hash codes (SHA1 of the contents).
 * The storage is useful for data deduplication, but the file metadata (original names)
 * must be kept elsewhere (e.g., in database).
 */
interface IHashFileStorage
{
    /**
     * Retrieve given file.
     * @param string $hash file identification
     * @return IImmutableFile|null an object representing the file (null if no such file exists)
     */
    public function fetch(string $hash): ?IImmutableFile;

    /**
     * Retrieve given file, throw an exception if the file is missing.
     * @param string $hash file identification
     * @return IImmutableFile an object representing the file
     * @throws FileStorageException if the hash is not found in the storage
     */
    public function fetchOrThrow(string $hash): IImmutableFile;

    /**
     * Stores a regular file from local file system into the storage.
     * @param string $path Valid local path to an existing file.
     * @param bool $move whether the file should be moved (if false, the file is copied)
     * @return string Hash, which can be later used to retrieve the file.
     */
    public function storeFile(string $path, bool $move = true): string;

    /**
     * Stores data as a file into the storage.
     * @param mixed $contents Data to be stored as a new file (string or string-convertible entity).
     * @return string Hash, which can be later used to retrieve the file.
     */
    public function storeContents($contents): string;

    /**
     * Remove given hash-identified file from the storage.
     * Should be used carefully, since hash storage is typically favored for deduplication.
     * @param string $hash file to be deleted
     * @return bool true if the file was removed, false if no such hash exists
     */
    public function delete(string $hash): bool;
}
