<?php

namespace App\Helpers\ExternalLogin\CAS;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidArgumentException;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\UserData;
use App\Helpers\LdapUserUtils;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\CASMissingInfoException;
use Nette\Utils\Arrays;
use Nette\Utils\Validators;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Core\NodeAttribute;

/**
 * Login provider of Charles University, CAS - Centrální autentizační služba UK.
 * CAS is basically just LDAP database, but it has some specifics. We are not
 * allowed to have account with sufficient permissions to read password hashes
 * for other users like normal LDAP login services works. Instead, we have only
 * anonymous binding which don't allow us to read almost anything. So, user must
 * login by his UKCO (unique student/staff university number) and we bind into
 * LDAP by his credentials. If this succeeded, we know the credentials was correct
 * and we can read his email address and other information to save them into our
 * database. There is also function to find user by email, but it's not guaranteed
 * to be unique, so this method may fail (but very unlikely).
 */
class LDAPLoginService implements IExternalLoginService
{

    /** @var string Unique identifier of this login service, for example "cas-uk" */
    private $serviceId;

    /**
     * Gets identifier for this service
     * @return string Login service unique identifier
     */
    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    /**
     * @return string The LDAP authentication
     */
    public function getType(): string
    {
        return "default";
    }

    /** @var LdapUserUtils Ldap utilities for bindings and searching */
    private $ldap;

    /** @var string Name of LDAP field containing user mail address */
    private $emailField;

    /** @var string Name of LDAP field containing user first name */
    private $firstNameField;

    /** @var string Name of LDAP field containing user last name */
    private $lastNameField;

    /**
     * Constructor
     * @param string $serviceId Identifier of this login service, must be unique
     * @param array $ldapConnection Parameters for instantiating LdapUserUtils class
     * @param array $fields Name of LDAP fields containing requested metadata (email, first and last name)
     */
    public function __construct(string $serviceId, array $ldapConnection, array $fields)
    {
        $this->serviceId = $serviceId;
        $this->ldap = new LdapUserUtils($ldapConnection);

        // The field names of user's information stored in the CAS LDAP
        $this->emailField = Arrays::get($fields, "email", "mail");
        $this->firstNameField = Arrays::get($fields, "firstName", "firstName");
        $this->lastNameField = Arrays::get($fields, "lastName", "lastName");
    }

    /**
     * User can enter either his email or his UKCO identifier. If the user filled in the email address, then this
     * function will ask the CAS system for the UKCO bound to this email.
     * @param string $username Email or UKCO
     * @return string The UKCO
     * @throws WrongCredentialsException
     */
    public function ensureUKCO(string $username)
    {
        if (Validators::isEmail($username)) {
            $username = $this->getUKCO($username);
            if ($username === null) {
                throw new WrongCredentialsException(
                    "Email address '$username' cannot be paired with a specific user in CAS.",
                    FrontendErrorMappings::E400_120__WRONG_CREDENTIALS_LDAP_EMAIL_NOT_PAIRED,
                    ["email" => $username]
                );
            }
        }

        if (!Validators::isNumeric($username)) {
            throw new WrongCredentialsException(
                "The UKCO given by the user is not a number.",
                FrontendErrorMappings::E400_121__WRONG_CREDENTIALS_LDAP_UKCO_NOT_NUMBER
            );
        }

        return $username;
    }

    /**
     * Tries to find UKCO for the given email. The ID cannot be determined if there is no
     * person with this email or if there mare multiple people sharing the email.
     * @param string $email Email address of user, whose UKCO is requested
     * @return string|null
     */
    public function getUKCO(string $email)
    {
        return $this->ldap->findUserByMail($email, $this->emailField);
    }

    /**
     * Read user's data from the CAS UK, if the credentials provided by the user are correct.
     * @param array $credentials
     * @param bool $onlyAuthenticate If true, only ID is required to be valid in returned user data.
     * @return UserData Information known about this user
     * @throws InvalidArgumentException
     * @internal param string $username Email or identification number of the person
     * @internal param string $password User's password
     */
    public function getUser($credentials, bool $onlyAuthenticate = false): UserData
    {
        $username = Arrays::get($credentials, "username", null);
        $password = Arrays::get($credentials, "password", null);

        if ($username === null || $password === null) {
            throw new InvalidArgumentException(
                "The ticket or the client URL is missing for validation of the request."
            );
        }

        $ukco = $this->ensureUKCO($username);
        $data = $this->ldap->getUser($ukco, $password); // throws when the credentials are wrong

        return $this->getUserData($ukco, $data, $onlyAuthenticate);
    }

    /**
     * Convert the LDAP data to the UserData container
     * @param string $ukco
     * @param Node $data
     * @return UserData
     */
    public function getUserData($ukco, Node $data, bool $onlyAuthenticate = false): UserData
    {
        $email = LDAPHelper::getArray(
            $this->getValue($data->get($this->emailField))
        ); // throws when field is invalid or empty

        if ($onlyAuthenticate) {
            $firstName = $lastName = "";
        } else {
            $firstName = LDAPHelper::getScalar(
                $this->getValue($data->get($this->firstNameField))
            ); // throws when field is invalid or empty
            $lastName = LDAPHelper::getScalar(
                $this->getValue($data->get($this->lastNameField))
            ); // throws when field is invalid or empty
        }

        // we do not get this information about the degrees of the user
        return new UserData($ukco, $email, $firstName, $lastName, "", "");
    }

    /**
     * Get value of an LDAP attribute.
     * @param NodeAttribute $attribute The attribute
     * @return mixed                    The value
     * @throws CASMissingInfoException  If attribute is invalid (empty or null)
     */
    private function getValue(NodeAttribute $attribute)
    {
        if ($attribute->count() === 0) {
            throw new CASMissingInfoException();
        }

        return $attribute->current();
    }
}
