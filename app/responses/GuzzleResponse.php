<?php

namespace App\Responses;

use Nette;
use Nette\Application\IResponse;
use Psr\Http\Message\StreamInterface;

/**
 * File response which is meant to be used for guzzle streams implementing StreamInterface.
 */
class GuzzleResponse implements IResponse {

  /** @var StreamInterface */
  private $stream;

  /** @var string */
  private $contentType;

  /** @var string */
  private $name;

  /** @var bool */
  private $forceDownload;


  /**
   * Constructor.
   * @param StreamInterface $stream
   * @param string $name
   * @param string $contentType
   * @param bool $forceDownload
   */
  public function __construct(StreamInterface $stream, string $name,
      string $contentType = NULL, bool $forceDownload = TRUE)
  {
    $this->stream = $stream;
    $this->name = $name;
    $this->contentType = $contentType ? $contentType : 'application/octet-stream';
    $this->forceDownload = $forceDownload;
  }

  /**
   * Get name of the file which will be returned.
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Send response to client.
   * @param Nette\Http\IRequest $httpRequest
   * @param Nette\Http\IResponse $httpResponse
   */
  public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse) {
    $httpResponse->setContentType($this->contentType);
    $httpResponse->setHeader('Content-Disposition',
        ($this->forceDownload ? 'attachment' : 'inline')
        . '; filename="' . $this->name . '"'
        . '; filename*=utf-8\'\'' . rawurlencode($this->name));

    $length = $this->stream->getSize();
    $httpResponse->setHeader('Content-Length', $length);
    while (!$this->stream->eof() && $length > 0) {
      echo $s = $this->stream->read(min(4e6, $length));
      $length -= strlen($s);
    }
  }

}
