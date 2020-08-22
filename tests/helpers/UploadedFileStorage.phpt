<?php

use App\Helpers\UploadedFileStorage;
use App\Model\Entity\Instance;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Exceptions\InvalidArgumentException;
use Nette\Http\FileUpload;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Tester\Assert;

require __DIR__ . "/../bootstrap.php";

/**
 * @testCase
 */
class TestUploadedFileStorage extends Tester\TestCase
{
    /** @var UploadedFileStorage */
    protected $storage;

    /** @var vfsStreamDirectory */
    protected $vfsStream;

    /** @var User */
    protected $user;

    protected function setUp()
    {
        $this->vfsStream = vfsStream::setup(
            "root",
            null,
            [
                "uploads" => [],
                "tmp" => []
            ]
        );

        $this->storage = new UploadedFileStorage($this->vfsStream->getChild("uploads")->url());
        $this->user = Mockery::mock(User::class);
        $this->user->shouldReceive("getId")->andReturn(42)->zeroOrMoreTimes();
    }

    protected function makeUpload($name, $content = "Lorem Ipsum"): FileUpload
    {
        $tmpname = uniqid();
        $tmpdir = $this->vfsStream->getChild("tmp");
        $tmppath = $tmpdir->url() . DIRECTORY_SEPARATOR . $tmpname;
        file_put_contents($tmppath, $content);
        return new FileUpload(
            [
                "name" => $name,
                "size" => 1024,
                "tmp_name" => $tmppath,
                "error" => UPLOAD_ERR_OK
            ]
        );
    }

    public function testValid_1()
    {
        $upload = $this->makeUpload('hello.txt');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testValid_2()
    {
        $upload = $this->makeUpload('HelloWorld.java');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testValid_3()
    {
        $upload = $this->makeUpload('.htaccess');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testValid_4()
    {
        $upload = $this->makeUpload('HerpDerp.');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testValid_5()
    {
        $upload = $this->makeUpload('hello world.c');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testValid_6()
    {
        $upload = $this->makeUpload('Hello');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
    }

    public function testInvalidName_1()
    {
        Assert::exception(
            function () {
                $upload = $this->makeUpload('he$llo.txt');
                $this->storage->store($upload, $this->user);
            },
            InvalidArgumentException::class
        );
    }

    public function testInvalidName_2()
    {
        Assert::exception(
            function () {
                $upload = $this->makeUpload('BMI – NotePad.pas');
                $this->storage->store($upload, $this->user);
            },
            InvalidArgumentException::class
        );
    }

    public function testLowercaseExtension_1()
    {
        $upload = $this->makeUpload('hello world.C');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
        Assert::same("hello world.c", $file->getName());
    }

    public function testLowercaseExtension_2()
    {
        $upload = $this->makeUpload('hello world.pAs');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
        Assert::same("hello world.pas", $file->getName());
    }

    public function testLowercaseExtension_3()
    {
        $upload = $this->makeUpload('.htaCCEss');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
        Assert::same(".htaCCEss", $file->getName());
    }

    public function testLowercaseExtension_4()
    {
        $upload = $this->makeUpload('foo.tAr.GZ');
        $file = $this->storage->store($upload, $this->user);
        Assert::type(UploadedFile::class, $file);
        Assert::same("foo.tAr.gz", $file->getName());
    }
}

(new TestUploadedFileStorage())->run();
