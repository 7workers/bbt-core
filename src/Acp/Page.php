<?php namespace Bbt\Acp;

use Bbt\Config;

abstract class Page
{
    public static $fnameTemplateMain = '_main.phtml';
    public static $urlBaseStaticContent = '//acp.project/_public';

	protected $isRenderPossible = false;
	protected $title;

    abstract protected function initMain();
    abstract protected function renderMain();
    abstract protected function javascriptMain();

    public function __construct()
    {
        $user = User::getCurrent();
        
        if( !$this->isAccessible($user) )
        {
	        $this->isRenderPossible = false;

		    if( $user->_id == $user::$usernameGuest )
		    {
		    	$urlLastRejected = $_SERVER["REQUEST_URI"];
		    	$hash = sha1($urlLastRejected.'~'.$_SERVER['REMOTE_ADDR'].'~'.Config::dir__projectRoot());
		    	
		    	setcookie('_U', $urlLastRejected, time()+600, '/');
		    	setcookie('_UH', $hash, time()+600, '/');

	            header('Location: /login.php');
			    die();
		    }

	        die('no access');
        }

	    $this->isRenderPossible = true;
	    
	    $this->initMain();
    }

	protected function isAccessible( User $user )
	{
		return $user->canAccess($this);
	}

    protected function isRenderPossible()
    {
        return $this->isRenderPossible;
    }

    protected function onRenderNotPossible()
    {
        echo('Page render not possible. Make sure you have permissions.');
    }

    public function render()
    {
        if (!$this->isRenderPossible()) {
            $this->onRenderNotPossible();
            return;
        }

        $bufferStatus = ob_get_status(true);

        if( !empty(@$bufferStatus[0]['buffer_used']) ) ob_end_flush();
        if( !ob_start('ob_gzhandler') ) ob_start();

        require(self::$fnameTemplateMain);

        ob_end_flush();
    }

	protected function loadBlockHere( $blockName, array $parameters=array(), /** @noinspection PhpUnusedParameterInspection */
	                                  $pageUrl='' )
    {
	    if( strpos($blockName,'/')===false )
	    {
		    $BLK = $blockName;
		    $path = '';
	    }
	    else
	    {
		    $pos = strrpos($blockName, '/');

		    $path = substr($blockName, 0, $pos);
		    $BLK = substr($blockName, $pos+1);
	    }

	    $imgLoading = '/_public/img/loading.gif';

	    $urlBLK = http_build_query(array_merge(['BLK'=>$BLK],$parameters));

	    $html = '<img class="BLK-load" alt="'.$path.'?'.$urlBLK.'" src="'.$imgLoading.'"/>';

	    echo($html);
    }

	protected function loadFrameHere( $frameName, array $parameters=array(), /** @noinspection PhpUnusedParameterInspection */
	                                  $pageUrl='' )
    {
	    if( strpos($frameName,'/')===false )
	    {
		    $FRM = $frameName;
		    $path = '';
	    }
	    else
	    {
		    $pos = strrpos($frameName, '/');

		    $path = substr($frameName, 0, $pos+1);
		    $FRM = substr($frameName, $pos+1);
	    }

	    $imgLoading = '/_public/img/loading.gif';

	    $urlFRM = http_build_query(array_merge(['FRM'=>$FRM],$parameters));

	    $html = '<img class="FRM-load" alt="'.$path.'?'.$urlFRM.'" src="'.$imgLoading.'"/>';

	    echo($html);
    }
    
    public function renderModals() {}


    protected function reloadPage()
    {
    	header("Refresh:0");
    	die();
    }
    
    protected function redirect($url)
    {
    	header('Location: '.$url);
	    die();
    }

	protected function redirectIfMissingParameters( array $arDefaultParameters)
	{
		$arUrlQuery = [];

		$redirect = false;

		foreach( $arDefaultParameters as $parameter => $defaultValue )
		{
			if( !isset($_GET[$parameter])) $redirect = true;

			$arUrlQuery[$parameter] = $_GET[$parameter] ?? $defaultValue;
		}

		$arUrlQuery = array_merge($arUrlQuery, $_GET);

		if($redirect) $this->redirect('?'.http_build_query($arUrlQuery));
	}
}