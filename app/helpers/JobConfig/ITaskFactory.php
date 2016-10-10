<?php

namespace App\Helpers\JobConfig;


interface ITaskFactory {
    public function create(array $data): TaskConfig;
}
