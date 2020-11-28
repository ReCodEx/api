<?php

namespace App\Exceptions;

class FrontendErrorMappings
{

    /** General accepted */
    const E202_000__ACCEPTED = "202-000";

    /** General bad request */
    const E400_000__BAD_REQUEST = "400-000";
    /** User '$user' has multiple specified emails ($emails) which are also registered locally in ReCodEx */
    const E400_001__BAD_REQUEST_EXT_MULTIPLE_USERS_FOUND = "400-001";
    /** Cannot issue token with effective role '$effectiveRole' higher than the actual one '$role' */
    const E400_002__BAD_REQUEST_FORBIDDEN_EFFECTIVE_ROLE = "400-002";
    /** Uploaded file name contains invalid characters */
    const E400_003__UPLOADED_FILE_INVALID_CHARACTERS = "400-003";

    /** Invalid credentials */
    const E400_100__WRONG_CREDENTIALS = "400-100";
    /** The username or password is incorrect */
    const E400_101__WRONG_CREDENTIALS_LOCAL = "400-101";
    /** Provided passwords do not match */
    const E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH = "400-102";
    /** Your current password does not match */
    const E400_103__WRONG_CREDENTIALS_CURRENT_PASSWORD_NOT_MATCH = "400-103";
    /** External authentication failed. */
    const E400_104__WRONG_CREDENTIALS_EXTERNAL_FAILED = "400-104";
    /** User authenticated through '$service' has no corresponding account in ReCodEx. Please register to ReCodEx first. */
    const E400_105__WRONG_CREDENTIALS_EXTERNAL_USER_NOT_FOUND = "400-105";
    /** User is already registered using '$service'. */
    const E400_106__WRONG_CREDENTIALS_EXTERNAL_USER_REGISTERED = "400-106";
    /** Email address '$email' cannot be paired with a specific user in CAS. */
    const E400_120__WRONG_CREDENTIALS_LDAP_EMAIL_NOT_PAIRED = "400-120";
    /** The UKCO given by the user is not a number. */
    const E400_121__WRONG_CREDENTIALS_LDAP_UKCO_NOT_NUMBER = "400-121";
    /** This account cannot be used for authentication to ReCodEx. The password is probably not verified. */
    const E400_122__WRONG_CREDENTIALS_LDAP_NOT_VERIFIED = "400-122";
    /** Too many unsuccessful tries. You won't be able to log in for a short amount of time. */
    const E400_123__WRONG_CREDENTIALS_LDAP_TOO_MANY_TRIES = "400-123";
    /** The ticket '$ticket' is not valid and does not belong to a CUNI student or staff or it was already used. */
    const E400_130__WRONG_CREDENTIALS_CAS_INVALID_TICKET = "400-130";
    /** The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete. */
    const E400_131__WRONG_CREDENTIALS_CAS_CORRUPTED_TICKET = "400-131";
    /** The ticket '$ticket' cannot be validated as the CUNI CAS service is unavailable. */
    const E400_132__WRONG_CREDENTIALS_CAS_UNAVAILABLE = "400-132";

    /** General job config error */
    const E400_200__JOB_CONFIG = "400-200";
    /** General exercise config error */
    const E400_300__EXERCISE_CONFIG = "400-300";

    /** General exercise compilation error */
    const E400_400__EXERCISE_COMPILATION = "400-400";
    /** File '$filename' is already defined by author of the exercise */
    const E400_401__EXERCISE_COMPILATION_FILE_DEFINED = "400-401";
    /** Submitted files contains two or more files with the same name */
    const E400_402__EXERCISE_COMPILATION_DUPLICATE_FILES = "400-402";
    /** None of the submitted files matched regular expression '$regex' in variable '$variable' */
    const E400_403__EXERCISE_COMPILATION_VARIABLE_NOT_MATCHED = "400-403";
    /** Variable '$variable' was not provided on submit */
    const E400_404__EXERCISE_COMPILATION_VARIABLE_NOT_PROVIDED = "400-404";
    /** File '$filename' in variable '$variable' could not be found among submitted files */
    const E400_405__EXERCISE_COMPILATION_FILE_NOT_PROVIDED = "400-405";
    /** Name of the entry-point contains illicit characters */
    const E400_406__EXERCISE_COMPILATION_BAD_ENTRY_POINT_NAME = "400-406";

    /** General error when manipulating with a group */
    const E400_500__GROUP_ERROR = "400-500";
    /** The group is archived, so it cannot be updated */
    const E400_501__GROUP_ARCHIVED = "400-501";
    /** Root group of an instance cannot be relocated under another group. */
    const E400_502__GROUP_INSTANCE_ROOT_CANNOT_RELOCATE = "400-502";
    /** Relocation of a group would create a loop in the tree hierarchy (new parent is a child of a group or group itself). */
    const E400_503__GROUP_RELOCATION_WOULD_CREATE_LOOP = "400-503";

    /** General unauthorized */
    const E401_000__UNAUTHORIZED = "401-000";
    /** Token was not provided in request */
    const E401_001__NO_TOKEN = "401-001";
    /** Token was provided, but was invalid */
    const E401_002__INVALID_TOKEN = "401-002";

    /** General payment required */
    const E402_000__PAYMENT_REQUIRED = "402-000";

    /** General forbidden */
    const E403_000__FORBIDDEN = "403-000";
    /** Forbidden since the user account does not exist */
    const E403_001__USER_NOT_EXIST = "403-001";
    /** Forbidden since the user account is disabled */
    const E403_001__USER_NOT_ALLOWED = "403-002";

    /** General not found */
    const E404_000__NOT_FOUND = "404-000";

    /** General method not allowed */
    const E405_000__METHOD_NOT_ALLOWED = "405-000";

    /** General conflict */
    const E409_000__CONFLICT = "409-000";
    /** The user attributes received from the CAS has no affiliation attributes that would allow registration in ReCodEx. Authenticated account does not belong to a student nor to an employee of MFF. */
    const E409_100__CONFLICT_CAS_BAD_AFFILIATION = "409-100";
    /** The user attributes received from the CAS do not contain an email address, which is required. */
    const E409_101__CONFLICT_CAS_EMAIL_MISSING = "409-101";
    /** The user attributes received from the CAS are incomplete. */
    const E409_102__CONFLICT_CAS_ATTRIBUTES_INCOMPLETE = "409-102";

    /** General internal server error */
    const E500_000__INTERNAL_SERVER_ERROR = "500-000";
    /** Cannot receive uploaded file '$filename' due to '$errorCode' */
    const E500_001__CANNOT_RECEIVE_FILE = "500-001";
    /** General LDAP connection exception */
    const E500_002__LDAP_CONNECTION = "500-002";
    /** General job config error */
    const E500_100__JOB_CONFIG = "500-100";
    /** General exercise config error */
    const E500_200__EXERCISE_CONFIG = "500-200";
    /** General exercise compilation error */
    const E500_300__EXERCISE_COMPILATION = "500-300";

    /** General not implemented */
    const E501_000__NOT_IMPLEMENTED = "501-000";
}
