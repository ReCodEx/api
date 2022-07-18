<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\FileStorage\IHashFileStorage;
use App\Helpers\FileStorage\IFileStorage;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\FileStorage\ArchivedImmutableFile;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\ZipFileStorage;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\FileStorageManager;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\UploadedPartialFile;
use App\Model\Repository\Users;
use App\Helpers\TmpFilesHelper;
use Nette\Utils\Strings;
use Tester\Assert;

/**
 * @testCase
 *
 * !!! we are using actual filesystem (tmp directory) !!!
 * the reason is that vfs is not supported by ZipArchive
 */
class TestFileStorage extends Tester\TestCase
{
    protected $tmpDir = null;
    protected $hashStorageCounter = 1;
    protected $localStorageCounter = 1;

    /** @var App\Model\Repository\Users */
    protected $users;

    /** @var  Nette\DI\Container */
    protected $container;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->users = $container->getByType(Users::class);
    }

    private static function setPropertyOfObject($object, $property, $newValue)
    {
        $reflectionClass = new ReflectionClass($object);
        $reflectionProperty = $reflectionClass->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $newValue);
    }

    private static function createTmpDir()
    {
        static $counter = 0;

        $root = sys_get_temp_dir();
        if (!$root || !is_dir($root)) {
            throw new \Exception("No tmp base dir.");
        }

        do {
            $ts = time();
            ++$counter;
            $path = "$root/recodex-$ts-$counter";
        } while (file_exists($path) || !@mkdir($path));

        if (!is_dir($path) || !Strings::startsWith($path, sys_get_temp_dir())) {
            throw new \Exception("Unable to create tmp dir $path");
        }
        return $path;
    }

    private static function rmdirRecursive($path)
    {
        if (!Strings::startsWith($path, sys_get_temp_dir())) {
            throw new \Exception("Must not rmdir something oustise temp dir $path");
        }
        if (Strings::endsWith($path, '/.') || Strings::endsWith($path, '/..')) {
            return;
        }
        if (is_dir($path)) {
            $entries = new DirectoryIterator($path);
            foreach ($entries as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    self::rmdirRecursive($fileinfo->getPathname());
                }
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    protected function createTmpFile($contents = null)
    {
        $name = tempnam($this->tmpDir, 'file');
        touch($name);
        if ($contents) {
            file_put_contents($name, $contents);
        }
        return $name;
    }

    protected function createZipFile(array $contents)
    {
        $name = tempnam($this->tmpDir, 'zip');
        $zip = new ZipArchive();
        $zip->open($name, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        foreach ($contents as $entry => $data) {
            $zip->addFromString($entry, $data);
        }
        $zip->close();
        if (!$contents) {
            touch($name);
        }
        return $name;
    }

    protected function prepareHashStorage($files = []): IHashFileStorage
    {
        // prepare hash storage dir with one file
        $hashDir = $this->tmpDir . '/hash' . ($this->hashStorageCounter++);
        if (is_dir($hashDir)) {
            self::rmdirRecursive($hashDir);
        }
        @mkdir($hashDir);
        $storage = new LocalHashFileStorage([ 'root' => $hashDir ]);

        foreach ($files as $file) {
            $hash = sha1($file);
            $hashDir .= '/' . substr($hash, 0, 3);
            if (!is_dir($hashDir)) {
                @mkdir($hashDir);
            }
            file_put_contents($hashDir . '/' . $hash, $file);
        }

        return $storage;
    }

    protected function prepareLocalStorage($files = []): IFileStorage
    {
        // prepare local storage dir
        $rootDir = $this->tmpDir . '/loc' . ($this->localStorageCounter++);
        if (is_dir($rootDir)) {
            self::rmdirRecursive($rootDir);
        }
        @mkdir($rootDir);
        $storage = new LocalFileStorage(new TmpFilesHelper($this->tmpDir), [ 'root' => $rootDir ]);

        foreach ($files as $file => $contents) {
            if (Strings::contains($file, '/')) {
                @mkdir($rootDir . '/' . dirname($file), 0777, true);
            }
            $file = "$rootDir/$file";
            if (is_array($contents)) {
                $zip = new ZipArchive();
                $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                foreach ($contents as $entry => $entryContents) {
                    $zip->addFromString($entry, $entryContents);
                }
                $zip->close();
                touch($file);
            } else {
                file_put_contents($file, $contents);
            }
        }

        return $storage;
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        if ($this->tmpDir && is_dir($this->tmpDir)) {
            self::rmdirRecursive($this->tmpDir);
        }
        $this->tmpDir = self::createTmpDir();
    }

    public function __destruct()
    {
        if ($this->tmpDir && is_dir($this->tmpDir)) {
            self::rmdirRecursive($this->tmpDir);
        }
    }

    public function testHashStoreFetch()
    {
        $contents = "Lorem ipsum et sepsum!";
        $hash = sha1($contents);
        $hashStorage = $this->prepareHashStorage([ $contents ]);
        $file = $hashStorage->fetchOrThrow($hash);
        Assert::type(IImmutableFile::class, $file);
        Assert::equal($hash, $file->getStoragePath());
        Assert::equal($contents, $file->getContents());
        Assert::equal($contents, $file->getContents(strlen($contents)));
        Assert::equal($contents, $file->getContents(1024));
        Assert::equal(substr($contents, 0, 5), $file->getContents(5));
    }

    public function testHashStoreFetchNonexist()
    {
        $contents = "Lorem ipsum et sepsum!";
        $hash = sha1($contents);
        $hashStorage = $this->prepareHashStorage([ $contents ]);
        $file = $hashStorage->fetch(sha1('no aint here'));
        Assert::null($file);
        Assert::exception(function () use ($hashStorage) {
            $file = $hashStorage->fetchOrThrow(sha1('no aint here'));
        }, FileStorageException::class, "File hash not found.");
    }

    public function testHashStoreFetchInvalidPath()
    {
        $hashStorage = $this->prepareHashStorage();
        Assert::exception(function () use ($hashStorage) {
            $hashStorage->fetch('../../config.neon');
        }, FileStorageException::class, "Given file hash contains invalid characters.");
    }

    public function testHashStoreAddFile()
    {
        $contents = "Lorem ipsum et sepsum!";
        $contents2 = "And some more psum!";
        $hash = sha1($contents);
        $hash2 = sha1($contents2);
        $hashStorage = $this->prepareHashStorage();
        $tmpfile = $this->createTmpFile($contents);
        $tmpfile2 = $this->createTmpFile($contents2);
        $tmpfile3 = $this->createTmpFile($contents2);

        Assert::equal($hash, $hashStorage->storeFile($tmpfile, false));
        Assert::true(file_exists($tmpfile));
        Assert::equal($hash, $hashStorage->storeFile($tmpfile));
        Assert::false(file_exists($tmpfile));

        Assert::equal($hash2, $hashStorage->storeFile($tmpfile2));
        Assert::false(file_exists($tmpfile2));
        Assert::equal($hash2, $hashStorage->storeFile($tmpfile3, false));
        Assert::true(file_exists($tmpfile3));

        $file = $hashStorage->fetch($hash);
        Assert::type(IImmutableFile::class, $file);
        Assert::equal($hash, $file->getStoragePath());
        Assert::equal($contents, $file->getContents());
    }

    public function testHashStoreAddContents()
    {
        $contents = "Lorem ipsum et sepsum!";
        $hash = sha1($contents);
        $hashStorage = $this->prepareHashStorage();
        Assert::equal($hash, $hashStorage->storeContents($contents));
        $file = $hashStorage->fetch($hash);
        Assert::type(IImmutableFile::class, $file);
        Assert::equal($hash, $file->getStoragePath());
        Assert::equal($contents, $file->getContents());
    }

    public function testHashStoreDelete()
    {
        $contents = "Lorem ipsum et sepsum!";
        $hash = sha1($contents);
        $hashStorage = $this->prepareHashStorage([ $contents ]);
        Assert::type(IImmutableFile::class, $hashStorage->fetch($hash));
        Assert::true($hashStorage->delete($hash));
        Assert::null($hashStorage->fetch($hash));
        Assert::false($hashStorage->delete($hash));
        Assert::false($hashStorage->delete(sha1('nonexistent file')));
    }

    public function testArchivedImmutableFile()
    {
        $entry = 'foo/bar';
        $data = 'abcde';
        $zip = $this->createZipFile([ $entry => $data ]);
        $file = new ArchivedImmutableFile($zip, $entry);
        Assert::equal("$zip#$entry", $file->getStoragePath());
        Assert::equal(strlen($data), $file->getSize());
        Assert::equal($data, $file->getContents());
        $tmpfile = $this->createTmpFile();
        $file->saveAs($tmpfile);
        Assert::equal($data, file_get_contents($tmpfile));
    }

    public function testZipFileStorageFetch()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);
        $fileA = $storage->fetch('a.txt');
        Assert::equal('AAAAA', $fileA->getContents());
        $fileB = $storage->fetchOrThrow('b.txt');
        Assert::equal('BBBB', $fileB->getContents());
        Assert::equal('BBBB', $fileB->getContents(4));
        Assert::equal('BBBB', $fileB->getContents(42));
        Assert::equal('BB', $fileB->getContents(2));
    }

    public function testZipFileStorageFetchNonexist()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);
        $fileC = $storage->fetch('c.txt');
        Assert::null($fileC);
        $fileB = $storage->fetchOrThrow('b.txt');
        Assert::exception(function () use ($storage) {
            $storage->fetchOrThrow('A.TXT');
        }, FileStorageException::class, "File not found within the storage.");
    }

    public function testZipFileStorageStoreFile()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        $tmpX = $this->createTmpFile('XXX');
        $tmpY = $this->createTmpFile('YYY');

        $storage->storeFile($tmpX, 'x.txt');
        $storage->storeFile($tmpY, 'a.txt', false, true); // do not move but overwrite
        $storage->flush();

        Assert::false(file_exists($tmpX)); // has been moved
        Assert::true(file_exists($tmpY)); // has been copied

        Assert::equal('XXX', $storage->extractToString('x.txt'));
        $tmpfile = $this->createTmpFile();
        $storage->extract('a.txt', $tmpfile, true);
        Assert::equal('YYY', file_get_contents($tmpfile));

        Assert::exception(function () use ($storage, $tmpfile) {
            $storage->storeFile($tmpfile, 'b.txt', false, false);
        }, FileStorageException::class, "Target entry already exists.");
    }

    public function testZipFileStorageStoreContents()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        $storage->storeContents('XXX', 'x.txt', false);
        $storage->storeContents('YYY', 'a.txt', true);
        $storage->flush();

        Assert::equal('XXX', $storage->extractToString('x.txt'));
        $tmpfile = $this->createTmpFile();
        $storage->extract('a.txt', $tmpfile, true);
        Assert::equal('YYY', file_get_contents($tmpfile));

        Assert::exception(function () use ($storage) {
            $storage->storeContents('ZZZ', 'b.txt', false);
        }, FileStorageException::class, "Target entry already exists.");
    }

    public function testZipFileStorageStoreStream()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        $tmpX = $this->createTmpFile('XXX');
        $tmpY = $this->createTmpFile('YYY');

        $fpX = fopen($tmpX, "rb");
        $storage->storeStream($fpX, 'x.txt');
        Assert::true(fclose($fpX));

        $fpY = fopen($tmpY, "rb");
        $storage->storeStream($fpY, 'a.txt', true); // overwrite
        Assert::true(fclose($fpY));
        $storage->flush();

        Assert::equal('XXX', $storage->extractToString('x.txt'));
        $tmpfile = $this->createTmpFile();
        $storage->extract('a.txt', $tmpfile, true);
        Assert::equal('YYY', file_get_contents($tmpfile));

        $fpX = fopen($tmpX, "rb");
        Assert::exception(function () use ($storage, $fpX) {
            $storage->storeStream($fpX, 'b.txt', false);
        }, FileStorageException::class, "Target entry already exists.");
        Assert::true(fclose($fpX));
    }

    public function testZipFileStorageStoreCopy()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        $storage->copy('a.txt', 'c.txt');
        $storage->copy('b.txt', 'a.txt', true);
        $storage->flush();

        Assert::equal('BBBB', $storage->extractToString('a.txt'));
        Assert::equal('BBBB', $storage->extractToString('b.txt'));
        Assert::equal('AAAAA', $storage->extractToString('c.txt'));

        Assert::exception(function () use ($storage) {
            $storage->copy('a.txt', 'b.txt');
        }, FileStorageException::class, "Unable to copy file to 'b.txt', target entry already exists.");
    }

    public function testZipFileStorageStoreMove()
    {
        $zip = $this->createZipFile([ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ]);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        $storage->move('a.txt', 'c.txt');
        $storage->move('b.txt', 'a.txt');
        $storage->flush();

        Assert::equal('BBBB', $storage->extractToString('a.txt'));
        Assert::equal('AAAAA', $storage->extractToString('c.txt'));

        Assert::exception(function () use ($storage) {
            $storage->move('c.txt', 'a.txt');
        }, FileStorageException::class, "Unable to rename an entry 'c.txt' to 'a.txt' in the ZIP archive.");

        $storage->move('c.txt', 'a.txt', true);
        $storage->flush();

        Assert::equal('AAAAA', $storage->extractToString('a.txt'));
        Assert::null($storage->fetch('b.txt'));
        Assert::null($storage->fetch('c.txt'));
    }

    public function testZipFileStorageStoreDelete()
    {
        $files = [ 'a.txt' => 'AAAAA', 'b.txt' => 'BBBB' ];
        $zip = $this->createZipFile($files);
        $storage = new ZipFileStorage(new TmpFilesHelper($this->tmpDir), $zip);

        foreach (array_keys($files) as $file) {
            Assert::true($storage->delete($file));
            Assert::false($storage->delete($file));
            $storage->flush();
            Assert::null($storage->fetch($file));
        }
    }

    public function testLocalFileStorageFetch()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAAAA',
            'b.txt' => 'BBB',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'bar/bar' => 'BAR']
        ]);

        $fileA = $storage->fetch('a.txt');
        Assert::equal('AAAAA', $fileA->getContents());
        Assert::equal('AAAAA', $fileA->getContents(5));
        Assert::equal('AAAAA', $fileA->getContents(42));
        Assert::equal('AAA', $fileA->getContents(3));
        $fileB = $storage->fetch('b.txt');
        $tmp1 = $this->createTmpFile();
        $fileB->saveAs($tmp1);
        Assert::equal('BBB', file_get_contents($tmp1));

        $fileFoo = $storage->fetch('z/z.zip#foo.md');
        Assert::equal('FOO', $fileFoo->getContents());
        $fileBar = $storage->fetch('z/z.zip#bar/bar');
        $tmp2 = $this->createTmpFile();
        $fileBar->saveAs($tmp2);
        Assert::equal('BAR', file_get_contents($tmp2));

        Assert::null($storage->fetch('q.txt'));
        Assert::exception(function () use ($storage) {
            $storage->fetchOrThrow('z/z.zip#q.txt');
        }, FileStorageException::class, "File not found within the storage.");
    }

    private function checkFileContents($storage, $file, $contents)
    {
        $f = $storage->fetch($file);
        Assert::type(IImmutableFile::class, $f);
        Assert::equal($contents, $f->getContents());
    }

    public function testLocalFileStorageStoreFile()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAA',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'bar/bar' => 'BAR']
        ]);

        // regular files
        $tmpB = $this->createTmpFile('BBB');
        $storage->storeFile($tmpB, 'b.txt');
        Assert::false(file_exists($tmpB)); // file has been moved
        $this->checkFileContents($storage, 'b.txt', 'BBB');

        $tmpA = $this->createTmpFile('A-OVERWRITE');
        $storage->storeFile($tmpA, 'a.txt', false, true); // do not move and overwrite
        Assert::true(file_exists($tmpA)); // file has been copied
        $this->checkFileContents($storage, 'a.txt', 'A-OVERWRITE');

        Assert::exception(function () use ($storage, $tmpA) {
            $storage->storeFile($tmpA, 'z/z.zip'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // archived files within ZIPs
        $storage->storeFile($tmpA, 'z/z.zip#a.new', false);
        Assert::true(file_exists($tmpA)); // file has been copied
        $this->checkFileContents($storage, 'z/z.zip#a.new', 'A-OVERWRITE');

        $tmpFoo = $this->createTmpFile('foverwrite');
        $storage->storeFile($tmpFoo, 'z/z.zip#foo.md', true, true);
        Assert::false(file_exists($tmpFoo)); // file has been moved
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'foverwrite');

        Assert::exception(function () use ($storage, $tmpA) {
            $storage->storeFile($tmpA, 'z/z.zip#bar/bar'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");
    }

    public function testLocalFileStorageStoreContents()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAA',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'bar/bar' => 'BAR']
        ]);

        // regular files
        $storage->storeContents('BBB', 'b.txt');
        $this->checkFileContents($storage, 'b.txt', 'BBB');

        $storage->storeContents('A-OVERWRITE', 'a.txt', true);
        $this->checkFileContents($storage, 'a.txt', 'A-OVERWRITE');

        Assert::exception(function () use ($storage) {
            $storage->storeContents('zipzip', 'z/z.zip'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // archived files within ZIPs
        $storage->storeContents('A-OVERWRITE', 'z/z.zip#a.new');
        $this->checkFileContents($storage, 'z/z.zip#a.new', 'A-OVERWRITE');

        $storage->storeContents('foverwrite', 'z/z.zip#foo.md', true);
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'foverwrite');

        Assert::exception(function () use ($storage) {
            $storage->storeContents('barbar', 'z/z.zip#bar/bar'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");
    }

    public function testLocalFileStorageStoreStream()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAA',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'bar/bar' => 'BAR']
        ]);

        // regular files
        $tmpB = $this->createTmpFile('BBB');
        $fpB = fopen($tmpB, "rb");
        $storage->storeStream($fpB, 'b.txt');
        Assert::true(fclose($fpB));
        $this->checkFileContents($storage, 'b.txt', 'BBB');

        $tmpA = $this->createTmpFile('A-OVERWRITE');
        $fpA = fopen($tmpA, "rb");
        $storage->storeStream($fpA, 'a.txt', true); // overwrite
        Assert::equal(0, fseek($fpA, 0));
        $this->checkFileContents($storage, 'a.txt', 'A-OVERWRITE');

        Assert::exception(function () use ($storage, $fpA) {
            $storage->storeStream($fpA, 'z/z.zip'); // no overwrite
        }, FileStorageException::class, "File already exists.");
        Assert::equal(0, fseek($fpA, 0));

        // archived files within ZIPs
        $storage->storeStream($fpA, 'z/z.zip#a.new');
        Assert::equal(0, fseek($fpA, 0));
        $this->checkFileContents($storage, 'z/z.zip#a.new', 'A-OVERWRITE');

        $storage->storeStream($fpA, 'z/z.zip#foo.md', true); // overwrite
        Assert::equal(0, fseek($fpA, 0));
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'A-OVERWRITE');

        Assert::exception(function () use ($storage, $fpA) {
            $storage->storeStream($fpA, 'z/z.zip#bar/bar'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");
        Assert::true(fclose($fpA));
    }

    public function testLocalFileStorageStoreCopy()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAA',
            'b.txt' => 'BBB',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'bar/bar' => 'BAR'],
            'z2.zip' => [ 'job.log' => 'failed' ]
        ]);

        // regular files
        $storage->copy('a.txt', 'c.txt');
        $storage->copy('b.txt', 'a.txt', true);
        $this->checkFileContents($storage, 'c.txt', 'AAA');
        $this->checkFileContents($storage, 'a.txt', 'BBB');
        Assert::exception(function () use ($storage) {
            $storage->copy('a.txt', 'c.txt'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // regular to ZIP
        $storage->copy('a.txt', 'z2.zip#b.txt');
        $storage->copy('a.txt', 'z/z.zip#foo.md', true);
        $this->checkFileContents($storage, 'z2.zip#b.txt', 'BBB');
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'BBB');
        Assert::exception(function () use ($storage) {
            $storage->copy('a.txt', 'z/z.zip#bar/bar'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");

        // ZIP to regular
        $storage->copy('z2.zip#b.txt', 'newb.txt');
        $storage->copy('z/z.zip#bar/bar', 'bar.txt', true);
        $this->checkFileContents($storage, 'newb.txt', 'BBB');
        $this->checkFileContents($storage, 'bar.txt', 'BAR');
        Assert::exception(function () use ($storage) {
            $storage->copy('z2.zip#job.log', 'a.txt'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // within one ZIP
        $storage->copy('z/z.zip#foo.md', 'z/z.zip#goo/foo.md');
        $storage->copy('z/z.zip#bar/bar', 'z/z.zip#foo.md', true);
        $this->checkFileContents($storage, 'z/z.zip#goo/foo.md', 'BBB');
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'BAR');
        Assert::exception(function () use ($storage) {
            $storage->copy('z2.zip#job.log', 'z2.zip#b.txt'); // no overwrite
        }, FileStorageException::class, "Unable to copy file to 'b.txt', target entry already exists.");

        // from ZIP to another ZIP
        $storage->copy('z/z.zip#foo.md', 'z2.zip#foo.md');
        $storage->copy('z/z.zip#bar/bar', 'z2.zip#job.log', true);
        $this->checkFileContents($storage, 'z2.zip#foo.md', 'BAR');
        $this->checkFileContents($storage, 'z2.zip#job.log', 'BAR');
        Assert::exception(function () use ($storage) {
            $storage->copy('z2.zip#job.log', 'z/z.zip#foo.md'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");

        // to newly created ZIP
        $storage->copy('a.txt', 'new.zip#a.txt');
        $this->checkFileContents($storage, 'new.zip#a.txt', 'BBB');

        // overwrite entire ZIP
        $storage->copy('new.zip#a.txt', 'z2.zip', true);
        $this->checkFileContents($storage, 'z2.zip', 'BBB');

        // nonexist file
        Assert::exception(function () use ($storage) {
            $storage->copy('nonexist', 'new.zip#unicorn'); // no overwrite
        }, FileStorageException::class, "File not found within the storage.");
        Assert::exception(function () use ($storage) {
            $storage->copy('z', 'new.zip#unicorn'); // no overwrite
        }, FileStorageException::class, "Given path refers to a directory.");
    }

    public function testLocalFileStorageStoreMove()
    {
        $storage = $this->prepareLocalStorage([
            'a.txt' => 'AAA',
            'b.txt' => 'BBB',
            'c.txt' => 'CCC',
            'd.txt' => 'DDD',
            'z/z.zip' => [ 'foo.md' => 'FOO', 'boo' => 'BOO', 'loo' => 'LOO', 'zoo' => 'ZOO', 'bar/bar' => 'BAR'],
            'z2.zip' => [ 'job.log' => 'failed', 'config.yaml' => 'YAML' ]
        ]);

        // regular files
        $storage->move('a.txt', 'e.txt');
        $storage->move('b.txt', 'a.txt', true);
        $this->checkFileContents($storage, 'e.txt', 'AAA');
        $this->checkFileContents($storage, 'a.txt', 'BBB');
        Assert::exception(function () use ($storage) {
            $storage->move('a.txt', 'c.txt'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // regular to ZIP
        $storage->move('c.txt', 'z2.zip#c.txt');
        $storage->move('d.txt', 'z/z.zip#foo.md', true);
        $this->checkFileContents($storage, 'z2.zip#c.txt', 'CCC');
        $this->checkFileContents($storage, 'z/z.zip#foo.md', 'DDD');
        Assert::exception(function () use ($storage) {
            $storage->move('a.txt', 'z/z.zip#bar/bar'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");

        // ZIP to regular
        $storage->move('z2.zip#c.txt', 'newc.txt');
        $storage->move('z/z.zip#bar/bar', 'bar.txt', true);
        $this->checkFileContents($storage, 'newc.txt', 'CCC');
        $this->checkFileContents($storage, 'bar.txt', 'BAR');
        Assert::exception(function () use ($storage) {
            $storage->move('z2.zip#job.log', 'a.txt'); // no overwrite
        }, FileStorageException::class, "File already exists.");

        // within one ZIP
        $storage->move('z/z.zip#foo.md', 'z/z.zip#goo/foo.md');
        $storage->move('z/z.zip#boo', 'z/z.zip#loo', true);
        $this->checkFileContents($storage, 'z/z.zip#goo/foo.md', 'DDD');
        $this->checkFileContents($storage, 'z/z.zip#loo', 'BOO');
        Assert::exception(function () use ($storage) {
            $storage->move('z2.zip#job.log', 'z2.zip#config.yaml'); // no overwrite
        }, FileStorageException::class, "Unable to rename an entry 'job.log' to 'config.yaml' in the ZIP archive.");

        // from ZIP to another ZIP
        $storage->move('z/z.zip#goo/foo.md', 'z2.zip#foo.md');
        $storage->move('z/z.zip#loo', 'z2.zip#job.log', true);
        $this->checkFileContents($storage, 'z2.zip#foo.md', 'DDD');
        $this->checkFileContents($storage, 'z2.zip#job.log', 'BOO');
        Assert::exception(function () use ($storage) {
            $storage->move('z2.zip#job.log', 'z/z.zip#zoo'); // no overwrite
        }, FileStorageException::class, "Target entry already exists.");

        // to newly created ZIP
        $storage->move('e.txt', 'new.zip#e.txt');
        $this->checkFileContents($storage, 'new.zip#e.txt', 'AAA');

        // overwrite entire ZIP
        $storage->move('new.zip#e.txt', 'z2.zip', true);
        $this->checkFileContents($storage, 'z2.zip', 'AAA');

        // nonexist file
        Assert::exception(function () use ($storage) {
            $storage->move('nonexist', 'new.zip#unicorn'); // no overwrite
        }, FileStorageException::class, "File not found within the storage.");
        Assert::exception(function () use ($storage) {
            $storage->move('z', 'new.zip#unicorn'); // no overwrite
        }, FileStorageException::class, "Given path refers to a directory.");
    }

    public function testLocalFileStorageStoreExtract()
    {
        $storage = $this->prepareLocalStorage([
            'foo/bar/a.txt' => 'AAA',
            'foo/bar/b.txt' => 'BBB',
            'zip' => [ 'foo' => 'FOO', 'bar' => 'BAR', 'keeper' => 'placeholder' ],
        ]);
        $root = $storage->getRootDirectory();

        $tmp = $this->createTmpFile('TMP');
        $storage->extract('foo/bar/a.txt', $tmp, true); // overwrite
        Assert::false(file_exists("$root/foo/bar/a.txt"));
        Assert::true(is_dir("$root/foo/bar"));
        Assert::equal('AAA', file_get_contents($tmp));
        unlink($tmp);

        $storage->extract('foo/bar/b.txt', $tmp);
        Assert::false(file_exists("$root/foo/bar/b.txt"));
        Assert::false(is_dir("$root/foo/bar"));
        Assert::false(is_dir("$root/foo"));
        Assert::equal('BBB', file_get_contents($tmp));

        $storage->extract('zip#foo', $tmp, true); // overwrite
        $storage->flush();
        Assert::null($storage->fetch('zip#foo'));
        Assert::equal('FOO', file_get_contents($tmp));
        unlink($tmp);

        $storage->extract('zip#bar', $tmp);
        Assert::null($storage->fetch('zip#bar'));
        Assert::equal('BAR', file_get_contents($tmp));
        Assert::true(file_exists("$root/zip"));

        Assert::exception(function () use ($storage, $tmp) {
            $storage->extract('zip#unicorn', $tmp, true);
        }, FileStorageException::class, "The ZIP archive is unable to open stream for entry 'unicorn'");

        Assert::exception(function () use ($storage, $tmp) {
            $storage->extract('x.txt', $tmp, true);
        }, FileStorageException::class, "File not found within the storage.");

        Assert::exception(function () use ($storage, $tmp) {
            $storage->extract('zip', $tmp); // no overwrite
        }, FileStorageException::class, "Target file exists.");

        Assert::true(is_dir($root));
    }

    public function testLocalFileStorageStoreDelete()
    {
        $storage = $this->prepareLocalStorage([
            'foo/bar/a.txt' => 'AAA',
            'foo/bar/b.txt' => 'BBB',
            'c.txt' => 'CCC',
            'zip' => [ 'foo' => 'FOO' ],
            'zip2' => [ 'job.log' => 'failed', 'config.yaml' => 'YAML' ]
        ]);
        $root = $storage->getRootDirectory();

        Assert::true($storage->delete('foo/bar/a.txt'));
        Assert::false($storage->delete('foo/bar/a.txt'));
        Assert::false(file_exists("$root/foo/bar/a.txt"));
        Assert::true(is_dir("$root/foo/bar"));

        Assert::true($storage->delete('foo/bar/b.txt'));
        Assert::false($storage->delete('foo/bar/b.txt'));
        Assert::false(file_exists("$root/foo/bar/b.txt"));
        Assert::false(is_dir("$root/foo/bar"));
        Assert::false(is_dir("$root/foo"));

        Assert::true($storage->delete('c.txt'));
        Assert::false($storage->delete('c.txt'));
        Assert::false(file_exists("$root/c.txt"));

        Assert::true($storage->delete('zip#foo'));
        Assert::false($storage->delete('zip#foo'));
        Assert::true(file_exists("$root/zip"));
        Assert::true($storage->delete('zip2'));
        Assert::false($storage->delete('zip2'));

        Assert::true(is_dir($root));
    }

    public function testLocalFileStorageStoreDeleteByFilter()
    {
        $storage = $this->prepareLocalStorage([
            'foo/bar/a.txt' => 'AA',
            'foo/bar/b.txt' => 'BBBB',
            'foo/bar/c.txt' => 'CCCCCC',
            'foo/bar/d.bin' => '---',
            'x.txt' => 'XXXXX',
        ]);
        $root = $storage->getRootDirectory();

        $deleted = $storage->deleteByFilter('foo', function ($file) {
            return !Strings::endsWith($file->getStoragePath(), '.txt')
                || $file->getSize() < 3;
        });

        Assert::equal(2, $deleted);
        Assert::true(file_exists("$root/foo/bar/a.txt"));
        Assert::false(file_exists("$root/foo/bar/b.txt"));
        Assert::false(file_exists("$root/foo/bar/c.txt"));
        Assert::true(file_exists("$root/foo/bar/d.bin"));
        Assert::true(file_exists("$root/x.txt"));
    }

    public function testUploadPartialFileChunk()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $partialFile = new UploadedPartialFile("foo", 9, $user);
        self::setPropertyOfObject($partialFile, 'id', '123');

        $storage = $this->prepareLocalStorage([]);
        $root = $storage->getRootDirectory();

        $manager = new FileStorageManager(
            $storage,
            $this->prepareHashStorage(),
            new TmpFilesHelper($this->tmpDir),
            ""
        );

        $inputTmpFile = $this->createTmpFile('ABCDE');
        PhpInputMock::init($inputTmpFile);

        $savedSize = $manager->storeUploadedPartialFileChunk($partialFile);
        Assert::equal(5, $savedSize);
        Assert::true(file_exists("$root/uploads/partial/123_foo_0"));
        Assert::equal('ABCDE', file_get_contents("$root/uploads/partial/123_foo_0"));
    }

    public function testAssemblePartialUploadChunks()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $partialFile = new UploadedPartialFile("foo", 9, $user);
        $uploadedFile = new UploadedFile("foo", new DateTime(), 9, $user, true);
        self::setPropertyOfObject($partialFile, 'id', '123');
        self::setPropertyOfObject($uploadedFile, 'id', '123');

        $files = [
            'uploads/partial/123_foo_0' => 'AAA',
            'uploads/partial/123_foo_1' => 'BB',
            'uploads/partial/123_foo_2' => 'CCCC',
        ];
        foreach ($files as $content) {
            $partialFile->addChunk(strlen($content));
        }

        $storage = $this->prepareLocalStorage($files);
        $root = $storage->getRootDirectory();
        $manager = new FileStorageManager(
            $storage,
            $this->prepareHashStorage(),
            new TmpFilesHelper($this->tmpDir),
            ""
        );

        $manager->assembleUploadedPartialFile($partialFile, $uploadedFile);
        foreach (array_keys($files) as $name) {
            Assert::false(file_exists("$root/$name"));
        }
        Assert::true(file_exists("$root/uploads/123_foo"));
        Assert::equal('AAABBCCCC', file_get_contents("$root/uploads/123_foo"));
    }

    public function testFileDigest()
    {
        $contents1 = 'abcdefghijklmnopqrstuvwxyz';
        $contents2 = '0123456789';
        $storage = $this->prepareLocalStorage([
            'foo.txt' => $contents1,
            'bar.zip' => [ 'foo.txt' => $contents2 ],
        ]);

        $file1 = $storage->fetch('foo.txt');
        Assert::equal(sha1($contents1), $file1->getDigest());

        $file2 = $storage->fetch('bar.zip#foo.txt');
        Assert::equal(sha1($contents2), $file2->getDigest());
    }

    public function testIsZipArchive()
    {
        $storage = $this->prepareLocalStorage([
            'foo.txt' => 'abc',
            'foo.zip' => 'abc',
            'bar.zip' => [ 'foo.txt' => 'abcde' ],
            'inner.zip' => [ 'foo.txt' => 'abcdef' ],
        ]);
        $storage->move('inner.zip', 'bar.zip#inner.zip');

        Assert::false($storage->fetch('foo.txt')->isZipArchive());
        Assert::false($storage->fetch('foo.zip')->isZipArchive());
        Assert::true($storage->fetch('bar.zip')->isZipArchive());
        Assert::false($storage->fetch('bar.zip#foo.txt')->isZipArchive());
        Assert::true($storage->fetch('bar.zip#inner.zip')->isZipArchive());
    }

    public function testGetZipEntries()
    {
        $storage = $this->prepareLocalStorage([
            'bad.zip' => 'abc',
            'foo.zip' => [ 'foo.txt' => 'abcde', 'bar.txt' => 'xyz' ],
            'inner.zip' => [ 'foo.txt' => 'abcde', 'bar.txt' => 'xyz' ],
        ]);
        $storage->move('inner.zip', 'bar.zip#inner.zip');

        Assert::equal([
            [ 'name' => 'foo.txt', 'size' => 5 ],
            [ 'name' => 'bar.txt', 'size' => 3 ],
        ], $storage->fetch('foo.zip')->getZipEntries());

        Assert::exception(
            function () use ($storage) {
                $storage->fetch('bar.zip#inner.zip')->getZipEntries();
            },
            FileStorageException::class
        );

        Assert::exception(
            function () use ($storage) {
                $storage->fetch('bad.zip')->getZipEntries();
            },
            FileStorageException::class
        );
    }
}

(new TestFileStorage())->run();
