<?php namespace Bbt\Acp;

abstract class AjaxEndpoint
{
	public static $prettyJson = false;
	
	protected $checkUserAccess    = true;
	protected $isResponsePossible = false;

	abstract protected function respondMain();

    public function __construct()
    {
    	if( !$this->checkUserAccess )
	    {
            $this->isResponsePossible = true;    
	    	
            return;
	    }
    	
        $user = User::getCurrent();

        if( $this->isAccessible($user) )
        {
            $this->isResponsePossible = true;
        }

    }

	protected function isAccessible( User $user )
	{
		return $user->canAccess($this);
	}

    final public function respond()
    {
    	if( !$this->isRespondPossible() )
	    {
	    	$this->onRespondNotPossible();
	    	return;
	    }
    	
        $response = $this->respondMain();

        if( !is_null($response) )
        {
            if( is_array($response) or is_bool($response) )
            {
                header('Cache-Control: no-cache, must-revalidate');
                header('Content-type: application/json');
                
                if( static::$prettyJson )
                {
	                echo(json_encode($response, JSON_PRETTY_PRINT) );
                }
                else
                {
                    echo(json_encode($response));
                }
            }
            elseif( is_string($response) )
            {
                echo($response);
            }
            else
            {
                throw new \Exception('unknown response');
            }
        }
    }

    protected function isRespondPossible() { return $this->isResponsePossible;}
    protected function onRespondNotPossible()
    {
	    $response = ['ERROR' => 'Unable to respond'];
	    header('Cache-Control: no-cache, must-revalidate');
	    header('Content-type: application/json');
	    echo(json_encode($response));
	    die();
    }

}