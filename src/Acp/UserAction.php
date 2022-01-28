<?php namespace Bbt\Acp;

use Bbt\MongoDbCollection;
use MongoDB\BSON\ObjectID;

use MongoDB\BSON\UTCDateTime;

use function Bbt\obj4db;

abstract class UserAction
{
	/**
	 * @var ObjectID
	 */
	public $_id;
	
	public $target;
	public $action;
	public $idUser;
	public $data;
    /**
     * @var UTCDateTime
     */
    public $ts;

	public const TARGET__EMAIL_TAG   = 'et';
	public const TARGET__API_KEY     = 'apk';
	public const TARGET__API_ACCOUNT = 'apa';
	public const TARGET__ACP_USER    = 'usr';

    public const ACTION__TAG_SET         = 'ts';
    public const ACTION__TAG_REMOVE      = 'tr';
    public const ACTION__CLASS_SET       = 'cs';
    public const ACTION__RESOLVE         = 're';
    public const ACTION__CREATE          = 'cr';
    public const ACTION__DELETE          = 'dl';
    public const ACTION__IGNORE          = 'ig';
    public const ACTION__ASSIGN          = 'as';
    public const ACTION__UN_ASSIGN       = 'un';
    public const ACTION__SET_TRUE        = 'on';
    public const ACTION__SET_FALSE       = 'of';
    public const ACTION__ENABLE          = 'en';
    public const ACTION__DISABLE         = 'ds';
    public const ACTION__SET_VALUE       = 'vl';
    public const ACTION__ADD_TO_SET      = 'ad';
    public const ACTION__REMOVE_FROM_SET = 'fs';
    public const ACTION__LOGIN           = 'li';
    public const ACTION__LOGOUT          = 'lo';
    public const ACTION__REQUEST         = 'rq';
    public const ACTION__PAGE_LOAD       = 'pl';
    public const ACTION__COMPLETE        = 'co';
    public const ACTION__QUIT            = 'q';
    public const ACTION__START            = 'sta';
    public const ACTION__STOP            = 'sto';
    public const ACTION__SKIP            = 'skp';

	protected const mapReadableTarget = [
		// define this is child class
	];
	protected const mapReadableAction = [
		// define this is child class
	];
	
	private const _mapReadableTarget = [
		self::TARGET__ACP_USER => 'User',
		self::TARGET__API_KEY => 'api key',
		self::TARGET__API_ACCOUNT => 'api account',
	];

    private const _mapReadableAction
        = [
            self::ACTION__TAG_SET    => 'set tag',
            self::ACTION__TAG_REMOVE => 'remove tag',
            self::ACTION__COMPLETE   => 'complete',
            self::ACTION__PAGE_LOAD  => 'page load',
            self::ACTION__REQUEST    => 'request',
            self::ACTION__QUIT       => 'quit',
        ];


    abstract protected function getCollection() :MongoDbCollection;

	public function loadFromDoc(array $dUserActionsLog): void
    {
        $this->_id    = $dUserActionsLog['_id'];
        $this->target = $dUserActionsLog['target'];
        $this->action = $dUserActionsLog['action'];
        $this->idUser = $dUserActionsLog['idUser'];
        $this->data   = @$dUserActionsLog['data'];
    }

	final public function __construct(string $target, string $action, User $user)
	{
		$this->target = $target;
		$this->action = $action;
		$this->idUser = $user->_id;
		$this->ts = new UTCDateTime();
	}
	
	public function log():void
	{
		$dNew = obj4db($this);

		unset($dNew['_id']);
		
		$res = $this->getCollection()->insertOne($dNew);
		
		$this->_id = $res->getInsertedId();
	}

	public static function readableTarget( string $idTarget ) :string
	{
		return self::_mapReadableTarget[$idTarget] ?? static::mapReadableTarget[$idTarget] ?? $idTarget;
	}

	public static function readableAction( string $idAction ): string
	{
		return self::_mapReadableAction[$idAction] ?? static::mapReadableAction[$idAction] ?? $idAction;
	}
	
}