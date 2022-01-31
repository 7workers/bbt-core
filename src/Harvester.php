<?php namespace Bbt;

use Exception;

abstract class Harvester extends CliScript
{
	protected $_id;
	protected $ignoreFields = [
		'pid', 'usleepNextWorker', 'counter', 'cermitUrl',
        'parseErrors', 'resizeCanvas', 'lastUrlFetched', 'debug', 'stopHarvesting',
        'patterns', 'patternsRemove', 'patternsIgnorePost', 'startTime', '_id', 'handlerCurl'
	];

    protected $harvested   = [];
    protected $enabled;
    protected $lastUrlFetched;
    private   $errors      = [];
    private   $handlerCurl = null;
    protected $retryFetch  = 5;
    protected $timeout     = 60;

	/**
	 * @var int seconds to wait before next request when getting could not connect to TOR
	 */
	protected $sleepTorReconnect  = 4;
	private $lastStart;
	private $lastFinish;

	/**
	 * @return MongoDbCollection
	 */
	abstract protected function getStateCollection();
	protected function harvest()
	{
		// implement in child
	}

	protected function isHarvested($item)
	{
		return in_array($item, $this->harvested);
	}

	protected function loadState()
	{
		$dHarvester = $this->getStateCollection()->findById($this->_id);

        if (!empty($dHarvester)) {
            foreach ($dHarvester as $key => $value) {
                if (property_exists($this, $key) and !in_array($key, $this->ignoreFields)) {
                    $this->$key = $value;
                }
            }
        }
	}

	protected function saveState()
	{
		$dHarvested = get_object_vars($this);

        foreach ($dHarvested as $key => $value) {
            if (in_array($key, $this->ignoreFields))    unset($dHarvested[$key]);
            if (is_resource($value))                    unset($dHarvested[$key]);
        }

		$dHarvested = array_filter($dHarvested, function($x) {return !is_null($x);});

		unset($dHarvested['_id']);

		$this->getStateCollection()->updateById($this->_id, ['$set' => $dHarvested]);
	}

	/**
	 * @return bool
	 */
	protected function runMain()
	{
        try {
            $this->loadState();

            if ($this->enabled === false) return false;

            $this->lastStart = time();

            $this->harvest();

            $this->lastFinish = time();

            $this->saveState();
        } catch (ExceptionHarvester $e) {
            $this->logError($e->getMessage());

            $this->saveState();
        } catch (ExceptionHarvesterTimeout $e) {
            $this->logError('timeout');

            $this->saveState();
        }

        if (!is_null($this->handlerCurl)) {
            curl_close($this->handlerCurl);
            $this->handlerCurl = null;
        }

		return true;
	}

	/**
	 * @param      $url
	 * @param bool $noTor
	 * @param bool $returnMimeType
	 * @param bool $includeHeader
	 * @param bool $sslVerify
	 *
	 * @param bool $follow
	 *
	 * @param bool $enableCookie
	 *
	 * @return mixed
	 * @throws ExceptionHarvester
	 * @throws ExceptionHarvesterTimeout
	 */
	protected function fetchPage($url, $noTor=false, $returnMimeType=false, $includeHeader=false, $sslVerify=false, $follow=false, $enableCookie=false)
	{
		for($i=1; $i <= $this->retryFetch; $i++)
		{
			$this->lastUrlFetched = trim($url);

			$this->log__debug('FETCHING: '.$url);

			if( is_null($this->handlerCurl) ) $this->handlerCurl = curl_init();

			curl_setopt($this->handlerCurl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.".rand(3, 8).".".rand(2, 5).") Gecko/2009200".rand(1, 8)." Firefox/0.10.1");
			curl_setopt($this->handlerCurl, CURLOPT_URL, str_replace(' ', '%20', trim($url)));
			curl_setopt($this->handlerCurl, CURLOPT_ENCODING, '');
			curl_setopt($this->handlerCurl, CURLOPT_FOLLOWLOCATION, $follow);
			curl_setopt($this->handlerCurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->handlerCurl, CURLOPT_AUTOREFERER, true);
			curl_setopt($this->handlerCurl, CURLOPT_SSL_VERIFYPEER, $sslVerify); #require for https urls
			curl_setopt($this->handlerCurl, CURLOPT_TIMEOUT, $this->timeout);
			curl_setopt($this->handlerCurl, CURLOPT_HEADER, $includeHeader);

            if ($enableCookie) {
                curl_setopt($this->handlerCurl, CURLOPT_COOKIEFILE, "");
            }

            if (!$noTor) {
                curl_setopt($this->handlerCurl, CURLOPT_HTTPPROXYTUNNEL, 1);
                curl_setopt($this->handlerCurl, CURLOPT_PROXY, "localhost:9050");
                curl_setopt($this->handlerCurl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }

			$content = curl_exec($this->handlerCurl);
			$error = curl_errno($this->handlerCurl);

			$mime = curl_getinfo($this->handlerCurl, CURLINFO_CONTENT_TYPE);

            if (false !== strpos($mime, ';')) {
                $mime = trim(substr($mime, 0, strpos($mime, ';')));
            }

            if ($error === 0) {
                if ($returnMimeType) {
                    return [$content, $mime];
                } else {
                    return $content;
                }
            } elseif (!$noTor and $error === CURLE_COULDNT_CONNECT and $i < $this->retryFetch) {
                sleep($this->sleepTorReconnect);
            } elseif ($i == $this->retryFetch) //that was last try
            {
                if ($error === CURLE_OPERATION_TIMEOUTED) {
                    throw new ExceptionHarvesterTimeout();
                } elseif ($error === CURLE_COULDNT_CONNECT) {
                    throw new ExceptionHarvester('Could not connect');
                } else {
                    throw new ExceptionHarvester('Curl error(' . $error . '): ' . curl_error($this->handlerCurl));
                }
            }
		}
		throw new ExceptionHarvester('Could not connect');
	}

	/**
	 * @param $needle
	 * @param $content
	 *
	 * @return bool|string
	 * @throws ExceptionHarvester
	 */
	protected function cutBefore( $needle, $content)
	{
		$pos = stripos($content, $needle);

        if (false === $pos) {
            throw new ExceptionHarvester('needle not found in cutBefore():' . $needle);
        }

		$content = substr($content, $pos);

		return $content;
	}

	/**
	 * @param $needle
	 * @param $content
	 *
	 * @return bool|string
	 * @throws ExceptionHarvester
	 */
	protected function cutAfter( $needle, $content )
	{
		$pos = stripos($content, $needle);

        if (false === $pos) {
            throw new ExceptionHarvester('needle not found in cutAfter():' . $needle);
        }

		$content = substr($content, 0, $pos);

		return $content;
	}

	private function logError( $message )
	{
		$message = $message. ' LAST URL='.$this->lastUrlFetched;

		array_unshift($this->errors, '('.date('Y-m-d H:s').') '.$message);
		$this->errors = array_slice($this->errors, 0, 10);

		$this->log__error($this->_id. ' '.$message);
	}

}

class ExceptionHarvester extends Exception {}
class ExceptionHarvesterTimeout extends Exception {}