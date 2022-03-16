<?php namespace Bbt;

use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray;
use ReflectionClass;
use RuntimeException;

trait functions
{
    public static function diversity(array $arStrings)
    {
        if (empty($arStrings)) return 0;

        $nofStringInitially = count($arStrings);

        $arGroups = [];

        foreach ($arStrings as $stringId => $string) {
            foreach ($arGroups as $groupId => $arGroupMembers) {
                foreach ($arGroupMembers as $groupMember_each) {
                    similar_text($string, $groupMember_each, $perc);

                    if ($perc >= 60) {
                        $arGroups[$groupId][] = $string;
                        unset($arStrings[$stringId]);
                        continue 3;
                    }
                }
            }

            $arGroups[] = [$string];
            unset($arStrings[$stringId]);
        }

        return round(100 * count($arGroups) / $nofStringInitially);
    }
}

function ts_from_objectID( $ObjectId )
{
	return hexdec(substr(strval($ObjectId), 0, 8));
}

function get_class_nons($objectOrString)
{
	if( is_object($objectOrString) )
	{
		$reflect = new ReflectionClass($objectOrString);
		return $reflect->getShortName();
	}

	/** @noinspection PhpAssignmentInConditionInspection */
	if ($pos = strrpos($objectOrString, '\\')) return substr($objectOrString, $pos + 1);

	return $objectOrString;
}


/**
 * Convert md5 sum to base64 URL-friendly
 * @param $string
 * @return string
 */
function md5_base64($string)
{
	return str_replace(['+','/','='],['-','_',''],base64_encode(md5($string,true)));
}

function createMongoId( int $timestamp ) :string 
{
    static $inc = 0;

    $ts    = pack('N', $timestamp);
    $m     = substr(md5(gethostname()), 0, 3);
    $pid   = pack('n', posix_getpid());
    $trail = substr(pack('N', $inc++), 1, 3);

    $bin = sprintf("%s%s%s%s", $ts, $m, $pid, $trail);

    $id = '';

    for ($i = 0; $i < 12; $i++ ) $id .= sprintf("%02X", ord($bin[$i]));

    return $id;
}

function translate_text( string $text, string $apiKey ) :?string
{
    $url = 'https://www.googleapis.com/language/translate/v2?q='.urlencode($text).'&target=EN&key='.$apiKey;

    $ret = @file_get_contents($url);

    $arResult = @json_decode($ret, true);

    if( !empty($arResult) and !empty($arResult['data']) and !empty($arResult['data']['translations']) )
    {
        $translation = current($arResult['data']['translations']);
        
        if( $translation['detectedSourceLanguage'] == 'en' ) return null;

        return $translation['translatedText'];
    }

    return null;
}

function html2txt( string $html, $width=80 ) :?string
{

    // if no html inside $html no translates
    if (!preg_match("/<[^<]+>.*<\/[^<]+>/", $html,$m)) return wordwrap($html, $width);

    $descriptor = [
        0 => [ "pipe", "r" ],
        1 => [ "pipe", "w" ],
        // 2 => array("file", "/tmp/error-output.txt", "a") 
    ];

    $pipes = [];

    $process = proc_open('elinks -eval "set document.browse.margin_width=0" -dump -dump-width '.$width, $descriptor, $pipes, null, ['LANG' => 'en_US.UTF-8']);

    if( !is_resource($process) ) return null;

    fwrite($pipes[0], $html);
    fclose($pipes[0]);

    $txt = stream_get_contents($pipes[1]);
    
    fclose($pipes[1]);
    proc_close($process);

    return $txt;
}

function getSubjectAndReplyFromOurAutomatedMessage(string $htmlText, ?string $plainText) : array {
    $subject = null;
    $reply = null;

    $text = $plainText ?? html2txt($htmlText, 140);

    if (preg_match('/Subject\s+(.*)/', $text, $matches)) {
        $subject = $matches[1];
    }
    if (preg_match('/Message\s+(.*)/', $text, $matches)) {
        $reply = $matches[1];
    }

    return [$subject, $reply];
}

