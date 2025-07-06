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
use App\Exceptions\InternalServerException;
use App\Exceptions\FrontendErrorMappings;
use App\Security\AccessManager;
use App\Security\Authorizator;
use App\Model\Repository\Users;
use App\Helpers\UserActions;
use App\Helpers\Validators;
use App\Helpers\FileStorage\IImmutableFile;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\RequestParamData;
use App\Helpers\MetaFormats\Type;
use App\Responses\StorageFileResponse;
use App\Responses\ZipFilesResponse;
use Nette\Application\Application;
use Nette\Http\IResponse;
use Tracy\ILogger;
use ReflectionClass;
use ReflectionMethod;
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

    /** @var MetaFormat Instance of the meta format used by the endpoint (null if no format used) */
    private MetaFormat $requestFormatInstance;

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
                    IResponse::S403_Forbidden,
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

    public function getFormatInstance(): MetaFormat
    {
        return $this->requestFormatInstance;
    }

    private function processParams(ReflectionMethod $reflection)
    {
        // use a method specialized for formats if there is a format available
        $format = MetaFormatHelper::extractFormatFromAttribute($reflection);
        if ($format !== null) {
            $this->requestFormatInstance = $this->processParamsFormat($format, null);
        }

        // handle loose parameters

        // cache the data from the loose attributes to improve performance
        $actionPath = get_class($this) . $reflection->name;
        if (!FormatCache::looseParametersCached($actionPath)) {
            $newParamData = MetaFormatHelper::extractRequestParamData($reflection);
            FormatCache::cacheLooseParameters($actionPath, $newParamData);
        }
        $paramData = FormatCache::getLooseParameters($actionPath);
        $this->processParamsLoose($paramData);
    }

    /**
     * Processes loose parameters. Request parameters are validated, no new data is created.
     * @param array $paramData Parameter data to be validated.
     */
    private function processParamsLoose(array $paramData)
    {
        // validate each param
        foreach ($paramData as $param) {
            $paramValue = $this->getValueFromParamData($param);

            // this throws when it does not conform
            $param->conformsToDefinition($paramValue);
        }
    }

    /**
     * Processes parameters defined by a format. Request parameters are validated and a format instance with
     *  parameter values created.
     * @param string $format The format defining the parameters.
     * @param ?array $valueDictionary If not null, a nested format instance will be created. The values will be taken
     *  from here instead of the request object. Format validation ignores parameter type (path, query or post).
     *  A top-level format will be created if null.
     * @throws InternalServerException Thrown when the format definition is corrupted/absent.
     * @throws BadRequestException Thrown when the request parameter values do not conform to the definition.
     * @return MetaFormat Returns a format instance with values filled from the request object.
     */
    private function processParamsFormat(string $format, ?array $valueDictionary): MetaFormat
    {
        // get the parsed attribute data from the format fields
        $formatToFieldDefinitionsMap = FormatCache::getFormatToFieldDefinitionsMap();
        if (!array_key_exists($format, $formatToFieldDefinitionsMap)) {
            throw new InternalServerException("The format $format is not defined.");
        }

        // maps field names to their attribute data
        $nameToFieldDefinitionsMap = $formatToFieldDefinitionsMap[$format];

        $formatInstance = MetaFormatHelper::createFormatInstance($format);
        foreach ($nameToFieldDefinitionsMap as $fieldName => $requestParamData) {
            $value = null;
            // top-level format
            if ($valueDictionary === null) {
                $value = $this->getValueFromParamData($requestParamData);
                // nested format
            } else {
                // Instead of retrieving the values with the getRequest call, use the provided $valueDictionary.
                // This makes the nested format ignore the parameter type (path, query, post) which is intended.
                // The data for this nested format cannot be spread across multiple param types, but it could be
                // if this was not a nested format but the top level format.
                if (array_key_exists($requestParamData->name, $valueDictionary)) {
                    $value = $valueDictionary[$requestParamData->name];
                }
            }

            // handle nested format creation
            // replace the value dictionary stored in $value with a format instance
            $nestedFormatName = $requestParamData->getFormatName();
            if ($nestedFormatName !== null) {
                $value = $this->processParamsFormat($nestedFormatName, $value);
            }

            // this throws if the value is invalid
            $formatInstance->checkedAssign($fieldName, $value);
        }

        // validate structural constraints
        if (!$formatInstance->validateStructure()) {
            throw new BadRequestException("All request fields are valid but additional structural constraints failed.");
        }

        return $formatInstance;
    }

    /**
     * Calls either getPostField, getQueryField or getPathField based on the provided metadata.
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
            case Type::Path:
                return $this->getPathField($paramData->name);
            case Type::File:
                return $this->getFileField(required: $paramData->required);
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

    private function getFileField($required = true)
    {
        $req = $this->getRequest();
        $files = $req->getFiles();

        if (count($files) === 0) {
            if ($required) {
                throw new BadRequestException("No file was uploaded");
            } else {
                return null;
            }
        } elseif (count($files) > 1) {
            throw new BadRequestException("Too many files were uploaded");
        }

        $file = array_pop($files);
        return $file;
    }

    private function getQueryField($param, $required = true)
    {
        $value = $this->getRequest()->getParameter($param);
        if ($value === null && $required) {
            throw new BadRequestException("Missing required query field $param");
        }
        return $value;
    }

    private function getPathField($param)
    {
        $value = $this->getParameter($param);
        if ($value === null) {
            throw new BadRequestException("Missing required path field $param");
        }
        return $value;
    }

    protected function logUserAction($code = IResponse::S200_OK)
    {
        if ($this->getUser()->isLoggedIn()) {
            $remoteAddr = $this->getHttpRequest()->getRemoteAddress();
            $params = $this->getRequest()->getParameters();
            unset($params[self::ActionKey]);
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
        ?int $totalCount = null,
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
        ?string $contentType = null,
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
    protected function sendZipFilesResponse(array $files, ?string $name = null, bool $forceDownload = true)
    {
        $this->logUserAction(200);
        $this->sendResponse(new ZipFilesResponse($files, $name, $forceDownload));
    }
}
