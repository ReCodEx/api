<?php

namespace App\Helpers\Swagger;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\FormatDefinitions\SuccessResponseFormat;
use App\Helpers\MetaFormats\Validators\VObject;

class ResponseData
{
    public array $responseParams;
    public string $description;
    public int $statusCode;
    public bool $useSuccessWrapper;

    public function __construct(
        array $responseParams,
        string $description,
        int $statusCode,
        bool $useSuccessWrapper,
    ) {
        $this->responseParams = $responseParams;
        $this->description = $description;
        $this->statusCode = $statusCode;
        $this->useSuccessWrapper = $useSuccessWrapper;

        // wrap the response parameters in the wrapper defined in "BasePresenter::sendSuccessResponse"
        if ($this->useSuccessWrapper) {
            $this->wrapResponse();
        }
    }

    private function wrapResponse()
    {
        // get wrapper params
        $wrapperFieldDefinitions = FormatCache::getFieldDefinitions(SuccessResponseFormat::class);
        /** @var AnnotationParameterData[] */
        $wrapperParams = array_map(function ($data) {
            return $data->toAnnotationParameterData();
        }, $wrapperFieldDefinitions);

        // find payload param
        $payloadParam = null;
        foreach ($wrapperParams as $param) {
            if ($param->name === "payload") {
                $payloadParam = $param;
                break;
            }
        }
        if ($payloadParam === null) {
            throw new InternalServerException("The SuccessResponseFormat is corrupted (no 'payload' field).");
        }
    
        // wrap responseParams
        $payloadParam->nestedObjectParameterData = $this->responseParams;
        $payloadParam->swaggerType = VObject::SWAGGER_TYPE;
        $this->responseParams = $wrapperParams;
    }
}