function getReplyFromEmailBody(array $patterns, ?string $html_body, ?string $text_body = null) : ?string  {
    // trim(): Passing null to parameter #1 ($string) of type string is deprecated (PHP 8.1)
    if ((trim($html_body??'').trim($text_body??'')) === "") return null;

    // We will use HTML body as first priority like in fetch mail script
    if (!empty($html_body)) {
        $text_body = html2txt($html_body, 150);
        if (trim($text_body??'') === "") return null;
    }

    $res = null;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text_body , $matches, PREG_OFFSET_CAPTURE)) {
                $res = trim(substr($text_body, 0, $matches[0][1]));
                break;
        }
    }
    if ((is_null($res)) && (strlen($text_body) < 100)) {
        $res = trim($text_body);
    }

    // removing automated Phones sign
    if (!is_null($res)) {
        $pattern = '/.*(Sent from.*)$/';
        $res = trim(preg_replace($pattern, '', $res));
    }

    return $res;
}

/**
 * @param array $array
 *
 * @return array removes null values from array
 */
function array_filter_null(array $array): array
{
    return array_filter($array, static function ($x) { return null !== $x; });
}

/**
 * 
 * Convert object or array to array suitable to be saved into DB (null fields gets removed)  
 * @param $obj
 *
 * @return array
 */
function obj4db( $obj ) :array
{
    if( is_object($obj)) $d = get_object_vars($obj);
    if( is_array($obj) ) $d = $obj;

    if( !isset($d) ) throw new RuntimeException('unknown object type');

    $d = array_map(static function ( $in ) {
        if( is_array($in) ) $in = array_map(static function ($in2) { if (!is_array($in2)) return $in2;if (empty($in2)) return null;$in2 = array_filter($in2, static function($x){if(null===$x)return false;if(is_array($x)&&empty($x))return false;return true;});if( empty($in2) ) return null;return $in2; }, $in);
        if (!is_array($in)) return $in;
        if (empty($in)) return null;
        $in = array_filter($in, static function($x){if(null===$x)return false;if(is_array($x)&&empty($x))return false;return true;});
        if( empty($in) ) return null;
        return $in;
    }, $d);

    return array_filter($d, static function ($x) { return null !== $x; });
}

function set_obj_from_array($o, array $a): void
{
    /** @noinspection DuplicatedCode */
    foreach ($a as $field => $value) {
        if ( !property_exists($o, $field)) continue;

        if (is_array($value)) {
            if(isset($value['$oid'])){$o->{$field}=new ObjectId($value['$oid']);continue;}
            foreach ($value as $fieldNested => $valueNested) {
                if(is_array($valueNested) && isset($valueNested['$oid'])){$o->{$field}[$fieldNested]=new ObjectId($valueNested['$oid']);continue;}
                if ($valueNested instanceof BSONArray) {
                    $o->{$field}[$fieldNested] = array_values($valueNested->getArrayCopy());
                    continue;
                }
                $o->{$field}[$fieldNested] = $valueNested;
            }
            continue;
        }
        $o->{$field} = $value;
    }
}

function hydrateFromJsonString(object $o, string $json): void
{
    $arr = json_decode($json, false);

    foreach (get_object_vars($arr) as $k => $v) {
        if (!property_exists($o, $k))  continue;

        if (is_object($v)) {
            if (is_array($o->{$k})) {
                foreach (get_object_vars($v) as $k2 => $v2) {
                    $o->{$k}[$k2] = $v2;
                }
                continue;
            }
        }

        $o->{$k} = $v;
    }
}

function json2mongoQuery( string $json ) :?array
{
	$query = json_decode($json, true);

	if( null===$query ) throw new RuntimeException('error parsing json');

    foreach ($query as $k => $v) {
        if (is_array($v)) {
            if(isset($v['$oid'])){$query[$k]=new ObjectId($v['$oid']);continue;}
            foreach ($v as $sK => $subV) {
                if (is_array($subV)) {
                    if(isset($subV['$oid'])){$query[$k][$sK]=new ObjectId($subV['$oid']);continue;}
                    foreach ($subV as $ssK => $subSubV) {
                        if (is_array($subSubV)) {
                            if(isset($subSubV['$oid'])){$query[$k][$sK][$ssK]=new ObjectId($subSubV['$oid']);continue;}
                            foreach ($subSubV as $sssK => $sssV) {
                                if (is_array($sssV)) {
                                    if(isset($sssV['$oid'])){$query[$k][$sK][$ssK][$sssK]=new ObjectId($sssV['$oid']);continue;}
                                }
                            }
                        }
                    }
                }
            }
        }
	}

	return $query;
}

