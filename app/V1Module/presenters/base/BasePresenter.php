<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\MetaFormatHelper;
use App\Helpers\Pagination;
use App\Model\Entity\User;
use App\Security\AccessToken;
use App\Security\Identity;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongHttpMethodException;
use App\Exceptions\NotImplementedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InternalServerException;
use App\Exceptions\FrontendErrorMappings;
use App\Security\AccessManager;
use App\Security\Authorizator;
use App\Model\Repository\Users;
use App\Helpers\UserActions;
use App\Helpers\Validators;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\AnnotationsParser;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\MetaRequest;
use App\Helpers\MetaFormats\RequestParamData;
use App\Helpers\MetaFormats\Type;
use App\Responses\StorageFileResponse;
use App\Responses\ZipFilesResponse;
use Nette\Application\Application;
use Nette\Http\IResponse;
use Nette\Utils\Arrays;
use Tracy\ILogger;
use ReflectionClass;
use ReflectionMethod;
use LogicException;
use ReflectionException;

class BasePresenter extends \App\Presenters\BasePresenter
{
    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var UserActions
     * @inject
     */
    public $userActions;

    /**
     * @var AccessManager
     * @inject
     */
    public $accessManager;

    /**
     * @var Application
     * @inject
     */
    public $application;

    /**
     * @var Authorizator
     * @inject
     */
    public $authorizator;

    /**
     * @var ILogger
     * @inject
     */
    public $logger;

    /** @var mixed Processed parameters from the request (MetaFormat or stdClass) */
    private mixed $requestFormatInstance;

    protected function formatPermissionCheckMethod($action)
    {
        return "check" . $action;
    }

    /**
     * Verify IP lock of given user.
     * @param User $user to be tested
     */
    protected function verifyUserIpLock(User $user)
    {
        if ($user->isIpLocked()) {
            // the user is bound to access ReCodEx from one IP only, at the moment
            $remoteAddr = $this->getHttpRequest()->getRemoteAddress();
            if (!$remoteAddr || !$user->verifyIpLock($remoteAddr)) {
                throw new ForbiddenRequestException(
                    "Forbidden Request - User is not allowed access from IP '$remoteAddr'.",
                    IResponse::S403_FORBIDDEN,
                    FrontendErrorMappings::E403_003__USER_IP_LOCKED,
                    [
                        'remoteAddress' => $remoteAddr,
                        'lockedAddress' => $user->getIpLockRaw(),
                        'expires' => $user->getIpLockExpiration(),
                    ]
                );
            }
        }
    }

    public function startup()
    {
        parent::startup();
        $this->application->errorPresenter = "V1:ApiError";

        try {
            $presenterReflection = new ReflectionClass($this);
            $actionMethodName = $this->formatActionMethod($this->getAction());
            $actionReflection = $presenterReflection->getMethod($actionMethodName);
        } catch (ReflectionException $e) {
            throw new NotImplementedException();
        }

        // client IP address checking

        /** @var ?Identity $identity */
        $identity = $this->getUser()->getIdentity();
        $user = $identity?->getUserData();
        if ($user) {
            $this->verifyUserIpLock($user);
        }

        // ACL-checking method
        $this->tryCall($this->formatPermissionCheckMethod($this->getAction()), $this->params);

        Validators::init();
        $this->processParams($actionReflection);

        $this->logger->log(var_export($this->getRequest(), true), ILogger::DEBUG);
    }

    protected function isRequestJson(): bool
    {
        return $this->getHttpRequest()->getHeader("content-type") === "application/json";
    }

    /**
     * @return User|null (null if no user is authenticated)
     */
    protected function getCurrentUserOrNull(): ?User
    {
        /** @var ?Identity $identity */
        $identity = $this->getUser()->getIdentity();
        return $identity?->getUserData();
    }

    /**
     * @return User
     * @throws ForbiddenRequestException
     */
    protected function getCurrentUser(): User
    {
        $user = $this->getCurrentUserOrNull();
        if ($user === null) {
            throw new ForbiddenRequestException();
        }
        return $user;
    }

