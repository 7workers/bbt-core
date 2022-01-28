<?php namespace Bbt;

use Exception;
use MongoDB;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\WriteConcern;
use RuntimeException;

/**
 * @method static MongoDbCollection test_collection()
 * @method static MongoDbCollection var()
 */
abstract class Db
{
    public static $mongoReadPreference;
    public static $mongoDbConnectionString = '127.0.0.1';
    public static $writeConcernAll;

    protected static $mapCollection2Db = [
        'test_collection' => 'test_db',
        'var'             => 'bbt',
    ];

	private static $mapMinify = [
		'apiSignalSent'       => 'S',
		'apiUserId'           => 'U',
		'reasonCode'          => 'R',
		'email'               => 'E',
		'profileId'           => 'P',
		'recipientProfileId'  => 'P2',
		'countryCode'         => 'C',
		'profileCountryCode'  => 'Cp',
		'afpAccountId'        => 'A',
		'autoLinkedAccountId' => 'X',
		'idSubject'           => 'J',
		'subjectClass'        => 'JC',
		'languages'           => 'L',

		'description'         => 'des',
		'message'             => 'm',
		'catchPhrases'        => 'cp',
		'date'                => 'd',
		'emailMatched'        => 'Em',

		'whitelisted'         => 'w',
	];

	private static $mapDeminify = null;



    /**
     * @param $name
     * @param $arguments
     *
     * @return MongoDbCollection
     * @throws Exception
     */
    public static function __callStatic( $name, $arguments )
    {
        return self::getCollection($name);
    }

	/**
	 *
	 * @param string $collectionName
	 * @param null   $databaseName
	 * @return MongoDbCollection
	 * @throws Exception
	 * @internal param string $classNameWrapper
	 */
    public static function getCollection( $collectionName, $databaseName=null )
    {
        /** @var MongoDbCollection[] $collectionsCached */
        static $collectionsCached = [];

        if (is_null($databaseName)) {
            if (isset(static::$mapCollection2Db[$collectionName])) {
                $databaseName = static::$mapCollection2Db[$collectionName];
            } else {
                throw new RuntimeException('collection not mapped to db: '.$collectionName);
            }
        }

        $options = [
            'typeMap' => [
                'array'    => 'array',
                'document' => 'array',
                'root'     => 'array',
            ],
        ];

        if ( !is_null(static::$mongoReadPreference)) {
            $options['readPreference'] = static::$mongoReadPreference;
        }
        
        $manager = self::getManager();

        if (empty($collectionsCached[$collectionName.$databaseName])) {
            $coll = new MongoDbCollection($manager, $databaseName, $collectionName, $options);
            $collectionsCached[$collectionName.$databaseName] = $coll;
        }

        return $collectionsCached[$collectionName.$databaseName];
    }

	/**
	 * @param      $collectionName
	 * @param      $renameTo
	 * @param      $databaseName
	 * @param bool $dropTarget
	 *
	 * @return MongoDB\Driver\Cursor
	 */
	public static function renameCollection( $collectionName, $renameTo, $databaseName, $dropTarget = true )
    {
	    $c = new Command([
		    'renameCollection' => $databaseName.'.'.$collectionName,
		    'to'               => $databaseName.'.'.$renameTo,
		    'dropTarget'       => $dropTarget,
	    ]);
	    
	    return self::getManager()->executeCommand('admin', $c);
    }

    public static function createView( string $viewName, string $databaseName, string $collectionName, array $pipeline, ?array $options=[] )
    {
        $c = new Command(
            [
                'create'   => $viewName,
                'viewOn'   => $collectionName,
                'pipeline' => $pipeline,
            ]
        );

        return self::getManager()->executeCommand($databaseName, $c, $options);
    }

	/**
	 * @return Manager
	 */
	protected static function getManager()
    {
	    /**
	     * @var Manager
	     */
	    static $manager;

	    if( !empty($manager) ) return $manager;

	    $options = [
		    'typeMap'                => [
			    'array'    => 'array',
			    'document' => 'array',
			    'root'     => 'array',
		    ],
		    'ssl'                    => false,
		    'serverSelectionTryOnce' => false,
		    'socketTimeoutMS'        => 600000,
	    ];

	    // read preferences are broken now in driver
        //if( !is_null(static::$mongoReadPreference) ) $options['readPreference'] = static::$mongoReadPreference;
	    
	    $manager = new Manager('mongodb://'.static::$mongoDbConnectionString.'/?x='.posix_getpid(), $options);
	    
	    return $manager;
    }

    /**
     * @param string $string
     * @return string
     */
    public static function ensureUtf($string)
    {
        $enc = mb_detect_encoding($string);
        return mb_convert_encoding($string, $enc, 'UTF-8');
    }

	/**
	 * Minify document fields, for example turn 'idApiUser' into 'u' etc.
	 *
	 * @throws Exception
	 *
	 * @param $document
	 *
	 * @return array
	 */
	public static function minify( array &$document )
	{
        foreach ($document as $field => $value) {
            if (isset(self::$mapMinify[$field])) {
                $fieldRenameTo = self::$mapMinify[$field];

                if (!isset($document[$fieldRenameTo])) {
                    $document[$fieldRenameTo] = $value;
                    unset($document[$field]);

                    continue;
                }

                throw new Exception('cannot minify document, field ' . $fieldRenameTo . ' already present');
            }
        }

		return $document;
	}

	public static function deminify( array &$document )
	{
		!empty( self::$mapDeminify ) or self::initMapDeminify();

        foreach ($document as $field => $value) {
            if (isset(self::$mapDeminify[$field])) {
                $fieldRenameTo = self::$mapDeminify[$field];

                if (!isset($document[$fieldRenameTo])) {
                    $document[$fieldRenameTo] = $value;
                    unset($document[$field]);

                    continue;
                }

                throw new Exception('cannot de-minify document, field ' . $fieldRenameTo . ' already present');
            }
        }

		return $document;
	}

    public static function deminifyAll(array &$manyDocuments)
    {
        foreach ($manyDocuments as &$document) {
            self::deminify($document);
        }
    }

    private static function initMapDeminify()
    {
        foreach (self::$mapMinify as $fieldFrom => $fieldTo) {
            self::$mapDeminify[$fieldTo] = $fieldFrom;
        }
    }

    public static function loadObjectFromDoc($object, array $doc)
    {
        foreach ($doc as $field => $value) {
            if (property_exists($object, $field)) $object->{$field} = $value;
        }
    }

    public static function remapIds2Keys(array $arItems)
    {
        $ret = [];

        foreach ($arItems as $dItem) {
            $ret[(string)$dItem['_id']] = $dItem;
        }

        return $ret;
    }

    public static function getWriteConcernAll(): WriteConcern
    {
        if (null !== self::$writeConcernAll) return self::$writeConcernAll;

        self::$writeConcernAll = new WriteConcern(WriteConcern::MAJORITY);

        return self::$writeConcernAll;
    }

    public static function listCollections(string $dbName):array
    {
        $arCollectionNames = [];
        foreach ((new MongoDB\Database(self::getManager(), $dbName))->listCollections() as $collInfo_each) {
            $arCollectionNames[] = $collInfo_each->getName();
        }

        return $arCollectionNames;
    }
}