function json2ifCondition(string $json): ?string
{
    //$json  = preg_replace('/(:\\s*)(\\/.+\\/)([iu]{0,3})(\\s*[},])/', '\1 "~__REGEX:\2 ~__MOD:\3" \4', $json);

    $dJson = json_decode($json, true);

    if( null===$dJson ) throw new RuntimeException('error parsing json');

    $arIfParts      = [];
    $variablePrefix = '$case->';

    foreach( $dJson as $key => $value ) {

        $suppressWarning = '';

        $posPoint = strpos($key, '.');

        if ($posPoint !== false) {
            $suppressWarning = '@';

            $key = substr($key, 0, $posPoint).'[\''.substr($key, $posPoint + 1).'\']';
            $key = str_replace('.', '\'][\'', $key);
        }

        if( $key === '$or' ) {
            throw new RuntimeException('$or not supported');
        }

        $key = $suppressWarning.$variablePrefix.$key;

        if (is_array($value)) {

            $arConditionParts = $value;
            $arNestedConditions = [];

            if (isset($arConditionParts['$in']) && is_array($arConditionParts['$in'])) {
                $arHaystack = '[\''.implode("','", $arConditionParts['$in']).'\']';

                $arNestedConditions[] = 'in_array('.$key.','.$arHaystack.')';
            }

            if (isset($arConditionParts['$gt']) && is_int($arConditionParts['$gt'])) {
                $arNestedConditions[] = $key.'>'.$arConditionParts['$gt'];
            }

            if (isset($arConditionParts['$gte']) && is_int($arConditionParts['$gte'])) {
                $arNestedConditions[] = $key.'>='.$arConditionParts['$gte'];
            }

            if (isset($arConditionParts['$lt']) && is_int($arConditionParts['$lt'])) {
                $arNestedConditions[] = $key.'<'.$arConditionParts['$lt'];
            }

            if (isset($arConditionParts['$lte']) && is_int($arConditionParts['$lte'])) {
                $arNestedConditions[] = $key.'<='.$arConditionParts['$lte'];
            }

            if (isset($arConditionParts['$nin']) && is_array($arConditionParts['$nin'])) {
                $arHaystack = '[\''.implode("','", $arConditionParts['$nin']).'\']';

                $arNestedConditions[] = '!in_array('.$key.','.$arHaystack.')';
            }

            if (isset($arConditionParts['$regex']) && is_string($arConditionParts['$regex'])) {
                $pattern   = str_replace('~', '\\\\~', $arConditionParts['$regex']);
                $modifiers = $arConditionParts['$options'] ?? '';

                $arNestedConditions[] = "preg_match('~{$pattern}~{$modifiers}',$key??'')";
            }

            if (isset($arConditionParts['$elemMatch']) && is_array($arConditionParts['$elemMatch'])) {
                if(array_diff( array_keys($arConditionParts['$elemMatch']), ['$eq', '$ne'])) {
                    throw new RuntimeException('only support $eq and $ne in $elemMatch');
                }

                if (isset($arConditionParts['$elemMatch']['$eq'])) {
                    $eqElemMatch = $arConditionParts['$elemMatch']['$eq'];

                    if( is_string($eqElemMatch) ) $eqElemMatch = "'{$eqElemMatch}'";

                    $arNestedConditions[] = 'in_array('.$eqElemMatch.',(array)'.$key.')';
                }

                if (isset($arConditionParts['$elemMatch']['$ne'])) {
                    $eqElemMatch = $arConditionParts['$elemMatch']['$ne'];

                    if( is_string($eqElemMatch) ) $eqElemMatch = "'{$eqElemMatch}'";

                    $arNestedConditions[] = '!in_array('.$eqElemMatch.',(array)'.$key.')';
                }

            }

            if (isset($arConditionParts['$exists']) && is_bool($arConditionParts['$exists'])) {

                if( preg_match('/\[([^]]+)]$/', $key, $matches) ) {
                    $keyChecking = $matches[1];
                    $arrayChecking = substr($key, 0, -(2+strlen($keyChecking)));
                    $arNestedConditions[] = ($arConditionParts['$exists'] ? '' : '!')."array_key_exists({$keyChecking},(array){$arrayChecking})";
                }
            }

            if (array_key_exists('$ne', $arConditionParts)) {
                $compareValue = $arConditionParts['$ne'];

                if (null === $compareValue) {
                    $compareValue_if = '!==null';
                } elseif (is_int($compareValue)) {
                    $compareValue_if = '!=='.$compareValue;
                } else {
                    $compareValue_if = '!==\''.str_replace("'", "\\'", $compareValue).'\'';
                }

                $arNestedConditions[] = $key.$compareValue_if;
            }


            if( empty($arNestedConditions) ) {
                if( substr(key($arConditionParts),0,1)==='$' ) {
                    throw new RuntimeException('error parsing condition part: '.json_encode($arConditionParts));
                }
                $arArrayCompareValue = '[\''.implode("','", $arConditionParts).'\']';
                $arIfParts[] = $key.'==='.$arArrayCompareValue.'';
                continue;
            } else {
                $arIfParts[] = implode(' && ', $arNestedConditions);
                continue;
            }

        } elseif( null === $value ) {
            $arIfParts[] = $key.'===null';
            continue;
        } elseif ( is_int($value) ) {
            $arIfParts[] = $key.'==='.$value.'';
            continue;
        } elseif ( is_bool($value) ) {
            $arIfParts[] = $key.'==='.($value?'true':'false').'';
            continue;
        } else {
            $arIfParts[] = $key.'===\''.str_replace("'", "\\'", $value).'\'';
            continue;
        }
    }

    usort($arIfParts, static function ($a, $b) {

        static $arPriorities = [
            '===null'    => 0,
            ']['         => 20,
            'in_array'   => 70,
            'preg_match' => 100,
        ];

        $priority_a = 10;
        $priority_b = 10;

        foreach ($arPriorities as $needle => $priority) {
            if (strpos($a, $needle) !== false) $priority_a = $priority;
            if (strpos($b, $needle) !== false) $priority_b = $priority;
        }

        return ($priority_a < $priority_b) ? -1 : 1;
    });

    return implode(' && ', $arIfParts);
}

