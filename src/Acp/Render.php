<?php namespace Bbt\Acp;

use DateTime;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;

abstract class Render
{
	public static function sIfMany($data) :string
    {
		$many = true;
		
		if( is_array($data) )
		{
			if( count($data)<=1 ) $many = false;
		}
		elseif( is_numeric($data) )
		{
			if( (int) $data <= 1 ) $many = false;
		}
		
		return $many ? 's' : '';
	}

	public static function formatTimePeriod( ?int $seconds=null , ?int $fromTime=null) :string
    {
		if( is_null($seconds) ) return '';
		if( $seconds===0 ) return '0';

		if (is_null($fromTime)) {
		    $fromTime = time();
        }


		if( $seconds > 1000000000 )
		{
			$seconds = $fromTime - $seconds;
		}

		if( $seconds > 31536000 ) // one year
		{
			$units = [ round($seconds / 31536000, 1), 'year' ];
		}
		elseif( $seconds > 4320000 ) // month
		{
			$units = [ round($seconds / 2592000, 1), 'month' ];
		}
		elseif( $seconds > 604800 )
		{
			$units = [ round($seconds / 604800), 'week' ];
		}
		elseif( $seconds > 86400 ) // day
		{
			$units = [ round($seconds / 86400), 'day' ];
		}
		elseif( $seconds > 3600 ) //  hour
		{
			$units = [ round($seconds / 3600), 'hour' ];
		}
		elseif( $seconds > 60 )
		{
			$units = [ round($seconds / 60), 'minute' ];
		}
		else
		{
			$units = [ $seconds, 'second' ];
		}

		if( $units[0] > 1 )
		{
			return $units[0].' '.$units[1].'s';
		}

        return $units[0].' '.$units[1];
    }

	public static function formatTime($ts = null) :string
    {
        if( is_null($ts) )
        {
            $dt = new DateTime();
        }
        elseif($ts instanceof ObjectID )
        {
	        $dt = (new DateTime())->setTimestamp(hexdec(substr((string) $ts, 0, 8)));
        }
        elseif($ts instanceof UTCDateTime )
        {
        	$dt = $ts->toDateTime();
        }
        /** @noinspection PhpUndefinedFieldInspection */
        elseif( is_object($ts) and property_exists($ts, '_id') and $ts->_id instanceof ObjectID )
        {
	        /** @noinspection PhpUndefinedFieldInspection */
	        $dt = (new DateTime())->setTimestamp(hexdec(substr((string) $ts->_id, 0, 8)));
        }
        elseif( is_array($ts) and isset($ts['_id']) and $ts['_id'] instanceof ObjectID ) 
        {
	        /** @noinspection PhpUndefinedFieldInspection */
	        $dt = (new DateTime())->setTimestamp(hexdec(substr((string) $ts['_id'], 0, 8)));
        }
        elseif( $ts instanceof DateTime )
        {
            $dt = $ts;
        }
        else
        {
            $dt = (new DateTime())->setTimestamp((int) $ts);
        }

        return $dt->format('H:i:s M,j y');
    }

    public static function formOptions(array $options, $nameSelect = null, $request = null, array $selected=[]) :void
    {
        if( is_null($request) ) $request = $_REQUEST;

        foreach( $options as $value => $text )
        {
            $htmlOption = '<option value="'.$value.'"';

            if( $selected || ( !is_null($nameSelect) && !is_null($request)) )
            {
                $isSelectedInRequest = is_array(@$request[$nameSelect])
                    ? (in_array($value, $request[$nameSelect]))
                    : (@$request[$nameSelect] != '' && @$request[$nameSelect] ==  (string) $value);
                
                if(
                	$isSelectedInRequest
            ||
	                ($selected && in_array($value, $selected)) )
                {
                    $htmlOption.= ' selected="selected"';
                }
            }

            $htmlOption.= '>'.$text.'</option>';

            echo($htmlOption);
        }
    }

    public static function formatNumberShort( int $numeric ) :string
    {
        if( $numeric < 1000) return $numeric;

        if( $numeric >= 1000 and $numeric < 10000 ) return round($numeric / 1000, 1)."k";
        if( $numeric >= 10000 and $numeric < 1000000 ) return round($numeric / 1000)."k";
        if( $numeric >= 1000000 ) return round($numeric / 1000000, 1)."m";

        return $numeric;
    }
}