<?php

namespace App\Security\ACL;

use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;

interface IUploadedFilePermissions
{
    public function canViewDetail(UploadedFile $file): bool;

    public function canDownload(UploadedFile $file): bool;

    public function canUpload(): bool;

    public function canDownloadSupplementaryFile(SupplementaryExerciseFile $file): bool;
}
