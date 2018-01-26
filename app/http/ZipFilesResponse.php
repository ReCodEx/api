<?php

namespace App\Responses;

use App\Exceptions\ApiException;
use Nette;
use Nette\Application\Responses\FileResponse;
use Nette\Utils\FileSystem;
use ZipArchive;


/**
 * Response which is able to compress given file into ZIP archive, send it to
 * user and then delete create zip.
 */
class ZipFilesResponse extends FileResponse {

  /**
   * @var string[]
   */
  private $files;

  /**
   * ZipFilesResponse constructor.
   * @param string[] $files
   * @param null $name
   * @param null $contentType
   * @param bool $forceDownload
   * @throws \Nette\Application\BadRequestException
   */
  public function __construct(array $files, $name = null, $contentType = null, bool $forceDownload = true) {
    $zipFile = tempnam(sys_get_temp_dir(), "ReC");
    parent::__construct($zipFile, $name, $contentType, $forceDownload);
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

    foreach ($this->files as $file) {
      if ($zip->addFile($file) !== true) {
        throw new ApiException("Error while adding file to archive");
      }
    }

    if ($zip->close() !== true) {
      throw new ApiException("Archive could not be properly saved");
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

    // in order to delete file after download, lets forbid continuous download
    $this->resuming = false;
    parent::send($httpRequest, $httpResponse);

    try {
      // delete file after it was served to user
      FileSystem::delete($this->getFile());
    } catch (Nette\IOException $e) {
      // silent
    }
  }

}
