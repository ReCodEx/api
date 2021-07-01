<?php

namespace App\Security\ACL;

use App\Model\Entity\SupplementaryExerciseFile;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\UploadedPartialFile;

interface IUploadedFilePermissions
{
    public function canViewDetail(UploadedFile $file): bool;

    public function canDownload(UploadedFile $file): bool;

    public function canUpload(): bool;

    public function canDownloadSupplementaryFile(SupplementaryExerciseFile $file): bool;

    public function canAppendPartial(UploadedPartialFile $file): bool;

    public function canCancelPartial(UploadedPartialFile $file): bool;

    public function canCompletePartial(UploadedPartialFile $file): bool;
}
