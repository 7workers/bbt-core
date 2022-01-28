<?php namespace Bbt;

use Memcached;

class Cache
{
	public static $enabled = true;

	public static $server = [
	    'host'  => '127.0.0.1',
        'port'  => 11211
    ];

	const KEY_TIME_SINCE_LAST_RUN = 'tslr';

	public static function getWithCb($key, $ttl, Callable $cbReadThrough=null)
	{
		if( !self::$enabled ) return $cbReadThrough();

		$cached = self::Memcache()->get($key);
		if( false!==$cached ) return $cached;
		if( is_null($cbReadThrough) ) return null;
		$value = $cbReadThrough();
		if( !is_null($value) and false!==$value ) self::Memcache()->set($key, $value, $ttl);
		return $value;
	}

	/**
	 * @return Memcached
	 */
	public static function Memcache()
	{
		static $memcache = null;

		if( !is_null($memcache) ) return $memcache;

		$memcache = new Memcached;
		$memcache->addServer(self::$server['host'], self::$server['port']);
		$memcache->setOption(Memcached::OPT_COMPRESSION, true);
		$memcache->setOption(Memcached::OPT_BINARY_PROTOCOL, true);

		return $memcache;
	}
	
	public static function get($key, callable $cache_cb = null, $cas_token = 0) { return self::Memcache()->get($key, $cache_cb, $cas_token);}
	public static function set($key, $value, $expiration = 0) { return self::Memcache()->set($key, $value, $expiration); }
	public static function touch($key, $expiration) { return self::Memcache()->touch($key, $expiration); }
	public static function delete($key, $time=0) { return self::Memcache()->delete($key, $time); }
	public static function increment($key, $offset = 1, $initial_value = 0, $expiry = 0) { return self::Memcache()->increment($key, $offset, $initial_value, $expiry); }
	public static function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0) { return self::Memcache()->decrement($key, $offset, $initial_value, $expiry); }
}
