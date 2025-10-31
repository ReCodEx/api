<?php

// @phpstan-ignore trait.unused
trait MockeryTrait
{
    protected function tearDown()
    {
        Mockery::close();
    }
}
