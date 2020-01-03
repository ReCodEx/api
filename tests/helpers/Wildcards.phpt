<?php

use App\Helpers\Wildcards;
use Tester\Assert;

require __DIR__ . "/../bootstrap.php";

Assert::same(["hello", "world"], iterator_to_array(Wildcards::splitPattern("hello,world")));
Assert::same(["hello", ""], iterator_to_array(Wildcards::splitPattern("hello,")));
Assert::same(["", "hello"], iterator_to_array(Wildcards::splitPattern(",hello")));
Assert::same(["hello", "{world,dude}"], iterator_to_array(Wildcards::splitPattern("hello,{world,dude}")));
Assert::same([""], iterator_to_array(Wildcards::splitPattern("")));

Assert::same([""], iterator_to_array(Wildcards::expandPattern("")));
Assert::same(["hello, world"], iterator_to_array(Wildcards::expandPattern("hello, world")));
Assert::same(["hello, world", "hello, dude"], iterator_to_array(Wildcards::expandPattern("hello, {world,dude}")));
Assert::same(
    ["hello, world", "hello, dude", "hi, world", "hi, dude"],
    iterator_to_array(Wildcards::expandPattern("{hello,hi}, {world,dude}"))
);
Assert::same(
    ["hello, world", "hi, world", "hey, world"],
    iterator_to_array(Wildcards::expandPattern("{hello,{hi,hey}}, world"))
);

Assert::true(Wildcards::match("*.c", "file.c"));
Assert::false(Wildcards::match("*.c", "file.h"));

Assert::true(Wildcards::match("{*.c,*.h}", "file.c"));
Assert::true(Wildcards::match("{*.c,*.h}", "file.h"));
Assert::false(Wildcards::match("{*.c,*.h}", "file.cpp"));

Assert::true(Wildcards::match("*{.c,.h}", "file.c"));
Assert::true(Wildcards::match("*{.c,.h}", "file.h"));
Assert::false(Wildcards::match("*{.c,.h}", "file.cpp"));

Assert::true(Wildcards::match("*.{c,h}", "file.c"));
Assert::true(Wildcards::match("*.{c,h}", "file.h"));
Assert::false(Wildcards::match("*.{c,h}", "file.cpp"));
