<?php

namespace App\Helpers;

/**
 * Helps creating tmp files in local temp directory.
 * It makes sure the temp exists and tries to remove all tmp files at the end.
 */
class TmpFilesHelper
{
    private $tempDirectory;
    private $files = [];

    public function __construct(?string $tempDirectory = null)
    {
        $this->tempDirectory = $tempDirectory ? "$tempDirectory/tmpfiles" : sys_get_temp_dir();
    }

    public function __destruct()
    {
        // try to remove all tmp file created by this helper
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Create a tmp file in a local temp directory and return path to it.
     * @param string $prefix prefix for the filename
     * @return string path to new tmp file
     */
    public function createTmpFile(string $prefix = ''): string
    {
        if (!is_dir($this->tempDirectory)) {
            @mkdir($this->tempDirectory, 0770, true);
        }
        $name = tempnam($this->tempDirectory, $prefix);
        $this->files[] = $name;
        return $name;
    }
}
