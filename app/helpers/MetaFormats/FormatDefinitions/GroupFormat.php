<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\MetaFormat;

/**
 * @format_def group
 */
class GroupFormat extends MetaFormat
{
    /**
     * @format uuid
     */
    public string $id;
    /**
     * @format uuid
     */
    public string $externalId;
    public bool $organizational;
    public bool $exam;
    public bool $archived;
    public bool $public;
    public bool $directlyArchived;
    /**
     * @format localizedText[]
     */
    public array $localizedTexts;
    /**
     * @format uuid[]
     */
    public array $primaryAdminsIds;
    /**
     * @format uuid?
     */
    public string $parentGroupId;
    /**
     * @format uuid[]
     */
    public array $parentGroupsIds;
    /**
     * @format uuid[]
     */
    public array $childGroups;
    /**
     * @format groupPrivateData
     */
    public $privateData;
    /**
     * @format acl[]
     */
    public array $permissionHints;
}
