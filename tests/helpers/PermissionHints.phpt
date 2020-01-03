<?php

use App\Helpers\PermissionHints;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . "/../bootstrap.php";

class Subject
{
}

class FakeAclModule
{
    public function canAction1(Subject $subject)
    {
        return true;
    }

    public function canAction2(Subject $subject)
    {
        return false;
    }

    public function canAction3(TestCase $subject)
    {
        return false;
    }
}

class TestPermissionHints extends TestCase
{
    public function testBasic()
    {
        $result = PermissionHints::get(new FakeAclModule(), new Subject());
        Assert::equal(["action1" => true, "action2" => false], $result);
    }

    public function testNotMatching()
    {
        $result = PermissionHints::get(new FakeAclModule(), new stdClass());
        Assert::equal([], $result);
    }
}

(new TestPermissionHints())->run();
