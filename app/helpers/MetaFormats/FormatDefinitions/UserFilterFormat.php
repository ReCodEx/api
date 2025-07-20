<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use ArrayAccess;

#[Format(UserFilterFormat::class)]
class UserFilterFormat extends MetaFormat implements ArrayAccess
{
    #[FQuery(new VString(), required: false)]
    public ?string $search;

    #[FQuery(new VString(), required: false)]
    public ?string $instanceId;

    #[FQuery(new VString(), required: false)]
    public ?string $roles;

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Offset to retrieve
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset ?? null;
    }

    /**
     * Offset to set
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * Offset to unset
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->$offset = null;
    }
}
