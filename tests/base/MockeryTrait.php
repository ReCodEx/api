<?php

trait MockeryTrait
{
    protected function tearDown()
    {
        Mockery::close();
    }
}
