<?php

/**
 * Creates a wrapper that mocks php://input stream redirecting all operations on regular file.
 */
class PhpInputMock
{
    private static $path = null;
    private $fp = null;

    public static function init($path)
    {
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
        self::$path = $path;
    }

    public static function restore()
    {
        stream_wrapper_restore('php');
    }

    public function stream_stat()
    {
        return fstat($this->fp);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        if ($path !== 'php://input') {
            return false;
        }
        $this->fp = fopen(self::$path, $mode);
        return (bool)$this->fp;
    }

    public function stream_write($data)
    {
        return fwrite($this->fp, $data);
    }

    public function stream_eof()
    {
        return feof($this->fp);
    }

    public function stream_read($length)
    {
        return fread($this->fp, $length);
    }

    public function stream_close()
    {
        fclose($this->fp);
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->fp, $offset, $whence);
    }

    public function stream_tell()
    {
        return ftell($this->fp);
    }
}
