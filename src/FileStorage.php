<?php namespace Bbt;

use Exception;
use MongoDB\BSON\ObjectID;

abstract class FileStorage
{
    public static $dirRootStorage    = __DIR__.'/../data';            // example: /home/pmt/var/data/files
    public static $dirTemp           = '/tmp';
    public static $urlDownloadScript = '/download.php';
    public static $saltDownload      = 'DownloadGateway_';
    public static $fetchTimeout      = 25;
    public static $withDtPrefix      = false;

	/**
	 * @param                        $urlFile
	 * @param \Bbt\MongoDbCollection $collection
	 *
	 * @return mixed
	 * @throws FileStorageException
	 */
	public static function fetchAndSave( $urlFile, MongoDbCollection $collection )
	{
		$urlSource = $urlFile;
		$fnameTmp = self::getTempName($collection);

        $context = stream_context_create(
            [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ]
        );

		$fs = @fopen($urlSource, 'rb', false, $context);
		$fd = fopen($fnameTmp, 'wb');

		if( $fs === false ) {
            unlink($fnameTmp);
		    throw new FileStorageException('error reading from URL='.$urlSource);
        }
		if( $fd === false ) {
            unlink($fnameTmp);
		    throw new FileStorageException('error opening destination file='.$fnameTmp);
        }

		stream_set_timeout($fs, self::$fetchTimeout);

		$bytesWritten = stream_copy_to_stream($fs, $fd);

		if( $bytesWritten == 0 ) {
            unlink($fnameTmp);
		    throw new FileStorageException('zero bytes transfered from URL='.$urlSource.' to='.$fnameTmp);
        }

		$dFile_new = [
			'url' => Db::ensureUtf($urlFile)
		];

		$res = $collection->assuredInsertOne($dFile_new);

        if ($res->getInsertedCount() !== 1) {
            unlink($fnameTmp);
            throw new FileStorageException('error writing database record for URL=' . $urlSource);
        }

		$idFile = $res->getInsertedId();

		$fnameStored = self::getFnameByIdAndMKDir((string) $idFile, $collection->getCollectionName());

        copy($fnameTmp, $fnameStored);

        unlink($fnameTmp);

		return $idFile;
	}

	/**
	 * @param $strId
	 * @param $collectionName
	 *
	 * @return string
	 * @throws FileStorageException
	 */
	public static function getFnameByIdAndMKDir( $strId, $collectionName ) :string
    {
        [$dirFull, $fnameLocal] = self::getDirAndFname($strId, $collectionName);

        if ( !file_exists($dirFull)) {
            $created = mkdir($dirFull, 0775, true);
            if ( !$created) throw new FileStorageException('Error creating dir: '.$dirFull);
            chmod($dirFull, 0775);
        }

		return $dirFull.'/'.basename($fnameLocal);
	}

	public static function getDirAndFname( string $id, string $collectionName ) :array
    {
        $fnameLocal = $id;
        $fnameLocal = strrev($fnameLocal);
        $fnameLocal = substr_replace($fnameLocal, '/', 1, 0);
        $fnameLocal = substr_replace($fnameLocal, '/', 3, 0);
        $fnameLocal = substr_replace($fnameLocal, '/', 5, 0);
        $fnameLocal = substr_replace($fnameLocal, '/', 7, 0);
        $fnameLocal = substr_replace($fnameLocal, '/', 9, 0);

        $dir = dirname($fnameLocal);

        if( self::$withDtPrefix ) {
            $dtPrefix = date('ym', (new ObjectId($id))->getTimestamp());
            $dir = $dtPrefix.'/'.$dir;
        }

        return [self::$dirRootStorage.'/'.$collectionName.'/'.$dir, $fnameLocal];
    }
	
	public static function getFnameById( string $id, string $collectionName ) :string
	{
		[$dirFull, $fnameLocal] = self::getDirAndFname($id, $collectionName);
		return $dirFull.'/'.basename($fnameLocal);
	}

	/**
     * @param string            $fnameTmp
     * @param MongoDbCollection $collection
     * @param array|null        $dFile
     *
     * @return ObjectID
     * @throws FileStorageException
     */
    public static function moveAndSave( string $fnameTmp, MongoDbCollection $collection, ?array $dFile=[] ) :ObjectID
    {
        if( !file_exists($fnameTmp) )       throw new FileStorageException('file not exists: '.$fnameTmp);

		$res = $collection->insertOne($dFile);

		if( $res->getInsertedCount()!==1 )  throw new FileStorageException('error writing database record for file='.$fnameTmp);

		$idFile = $res->getInsertedId();

		$fnameStored = self::getFnameById(strval($idFile), $collection->getCollectionName());

        self::ensureNestedDirs($fnameStored);

		copy($fnameTmp, $fnameStored);
		unlink($fnameTmp);

		return $idFile;
    }

	/**
	 * @param string $fnameLocal
	 *
	 * @throws FileStorageException
	 */
	public static function ensureNestedDirs( string $fnameLocal ) :void
    {
        $dir = dirname($fnameLocal);

        if ( !file_exists($dir)) {
            if ( !mkdir($dir, 0775, true) && !is_dir($dir)) throw new FileStorageException('Error creating dir: '.$dir);
            chmod($dir, 0775);
        }
    }

