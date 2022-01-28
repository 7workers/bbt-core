<?php namespace Bbt\Acp;

use Bbt\Api\ApiAccount;
use Exception;
use MongoDB\BSON\ObjectID;

abstract class User
{
	public static $usernameGuest = 'guest';

	public static $classNameInstantiate = 'Bbt\Acp\User'; // set this to child class, for example Fdt\Acp\User

	public $_id;
	public $arRoles = [];
	public $arGroups = [];
	public $name = '';
	public $email;

	protected $permissions = [];

	protected $idsApiAccountsPrimary = [];
	protected $idsApiAccountsSecondary = [];

	/**
	 * @var ApiAccount
	 */
	protected $withApiAccountId;

	abstract protected function loadFromStorage($id);

	public function __construct( $idOrDoc )
	{
        if (is_array($idOrDoc)) {
            $this->loadFromDoc($idOrDoc);
        } elseif ($idOrDoc !== self::$usernameGuest) {
            $this->loadFromDoc($this->loadFromStorage($idOrDoc));
        } else {
            $this->_id = self::$usernameGuest;
        }
	}

	/**
	 * @param bool|true  $returnPrimary
	 * @param bool|false $returnSecondary
	 *
	 * @return array array of assigned ApiAccount IDs
	 */
	public function getAssignedApiAccounts($returnPrimary=true, $returnSecondary=false) :array
	{
		$ids = [];

		if( $returnPrimary ) $ids = $this->idsApiAccountsPrimary;
		if( $returnSecondary ) $ids = array_merge($ids, $this->idsApiAccountsSecondary);

		return $ids;
	}

	/**
	 * @param null $acc ApiAccount object or other object with public _id filed as account ID
	 *
	 * @return $this
	 * @throws Exception
	 */
    public function withApiAccount($acc = null): self
    {
        if (is_null($acc)) {
            $this->withApiAccountId = null;
        } elseif (is_object($acc) && property_exists($acc, '_id')) {
            $this->withApiAccountId = $acc->_id;
        } elseif (!is_object($acc) || ($acc instanceof ObjectID)) {
            $this->withApiAccountId = $acc;
        } else {
            throw new Exception('Unknown account ID or object:' . var_export($acc, true));
        }

        return $this;
    }

    public function hasAnyRole(array $roles): bool
    {
        if( empty($roles) ) return false;

        if( array_intersect($roles, $this->arRoles) ) {
            return true;
        }
        return false;
    }

    public function hasRole(string $role): bool
    {
        if (in_array($role, $this->arRoles)) {
            return true;
        }

        return false;
    }

    public function hasAnyGroup(array $groups): bool
    {
        if( empty($groups) ) return false;

        if( array_intersect($groups, $this->arGroups) ) {
            return true;
        }
        return false;
    }

    public function canAccess($resource): bool
	{
		if( $this->hasRole('superuser') ) return true;

        if (is_string($resource)) {
            $literalToCheck = $resource;

            if (in_array($literalToCheck, $this->permissions)) return true;

            if (!is_null($this->withApiAccountId)) {
                if (in_array($this->withApiAccountId, $this->idsApiAccountsPrimary)) {
                    $accountType = '~PRIMARY';
                } elseif (in_array($this->withApiAccountId, $this->idsApiAccountsSecondary)) {
                    $accountType = '~SECONDARY';
                } else {
                    return false;
                }

                if (in_array($literalToCheck . $accountType, $this->permissions)) return true;
            }
        } else {
            if (is_object($resource)) {
                $class = get_class($resource);
                /** @noinspection PhpAssignmentInConditionInspection */
                if ($pos = strrpos($class, '\\')) $class = substr($class, $pos + 1);

                if ($this->canAccess($class)) return true;

                $parentClass = get_parent_class($resource);
                /** @noinspection PhpAssignmentInConditionInspection */
                if ($pos = strrpos($parentClass, '\\')) $parentClass = substr($parentClass, $pos + 1);

                if ($parentClass != $class && $this->canAccess($parentClass)) return true;
            }
        }

		return false;
	}

	/**
	 * @return User
	 */
	public static function getCurrent(): User
    {
		/** @var User $cached */
		static $cached;

		if( !is_null($cached) ) return $cached;

		$class = self::$classNameInstantiate;

		if( empty($_SESSION['AcpUser_username']) ) return new $class(self::$usernameGuest);

		$cached = new $class($_SESSION['AcpUser_username']);

		return $cached;
	}

	/**
	 * @param $dAcpUser
	 *
	 * @throws ExceptionAcpUserNotFound
	 */
	protected function loadFromDoc( array $dAcpUser ):void
	{
		
		if( empty($dAcpUser) ) throw new ExceptionAcpUserNotFound($this->_id);

        $this->_id      = $dAcpUser['_id'];
        $this->name     = @$dAcpUser['name'];
        if (!empty($dAcpUser['roles'])) {
            $this->arRoles = (array)@$dAcpUser['roles'];
        } else {
            $this->arRoles = [$dAcpUser['role']];
        }
        $this->arGroups = (array)@$dAcpUser['groups'];
        $this->email    = $dAcpUser['email'];

        $this->idsApiAccountsPrimary   = empty($dAcpUser['primaryApiAccounts'])     ? [] : $dAcpUser['primaryApiAccounts'];
        $this->idsApiAccountsSecondary = empty($dAcpUser['secondaryApiAccounts'])   ? [] : $dAcpUser['secondaryApiAccounts'];
        $this->permissions             = empty($dAcpUser['permissions'])            ? [] : $dAcpUser['permissions'];
	}
}

class ExceptionAcpUserNotFound extends Exception {}