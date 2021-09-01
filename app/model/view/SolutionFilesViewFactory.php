<?php

namespace App\Model\View;

use App\Model\Entity\Solution;
use App\Model\Entity\SolutionZipFile;
use App\Helpers\FileStorageManager;
use App\Helpers\ExerciseConfig\Pipeline\Box\ExecutionBox;

/**
 * Factory for solution files (both assignment and reference) view.
 */
class SolutionFilesViewFactory
{
    /**
     * @var FileStorageManager
     * @inject
     */
    private $fileStorage;

    public function __construct(FileStorageManager $fileStorage)
    {
        $this->fileStorage = $fileStorage;
    }

    /**
     * Parametrized view.
     * @param Solution $solution
     * @return array
     */
    public function getSolutionFilesData(Solution $solution)
    {
        $entryPoint = $solution->getSolutionParams()->getVariable(ExecutionBox::$ENTRY_POINT_KEY);
        $entryPoint = $entryPoint ? $entryPoint->getValue() : null;

        $files = $solution->getFiles()->toArray();
        foreach ($files as &$file) {
            if ($file instanceof SolutionZipFile) {
                $physicalFile = $file->getFile($this->fileStorage);
            }

            $file = $file->jsonSerialize();
            if ($file['name'] === $entryPoint) {
                $file['isEntryPoint'] = true;
            }
            if (!empty($physicalFile)) {
                $file['zipEntries'] = $physicalFile->getZipEntries();
            }
        }
        unset($file);

        return $files;
    }
}