    /**
     * @throws ForbiddenRequestException
     */
    protected function getAccessToken(): AccessToken
    {
        /** @var ?Identity $identity */
        $identity = $this->getUser()->getIdentity();

        if ($identity === null || $identity->getToken() === null) {
            throw new ForbiddenRequestException();
        }

        return $identity->getToken();
    }

    /**
     * @throws ForbiddenRequestException
     */
    protected function getCurrentUserLocale(): string
    {
        return $this->getCurrentUser()->getSettings()->getDefaultLanguage();
    }

    /**
     * Is current user in the given scope?
     * @param string $scope Scope ID
     * @return bool
     */
    protected function isInScope(string $scope): bool
    {
        /** @var ?Identity $identity */
        $identity = $this->getUser()->getIdentity();

        if (!$identity) {
            return false;
        }

        return $identity->isInScope($scope);
    }

    public function getMetaRequest(): MetaRequest | null
    {
        if ($this->requestFormatInstance === null) {
            throw new InternalServerException(
                "getMetaRequest() cannot be used without a format class defined for the endpoint."
            );
        }

        $request = parent::getRequest();
        return new MetaRequest($request, $this->requestFormatInstance);
    }

    private function processParams(ReflectionMethod $reflection)
    {
        $this->logger->log(var_export(MetaFormatHelper::debugGetAttributes($reflection), true), ILogger::DEBUG);

        // use a method specialized for formats if there is a format available
        $format = MetaFormatHelper::extractFormatFromAttribute($reflection);
        if ($format !== null) {
            $this->processParamsFormat($format);
            return;
        }

        // otherwise use a method for loose parameters
        $paramData = MetaFormatHelper::extractRequestParamData($reflection);
        $this->processParamsLoose($paramData);
    }

    private function processParamsLoose(array $paramData)
    {
        $formatInstanceArr = [];

        // validate each param
        foreach ($paramData as $param) {
            ///TODO: path parameters are not checked yet
            if ($param->type == Type::Path) {
                continue;
            }

            $paramValue = $this->getValueFromParamData($param);
            $formatInstanceArr[$param->name] = $paramValue;

            // this throws when it does not conform
            $this->logger->log(var_export($param, true), ILogger::DEBUG);
            $param->conformsToDefinition($paramValue);
        }

        // cast to stdClass
        $this->requestFormatInstance = (object)$formatInstanceArr;
    }

    private function processParamsFormat(string $format)
    {
        // get the parsed attribute data from the format fields
        $formatToFieldDefinitionsMap = FormatCache::getFormatToFieldDefinitionsMap();
        if (!array_key_exists($format, $formatToFieldDefinitionsMap)) {
            throw new InternalServerException("The format $format is not defined.");
        }

        // maps field names to their attribute data
        $nameToFieldDefinitionsMap = $formatToFieldDefinitionsMap[$format];

        ///TODO: handle nested MetaFormat creation
        $formatInstance = MetaFormatHelper::createFormatInstance($format);
        foreach ($nameToFieldDefinitionsMap as $fieldName => $requestParamData) {
            ///TODO: path parameters are not checked yet
            if ($requestParamData->type == Type::Path) {
                continue;
            }

            $value = $this->getValueFromParamData($requestParamData);

            if (!$formatInstance->checkedAssign($fieldName, $value)) {
                ///TODO: it would be nice to give a more detailed error message here
                throw new InvalidArgumentException($fieldName);
            }
        }

        // validate structural constraints
        if (!$formatInstance->validateStructure()) {
            throw new BadRequestException("All request fields are valid but additional structural constraints failed.");
        }

        $this->requestFormatInstance = $formatInstance;
    }

    /**
     * Calls either getPostField or getQueryField based on the provided metadata.
     * @param \App\Helpers\MetaFormats\RequestParamData $paramData Metadata of the request parameter.
     * @throws \App\Exceptions\InternalServerException Thrown when an unexpected parameter location was set.
     * @return mixed Returns the value from the request.
     */
    private function getValueFromParamData(RequestParamData $paramData): mixed
    {
        switch ($paramData->type) {
            case Type::Post:
                return $this->getPostField($paramData->name, required: $paramData->required);
            case Type::Query:
                return $this->getQueryField($paramData->name, required: $paramData->required);
            default:
                throw new InternalServerException("Unknown parameter type: {$paramData->type->name}");
        }
    }

