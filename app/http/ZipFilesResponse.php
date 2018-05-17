<?php

namespace App\Responses;

use App\Exceptions\ApiException;
use Nette;
use Nette\Application\Responses\FileResponse;
use Nette\Utils\FileSystem;
use ZipArchive;


/**
 * Response which is able to compress given file into ZIP archive, send it to
 * user and then delete created zip file. If flag deleteFiles is true, then
 * given files will be deleted after sending response.
 */
class ZipFilesResponse extends FileResponse {

  /**
   * Indexed by local path, containing original filename.
   * @var string[]
   */
  private $files;

  /**
   * @var bool
   */
  private $deleteFiles;

  /**
   * ZipFilesResponse constructor.
   * @param string[] $files indexed by local path, containing original filename
   * @param null $name
   * @param bool $deleteFiles if true given files will be deleted after send
   * @param bool $forceDownload
   * @throws Nette\Application\BadRequestException
   */
  public function __construct(array $files, $name = null, bool $deleteFiles = false, bool $forceDownload = true) {
    $zipFile = tempnam(sys_get_temp_dir(), "ReC");
    parent::__construct($zipFile, $name, "application/zip", $forceDownload);
    $this->files = $files;
    $this->deleteFiles = $deleteFiles;
  }

  /**
   * Compress given files to zip archive.
   * @throws ApiException
   */
  private function compress() {
    $zip = new ZipArchive();
    if ($zip->open($this->getFile(), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      throw new ApiException("Archive could not be created");
    }

    foreach ($this->files as $localPath => $name) {
      if ($zip->addFile($localPath, $name) !== true) {
        throw new ApiException("Error while adding file to archive");
      }
    }

    if ($zip->close() !== true) {
      throw new ApiException("Archive could not be properly saved");
    }

    if (count($this->files) === 0) {
      // Well the php is interesting in handling zip archives, if you do not
      // provide any file to it, the php will process everything correctly and
      // successfully, creating and even closing, butt beware because if you do
      // not provided nada files, the zip will not be created at all!!!
      // So for further processing we need to at least create an empty file,
      // which will omit further warnings concerning non-existing file during
      // sending back to the user.
      touch($this->getFile());
    }
  }

  /**
   * Sends response to output.
   * @param Nette\Http\IRequest $httpRequest
   * @param Nette\Http\IResponse $httpResponse
   * @throws ApiException
   */
  public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
    // first compress all given files into zip
    $this->compress();

    // clear all potentially cached information like filesize and such
    clearstatcache();

    // in order to delete file after download, lets forbid continuous download
    $this->resuming = false;
    parent::send($httpRequest, $httpResponse);

    try {
      // delete file after it was served to user
      FileSystem::delete($this->getFile());
    } catch (Nette\IOException $e) {
      // silent
    }

    // given files was asked to be deleted after send
    if ($this->deleteFiles) {
      foreach ($this->files as $file) {
        try {
          FileSystem::delete($file);
        } catch (Nette\IOException $e) {
          // silent
        }
      }
    }
  }

}
