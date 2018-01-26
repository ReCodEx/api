<?php

namespace App\Responses;

use Nette;
use Nette\Application\Responses\FileResponse;
use Nette\Utils\FileSystem;


class DeleteableFileResponse extends FileResponse {

  /**
   * DeleteableFileResponse constructor.
   * @param $file
   * @param null $name
   * @param null $contentType
   * @param bool $forceDownload
   * @throws \Nette\Application\BadRequestException
   */
  public function __construct($file, $name = null, $contentType = null, bool $forceDownload = true) {
    parent::__construct($file, $name, $contentType, $forceDownload);
  }

  /**
   * Sends response to output.
   * @param Nette\Http\IRequest $httpRequest
   * @param Nette\Http\IResponse $httpResponse
   */
  public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
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