    private function getPostField($param, $required = true)
    {
        $req = $this->getRequest();
        $post = $req->getPost();

        if ($req->isMethod("POST")) {
            // nothing to see here...
        } else {
            if ($req->isMethod("PUT") || $req->isMethod("DELETE")) {
                parse_str(file_get_contents('php://input'), $post);
            } else {
                throw new WrongHttpMethodException(
                    "Cannot get the post parameters in method '" . $req->getMethod() . "'."
                );
            }
        }

        if (array_key_exists($param, $post)) {
            return $post[$param];
        } else {
            if ($required) {
                throw new BadRequestException("Missing required POST field $param");
            } else {
                return null;
            }
        }
    }

    private function getQueryField($param, $required = true)
    {
        $value = $this->getRequest()->getParameter($param);
        if ($value === null && $required) {
            throw new BadRequestException("Missing required query field $param");
        }
        return $value;
    }

    protected function logUserAction($code = IResponse::S200_OK)
    {
        if ($this->getUser()->isLoggedIn()) {
            $remoteAddr = $this->getHttpRequest()->getRemoteAddress();
            $params = $this->getRequest()->getParameters();
            unset($params[self::ACTION_KEY]);
            $this->userActions->log($this->getAction(true), $remoteAddr, $params, $code);
        }
    }

    protected function sendSuccessResponse($payload, $code = IResponse::S200_OK)
    {
        $this->logUserAction($code);

        $resp = $this->getHttpResponse();
        $resp->setCode($code);
        $this->sendJson(
            [
                "success" => true,
                "code" => $code,
                "payload" => $payload
            ]
        );
    }

    /**
     * Special response for paginated contents. Sends items over with metadata about pagination, ordering, and filters.
     * @param array $items Items to be sent.
     * @param Pagination $pagination Object holding pagination metadata and ordering.
     * @param bool $sliceItems If true, a slice of items array is created using pagination data.
     *                         Otherwise, the items are expected to be already sliced.
     * @param int|null $totalCount total count of all resources
     * @param int $code response code
     */
    protected function sendPaginationSuccessResponse(
        array $items,
        Pagination $pagination,
        bool $sliceItems = false,
        int $totalCount = null,
        $code = IResponse::S200_OK
    ) {
        $this->sendSuccessResponse(
            [
                "items" => $sliceItems
                    ? array_slice(
                        array_values($items),
                        $pagination->getOffset(),
                        $pagination->getLimit() ? $pagination->getLimit() : null
                    )
                    : array_values($items),
                "totalCount" => ($totalCount === null) ? count($items) : $totalCount,
                "offset" => $pagination->getOffset(),
                "limit" => $pagination->getLimit(),
                "orderBy" => $pagination->getOriginalOrderBy(),
                "filters" => $pagination->getRawFilters(),
            ],
            $code
        );
    }

    protected function getPagination(...$params): Pagination
    {
        return new Pagination(...$params);
    }

    /**
     * Wrapper for special responses that send one file directly from the storage.
     * @param IImmutableFile $file to be sent
     * @param string $name under which the file is presented in download
     * @param string $contentType MIME
     * @param bool $forceDownload
     */
    protected function sendStorageFileResponse(
        IImmutableFile $file,
        string $name,
        string $contentType = null,
        bool $forceDownload = true
    ) {
        $this->logUserAction(200);
        $this->sendResponse(new StorageFileResponse($file, $name, $contentType, $forceDownload));
    }

    /**
     * Wrapper for special responses that prepare a new ZIP archive and send it over.
     * @param array $files indexed by original name (becomes zip entry) where values are local paths (strings)
     *                     or possibly IImmutableFile objects
     * @param string|null $name
     * @param bool $forceDownload
     */
    protected function sendZipFilesResponse(array $files, string $name = null, bool $forceDownload = true)
    {
        $this->logUserAction(200);
        $this->sendResponse(new ZipFilesResponse($files, $name, $forceDownload));
    }
}
