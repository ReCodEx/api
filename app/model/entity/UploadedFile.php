<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Nette\Http\FileUpload;

/**
 * @ORM\Entity
 * @ORM\Table(name="`uploaded_file`")
 */
class UploadedFile implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;
    
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;
    
    /**
     * @ORM\Column(type="string")
     */
    protected $name;
    
    /**
     * @ORM\Column(type="string")
     */
    protected $filePath;
    
    public function getContent() {
      return file_get_contents($this->filePath);
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $uploadedAt;

    /**
     * @ORM\Column(type="integer")
     */
    protected $fileSize;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    public function getUser() {
      return $this->user;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Submission")
     * @ORM\JoinColumn(name="submission_id", referencedColumnName="id")
     */
    protected $submission;

    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "name" => $this->name,
        "size" => $this->fileSize,
        "uploadedAt" => $this->uploadedAt->getTimestamp(),
        "userId" => $this->user->getId(),
        "content" => $this->getContent()
      ];
    }

    /**
     * @param FileUpload  $file   Uploaded file
     * @param User        $user   The user who uploaded the file
     * @param string      $fsRoot Root file system directory - file will be put here
     * @return bool|UploadedFile
     */
    public static function upload(FileUpload $file, User $user, $fsRoot = UPLOAD_DIR) {
      if (!$file->isOK()) {
        return FALSE;
      }

      try {
        $filePath = self::getFilePath($user->getId(), $file, $fsRoot);
        $file->move($filePath); // moving might fail with Nette\InvalidStateException if the user does not have sufficient rights to the FS
      } catch (\Nette\InvalidStateException $e) {
        return FALSE;
      }

      $uploadedFile = new UploadedFile;
      $uploadedFile->filePath = $filePath;
      $uploadedFile->name = $file->getSanitizedName();
      $uploadedFile->uploadedAt = new \DateTime;
      $uploadedFile->fileSize = $file->getSize();
      $uploadedFile->user = $user;
      return $uploadedFile;
    }

    protected static function getFilePath($userId, FileUpload $file, $fsRoot) {
      $fileName = pathinfo($file->getSanitizedName(), PATHINFO_FILENAME);
      $ext = pathinfo($file->getSanitizedName(), PATHINFO_EXTENSION);
      $uniqueId = uniqid();
      return "$fsRoot/user_{$userId}/{$fileName}_{$uniqueId}.$ext";
    }
}
