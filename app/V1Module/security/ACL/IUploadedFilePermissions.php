<?php
namespace App\Security\ACL;

use App\Model\Entity\UploadedFile;

interface IUploadedFilePermissions {
  function canViewDetail(UploadedFile $file): bool;
  function canDownload(UploadedFile $file): bool;
  function canUpload(): bool;
}