<?php

namespace App\Helpers\FileStorage;

use Exception;
use Nette\Http\IResponse;

/**
 * Thrown in case of internal file storage errors.
 */
class FileStorageException extends Exception
{
    public $path;

    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param string $path name of the related file/directory that caused the exception
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = 'Unexpected file storage error',
        ?string $path = null,
        $previous = null
    ) {
        parent::__construct($msg, IResponse::S500_InternalServerError, $previous);
        $this->path = $path;
    }
}
