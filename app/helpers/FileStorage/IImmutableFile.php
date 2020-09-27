<?php

namespace App\Helpers\FileStorage;

use ZipArchive;

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
     * Retrive the entire file contents as a (binary) string.
     * @param int $sizeLimit maximal length of the result, zero means no limit
     * @return string
     */
    public function getContents(int $sizeLimit = 0): string;

    /**
     * Retrive the entire file and save it to local path.
     * @param string $path
     */
    public function saveAs(string $path): void;

    /**
     * Save the file into a ZIP archive.
     * @param ZipArchive $zip zip archive that is already open for writing
     * @param string $entryName under which name the file should be stored in zip
     */
    public function addToZip(ZipArchive $zip, string $entryName): void;

    /**
     * Retrieve the entire file and pass it through to HTTP response as a file for download.
     * This special function is here merely to pass down the files more efficiently.
     * The headers should already be set appropriately.
     */
    public function passthru(): void;
}
