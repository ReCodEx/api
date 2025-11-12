<?php

namespace App\Security\ACL;

use App\Model\Entity\ExerciseFile;
use App\Model\Entity\UploadedFile;

interface IUploadedFilePermissions
{
    public function canViewDetail(UploadedFile $file): bool;

    public function canDownload(UploadedFile $file): bool;

    public function canUpload(): bool;

    public function canDownloadExerciseFile(ExerciseFile $file): bool;
}
