<?php namespace Bbt\Acp;

use DateTime;
use MongoDB\BSON\ObjectID;

trait RenderGeneral
{
	protected function sIfMany($data)
	{
		$many = true;
		
		if( is_array($data) )
		{
			if( count($data)<=1 ) $many = false;
		}
		elseif( is_numeric($data) )
		{
			if( intval($data)<=1 ) $many = false;
		}
		
		return $many ? 's' : '';
	}

	protected function formatTimePeriod( ?int $seconds=null )
	{
		if( is_null($seconds) ) return '';

		if( $seconds > 1000000000 )
		{
			$seconds = time() - $seconds;
		}

		if( $seconds > 31536000 ) // one year
		{
			$units = [ round($seconds / 31536000, 1), 'year' ];
		}
		elseif( $seconds > 4320000 ) // month
		{
			$units = [ round($seconds / 2678000, 1), 'month' ];
		}
		elseif( $seconds > 1209600 )
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
		else
		{
			return $units[0].' '.$units[1];
		}
	}

	protected function formatTime($ts = null)
    {
        if( is_null($ts) )
        {
            $dt = new DateTime();
        }
        elseif($ts instanceof ObjectID )
        {
	        $dt = (new DateTime())->setTimestamp(hexdec(substr((string) $ts, 0, 8)));
        }
        /** @noinspection PhpUndefinedFieldInspection */
        elseif( is_object($ts) and property_exists($ts, '_id') and $ts->_id instanceof ObjectID )
        {
	        /** @noinspection PhpUndefinedFieldInspection */
	        $dt = (new DateTime())->setTimestamp(hexdec(substr((string) $ts->_id, 0, 8)));
        }
        else
        {
            $dt = (new DateTime())->setTimestamp(intval($ts));
        }

        return $dt->format('H:i:s M, j Y');
    }

    protected function formOptions(array $options, $nameSelect = null, $request = null, array $selected=[])
    {
        if( is_null($request) ) $request = $_REQUEST;

        foreach( $options as $value => $text )
        {
            $htmlOption = '<option value="'.$value.'"';

            if( (!is_null($nameSelect) and !is_null($request)) or $selected )
            {
                if(
                	(isset($request[$nameSelect]) and $request[$nameSelect]!='' and $request[$nameSelect] == $value)
	                or
	                ($selected and in_array($value, $selected)) )
                {
                    $htmlOption.= ' selected="selected"';
                }
            }

            $htmlOption.= '>'.$text.'</option>';

            echo($htmlOption);
        }
    }
}

