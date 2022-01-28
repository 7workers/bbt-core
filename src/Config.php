<?php namespace Bbt;

use Exception;

/**
 * Class Config
 *
 * @package Bbt
 *
 */
abstract class Config
{
	protected static $dir__projectRoot;

    public static $mongo
        = [
            'connection_string' => '127.0.0.1',
            'readPreference'    => null,
            'writeConcernAll'   => 1,
        ];

	public static $rabbitmqServer
        = [
            'host'      => '127.0.0.1',
            'login'     => 'guest',
            'password'  => 'guest'
        ];

	public static $cacheServer
        = [
            'host'      => '127.0.0.1',
            'port'      => 11211
        ];

	public static $dpf
		= [
			'directGetData' => false,
			'autoQueue'     => true,
		];

    public static $enableApiForwardToSelfNode = false;

    public static $selfNode = [
        'host' => 'self.passmetask.com:8082',
        'token' => ''
    ];

	public static $urlBaseStaticContent = '//acps.prg';

    public static $handleErrors = false;

    public static $forceHttpApiScheme = false;

	public static $acpUseSts = true;

    /**
     *
     * @param $dir__projectRoot
     *
     * @throws Exception
     */
    public static function init($dir__projectRoot): void
    {
        self::$dir__projectRoot = $dir__projectRoot;
    }

	public static function dir__projectRoot() :string { return self::$dir__projectRoot; }

    protected static function getFsPathRealAbsoluteDir( $fsRelativeOrAbsolute ) :string
    {
        $fsPath = (substr($fsRelativeOrAbsolute,0,1) == '/') ? $fsRelativeOrAbsolute : (self::$dir__projectRoot . '/' . $fsRelativeOrAbsolute);

        $realPath = realpath($fsPath);

        if( $realPath !== false ) return $realPath;


        if (!mkdir($fsPath, 0770, true) && !is_dir($fsPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $fsPath));
        }

        $realPath = realpath($fsPath);

        if( $realPath !== false ) return $realPath;

        throw new \RuntimeException('Failed dir accessing/creation: '.$fsPath);
    }

    protected static function getFsPathRealAbsoluteFile( $fsRelativeOrAbsolute ) :string
    {
        $fsPath = (substr($fsRelativeOrAbsolute,0,1) == '/') ? $fsRelativeOrAbsolute : (self::$dir__projectRoot . '/' . $fsRelativeOrAbsolute);

        $realPath = realpath($fsPath);

        if( $realPath !== false ) return $realPath;

        touch($fsPath);

        $realPath = realpath($fsPath);

        if( $realPath !== false ) return $realPath;

        $dir = dirname($fsPath);

        if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        touch($fsPath);

        $realPath = realpath($fsPath);

        if( $realPath !== false ) return $realPath;

        throw new \RuntimeException('Failed file accessing/creation: '.$fsPath);
    }

	public static function __callStatic( $name, $arguments )
	{
        if (strpos($name, 'dir__') === 0) {
            return self::getFsPathRealAbsoluteDir(static::$dirs[substr($name, 5)]);
        }

        if (strpos($name, 'fname__') === 0) {
            return self::getFsPathRealAbsoluteFile(static::$files[substr($name, 7)]);
        }

        throw new Exception('unknown method called: '.$name);
	}
}