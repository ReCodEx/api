<?php

namespace App\Exceptions;

class FrontendErrorMappings
{
    /** General accepted */
    public const E202_000__ACCEPTED = "202-000";

    /** General bad request */
    public const E400_000__BAD_REQUEST = "400-000";
    /** User '$user' has multiple specified emails ($emails) which are also registered locally in ReCodEx */
    public const E400_001__BAD_REQUEST_EXT_MULTIPLE_USERS_FOUND = "400-001";
    /** Cannot issue token with effective role '$effectiveRole' higher than the actual one '$role' */
    public const E400_002__BAD_REQUEST_FORBIDDEN_EFFECTIVE_ROLE = "400-002";
    /** Uploaded file name contains invalid characters */
    public const E400_003__UPLOADED_FILE_INVALID_CHARACTERS = "400-003";
    /** Invalid declared uploaded file size */
    public const E400_004__UPLOADED_FILE_INVALID_SIZE = "400-004";
    /** Per-partes upload is not completed yet */
    public const E400_005__UPLOADED_FILE_PARTIAL = "400-005";
    /** Entity version is too old (concurrent edits occured) */
    public const E400_010__ENTITY_VERSION_TOO_OLD = "400-010";

    /** Invalid credentials */
    public const E400_100__WRONG_CREDENTIALS = "400-100";
    /** The username or password is incorrect */
    public const E400_101__WRONG_CREDENTIALS_LOCAL = "400-101";
    /** Provided passwords do not match */
    public const E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH = "400-102";
    /** Your current password does not match */
    public const E400_103__WRONG_CREDENTIALS_CURRENT_PASSWORD_NOT_MATCH = "400-103";
    /** External authentication failed - no matching user found in ReCodEx a automated registration is not possible. */
    public const E400_104__EXTERNAL_AUTH_FAILED_USER_NOT_FOUND = "400-104";
    /** External authentication failed - unable to register new user because no role was provided. */
    public const E400_105__EXTERNAL_AUTH_FAILED_MISSING_ROLE = "400-105";

    /** General job config error */
    public const E400_200__JOB_CONFIG = "400-200";
    /** General exercise config error */
    public const E400_300__EXERCISE_CONFIG = "400-300";

    /** General exercise compilation error */
    public const E400_400__EXERCISE_COMPILATION = "400-400";
    /** File '$filename' is already defined by author of the exercise */
    public const E400_401__EXERCISE_COMPILATION_FILE_DEFINED = "400-401";
    /** Submitted files contains two or more files with the same name */
    public const E400_402__EXERCISE_COMPILATION_DUPLICATE_FILES = "400-402";
    /** None of the submitted files matched regular expression '$regex' in variable '$variable' */
    public const E400_403__EXERCISE_COMPILATION_VARIABLE_NOT_MATCHED = "400-403";
    /** Variable '$variable' was not provided on submit */
    public const E400_404__EXERCISE_COMPILATION_VARIABLE_NOT_PROVIDED = "400-404";
    /** File '$filename' in variable '$variable' could not be found among submitted files */
    public const E400_405__EXERCISE_COMPILATION_FILE_NOT_PROVIDED = "400-405";
    /** Name of the entry-point contains illicit characters */
    public const E400_406__EXERCISE_COMPILATION_BAD_ENTRY_POINT_NAME = "400-406";

    /** General error when manipulating with a group */
    public const E400_500__GROUP_ERROR = "400-500";
    /** The group is archived, so it cannot be updated */
    public const E400_501__GROUP_ARCHIVED = "400-501";
    /** Root group of an instance cannot be relocated under another group. */
    public const E400_502__GROUP_INSTANCE_ROOT_CANNOT_RELOCATE = "400-502";
    /** Relocation of a group would create a loop in the tree hierarchy (new parent is a child of a group or group itself). */
    public const E400_503__GROUP_RELOCATION_WOULD_CREATE_LOOP = "400-503";

    /** General unauthorized */
    public const E401_000__UNAUTHORIZED = "401-000";
    /** Token was not provided in request */
    public const E401_001__NO_TOKEN = "401-001";
    /** Token was provided, but was invalid */
    public const E401_002__INVALID_TOKEN = "401-002";

    /** General payment required */
    public const E402_000__PAYMENT_REQUIRED = "402-000";

    /** General forbidden */
    public const E403_000__FORBIDDEN = "403-000";
    /** Forbidden since the user account does not exist */
    public const E403_001__USER_NOT_EXIST = "403-001";
    /** Forbidden since the user account is disabled */
    public const E403_002__USER_NOT_ALLOWED = "403-002";
    /** Forbidden since the user has IP lock and trying to access from a different IP */
    public const E403_003__USER_IP_LOCKED = "403-003";

    /** General not found */
    public const E404_000__NOT_FOUND = "404-000";

    /** General method not allowed */
    public const E405_000__METHOD_NOT_ALLOWED = "405-000";

    /** General conflict */
    public const E409_000__CONFLICT = "409-000";
    /** The user attributes received from the CAS has no affiliation attributes that would allow registration in ReCodEx. Authenticated account does not belong to a student nor to an employee of MFF. */
    public const E409_100__CONFLICT_CAS_BAD_AFFILIATION = "409-100";
    /** The user attributes received from the CAS do not contain an email address, which is required. */
    public const E409_101__CONFLICT_CAS_EMAIL_MISSING = "409-101";
    /** The user attributes received from the CAS are incomplete. */
    public const E409_102__CONFLICT_CAS_ATTRIBUTES_INCOMPLETE = "409-102";

    /** General internal server error */
    public const E500_000__INTERNAL_SERVER_ERROR = "500-000";
    /** Cannot receive uploaded file '$filename' due to '$errorCode' */
    public const E500_001__CANNOT_RECEIVE_FILE = "500-001";
    /** General LDAP connection exception */
    public const E500_002__LDAP_CONNECTION = "500-002";
    /** General job config error */
    public const E500_100__JOB_CONFIG = "500-100";
    /** General exercise config error */
    public const E500_200__EXERCISE_CONFIG = "500-200";
    /** General exercise compilation error */
    public const E500_300__EXERCISE_COMPILATION = "500-300";

    /** General not implemented */
    public const E501_000__NOT_IMPLEMENTED = "501-000";
}
