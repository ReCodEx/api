<?php

namespace App\Responses;

use Nette;
use Nette\Application\Response;
use App\Helpers\FileStorage\IImmutableFile;

/**
 * File response that serves IImmutableFile from file storage.
 */
class StorageFileResponse implements Response
{
    /** @var IImmutableFile */
    private $file;

    /** @var string */
    private $contentType;

    /** @var string */
    private $name;

    /** @var bool */
    private $forceDownload;


    /**
     * Constructor.
     * @param IImmutableFile $file
     * @param string $name
     * @param string $contentType
     * @param bool $forceDownload
     */
    public function __construct(
        IImmutableFile $file,
        string $name,
        ?string $contentType = null,
        bool $forceDownload = true
    ) {
        $this->file = $file;
        $this->name = $name;
        $this->contentType = $contentType ? $contentType : 'application/octet-stream';
        $this->forceDownload = $forceDownload;
    }

    /**
     * Get name of the file which will be returned.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Send response to client.
     * @param Nette\Http\IRequest $httpRequest
     * @param Nette\Http\IResponse $httpResponse
     */
    public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse): void
    {
        $httpResponse->setContentType($this->contentType);
        $httpResponse->setHeader(
            'Content-Disposition',
            ($this->forceDownload ? 'attachment' : 'inline')
                . '; filename="' . $this->name . '"'
                . '; filename*=utf-8\'\'' . rawurlencode($this->name)
        );

        $httpResponse->setHeader('Content-Length', $this->file->getSize());
        $this->file->passthru();
    }
}
