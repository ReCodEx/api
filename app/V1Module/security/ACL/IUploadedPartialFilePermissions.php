<?php

namespace App\Security\ACL;

use App\Model\Entity\UploadedPartialFile;

interface IUploadedPartialFilePermissions
{
    public function canAppendPartial(UploadedPartialFile $file): bool;

    public function canCancelPartial(UploadedPartialFile $file): bool;

    public function canCompletePartial(UploadedPartialFile $file): bool;
}
