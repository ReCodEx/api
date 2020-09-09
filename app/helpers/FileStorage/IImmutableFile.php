<?php

namespace App\Helpers\FileStorage;

/**
 * Abstraction that represents one immutable (read-only) file.
 */
interface IImmutableFile
{
    /**
     * Return a string that identifies the file within its file storage
     * (e.g., a relative path or a hash).
     * @return string
     */
    public function getStoragePath(): string;

    /**
     * Return actual size of the file in bytes.
     * @return int
     */
    public function getSize(): int;

    /**
     * Retrive the entire file contents as a binary-safe string.
     * @return string
     */
    public function getContents(): string;

    /**
     * Retrive the entire file and save it to local path.
     * @param string $path
     */
    public function saveAs(string $path): void;

    /**
     * Retrieve the entire file and pass it through to HTTP response as a file for download.
     * This special function is here merely to pass down the files more efficiently.
     * The headers should already be set appropriately.
     */
    public function passthru(): void;
}