	private static function getTempName(MongoDbCollection $collection) :string
	{
		$fname = tempnam(self::$dirTemp, $collection->getCollectionName() );

		chmod($fname, 0664);

		return $fname;
	}

    /**
     * @param string   $fname
     * @param int      $canvasSize
     * @param int|null $quality
     *
     * @throws FileStorageException
     */
    public static function resizeImage( string $fname, int $canvasSize, ?int $quality=90 ) :void
	{
        $result = getimagesize($fname);

		if( false === $result ) throw new FileStorageException('file not image '.$fname);

        $widthOrig  = $result[0];
        $heightOrig = $result[1];
        $mime       = $result['mime'];

		if( 0 === $widthOrig * $heightOrig ) throw new FileStorageException('image size=0 '.$fname);

		if( $widthOrig <= $canvasSize and $heightOrig <= $canvasSize ) return;

		$width  = $canvasSize;
		$height = $canvasSize;

		$ratioOrig = $widthOrig / $heightOrig;

        if ($width / $height > $ratioOrig) {
            $width = $height * $ratioOrig;
        } else {
            $height = $width / $ratioOrig;
        }

		$gdTarget = imagecreatetruecolor( (int)$width, (int)$height );

		switch ($mime)
		{
			case 'image/jpeg':     $gd = @imagecreatefromjpeg($fname); break;
			case 'image/gif':      $gd = @imagecreatefromgif($fname); break;
			case 'image/png':      $gd = @imagecreatefrompng($fname); break;
			case 'image/webp':     $gd = @imagecreatefromwebp($fname); break;
			case 'image/x-ms-bmp': $gd = self::imagecreatefrombmp($fname); break;

			default: throw new FileStorageException('unknown mime:'.$fname.' MIME detected='.$mime);
		}

		if( !is_resource($gd) && !is_object($gd) ) throw new FileStorageException('error creating GD resource='.$fname.' MIME detected='.$mime);

        imagecopyresampled($gdTarget, $gd, 0, 0, 0, 0, (int)$width, (int)$height, $widthOrig, $heightOrig);

        imagejpeg($gdTarget, $fname, $quality);

		imagedestroy($gdTarget);
		imagedestroy($gd);
	}

	public static function getUrlDownload(string $id, MongoDbCollection $collection, int $ttlHours=10): string
	{
        $fid  = $id.'.'.$collection->getCollectionName();

        $url = self::$urlDownloadScript.'?fid='.$fid.'&hash='.self::getHashDownload($fid, time(), $ttlHours);

		return $url;
	}

	public static function getHashDownload(string $fid, int $time, $ttlHours): string
	{
        $hash = md5( self::$saltDownload.'_'.$fid.'_'.strtotime('+'.$ttlHours.' hours', $time) );
        $hash.= $time.'.'.$ttlHours;

		return $hash;
	}

    public static function isDownloadHashValid( string $fid, string $hash ) :bool
    {
        $lenHash  = 32;
        $posPoint = strpos($hash, '.', $lenHash);

        $time  = (int) substr($hash, $lenHash, $posPoint - $lenHash);
        $hours = (int) substr($hash, $posPoint + 1);

        if( strtotime('+'.((int) $hours).' hours', $time) < time() ) return false;

        $referenceHash = self::getHashDownload($fid, $time, $hours);

		return $referenceHash === $hash;
	}

	public static function imagecreatefrombmp( $p_sFile )
	{
		$file = fopen($p_sFile, "rb");

		$read = fread($file, 10);

		while( !feof($file) && ($read <> "") )
		{
			$read .= fread($file, 1024);
		}

		$temp = unpack("H*", $read);

		$hex = $temp[1];

		$header = substr($hex, 0, 108);

        if (substr($header, 0, 4) == "424d") {
            $header_parts = str_split($header, 2);
            $width        = hexdec($header_parts[19].$header_parts[18]);
            $height       = hexdec($header_parts[23].$header_parts[22]);
            unset($header_parts);
        } else {
            return false;
        }

		$x = 0;
		$y = 1;

		$image = imagecreatetruecolor($width, $height);

		if( $image === false ) return false;

		$body        = substr($hex, 108);
		$body_size   = (strlen($body) / 2);
		$header_size = ($width * $height);
		$usePadding  = ($body_size > ($header_size * 3) + 4);

        for ($i = 0; $i < $body_size; $i += 3) {
            if ($x >= $width) {
                if ($usePadding) $i += $width % 4;
                $x = 0;
                $y++;
                if ($y > $height) break;
            }

            $i_pos = $i * 2;
            $r     = hexdec($body[$i_pos + 4].$body[$i_pos + 5]);
            $g     = hexdec($body[$i_pos + 2].$body[$i_pos + 3]);
            $b     = hexdec($body[$i_pos].$body[$i_pos + 1]);

            $color = imagecolorallocate($image, $r, $g, $b);

            if ($color === false) return false;

            imagesetpixel($image, $x, $height - $y, $color);
            $x++;
        }

		unset($body);

		return $image;
	}
}

class FileStorageException extends Exception{};