/**
 * typical usage: getArrayValuebyPath( $dLogItem, explode('.','connectivity.primary.ip'))
 *
 * @param array $array
 * @param array $path
 *
 * @return array|mixed|null
 */
function getArrayValueByPath(array $array, array $path )
{
    $current = $array;

    foreach ($path as $key) {
        if ( !isset($current[$key])) return null;
        $current = $current[$key];
    }

    return $current;
}

/**
 * Returns IP in searchable / storable format (string)
 * example:
 * ac3a8ebb  - (for IPv4)
 * 26001702328052f07df1cb49550e4e30  - (for Ipv6)
 * @param $ip
 * @return string
 */
function ip2searchable($ip): string
{
    if (false !== $ip && is_int($ip)) $ip = long2ip($ip);

    if( !is_string($ip) ) throw new InvalidArgumentException('invalid IP format: '.$ip);

    if( strlen($ip) == 32 && strpos($ip,':')===false ) return $ip;

    if( strpos($ip, '.') !== false ) {
        $octets = explode('.', $ip);
        $ip   = str_pad(dechex($octets[0]), 2, '0', STR_PAD_LEFT).str_pad(dechex($octets[1]), 2, '0', STR_PAD_LEFT).str_pad(dechex($octets[2]), 2, '0', STR_PAD_LEFT).str_pad(dechex($octets[3]), 2, '0', STR_PAD_LEFT);
        return $ip;
    }

    if( strlen($ip) == 8 ) return $ip;

    $groups = explode(':', $ip);

    if (end($groups) == '') $groups = array_slice($groups, 0, -1);

    $ip = '';

    foreach ($groups as $group) {
        if ($group == '') {
            $ip .= str_repeat('0000', 9 - count($groups));
            continue;
        }
        $ip .= str_pad($group, 4, '0', STR_PAD_LEFT);
    }

    return $ip;
}

trait Hydratable {
    public function hydrate(mixed $d)
    {
        $ref = new \ReflectionClass($this);
        $properties = $ref->getProperties();

        foreach ($properties as $prop_each) {
            $name = $prop_each->getName();
            if( is_array($d) && !isset($d[$name])) continue;
            if( is_object($d) && !isset($d->{$name})) continue;

            $val = is_object($d) ? $d->{$name} : $d[$name];

            if( $prop_each->getType()->isBuiltin() ) {
                $this->{$name} = $val;
                continue;
            }

            $type = $prop_each->getType()->getName();

            if( $type == 'MongoDB\BSON\ObjectId' ) {
                $this->{$name} = new $type((string)$val);
                continue;
            }

            $this->{$name} = new $type();

            if( method_exists($type, 'hydrate') ) {
                $this->{$name}->hydrate($val);
                continue;
            }

            foreach ($val as $n => $m) {
                if( !property_exists($this->{$name}, $n) ) continue;
                $this->{$name}->{$n} = $m;
            }
        }
    }
}