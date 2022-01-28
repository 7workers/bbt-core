<?php namespace Bbt\Acp;

use Bbt\Config;use Bbt\MongoDbCollection;
use MongoDB\Driver\WriteConcern;
use PHPGangsta_GoogleAuthenticator;

abstract class LoginPage extends Page
{
	public static $emailFromForgotPassword = 'PassMeTask ACP <root@passmetask.com>';
	public static $useGoogleAuth = true;
	public static $gaAppNameForSecret = 'PassMeTask';
	public static $daysKeepCode = 30;
	public static $cookieSecretHash = '__RD';

	public static $useSts = true;
	public static $urlLogoImage;

    private $user;
    private $action = 'login';
    
    private $showCodeField = false;
    
    protected $title = 'ACP Login';
    
	public static $sbjEmailForgot = 'PassMeTask Admin password recovery';
	public static $tplEmailForgot = 'You or someone else requested password reset.<br>Follow this link to reset your password: {link}<br><br>If it wasn\'t you, ignore this message';
    
    abstract protected function getAcpUsersCollection() :MongoDbCollection;
    
	protected function initMain() :void
	{
	    if( self::$useSts and isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on' )
	    {
		    header('Strict-Transport-Security: max-age=5184000');
	    }
        elseif( self::$useSts )
	    {
		    header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], true, 301);
		    die();
	    }
	    
	    if( !empty( $_REQUEST['out'] ) )
	    {
	    	$this->onUserLogout();
	        
		    unset($_SESSION['AcpUser_username']);
		    
		    session_destroy();
		    setcookie(static::$cookieSecretHash, '');
            header('location: /login.php');
            die();
	    }

	    unset($_SESSION['AcpUser_username']);

	    if( !empty($_REQUEST['action'])) $this->action = $_REQUEST['action'];
	    
	    if( self::$useGoogleAuth )
        {
            if( empty($_COOKIE[self::$cookieSecretHash]) ) $this->showCodeField = true;
        }

        if( $this->action == 'reset' )
        {
            $hash = $_REQUEST['hash'];
            $users = $this->getAcpUsersCollection()->find([], ['sort' => ['_id' => 1]])->toArray();

            foreach( $users as $user )
            {
                if( $hash == sha1($user['_id'].$user['password'].floor(time() / 3600) * 3600) ||
                    $hash == sha1($user['_id'].$user['password'].floor((time() - 300) / 3600) * 3600))
                {
                    $this->user = $user['_id'];
                    break;
                }
            }

            if( !$this->user ) die('Password reset link is expired.');
        }

	    if( $this->action == 'secret' and (empty($_SESSION['_tmp_ga_qr']) or empty($_SESSION['_tmp_ga_secret'])) )
        {
            die('wrong request - ga not set');
	    }
	}
	
    /** @noinspection PhpMissingParentCallCommonInspection */
    public function render() :void
    {
        ?><!DOCTYPE html>
        <!--suppress HtmlUnknownTarget -->
        <html lang='en'>
        <head>
            <meta charset="UTF-8" />
            <title><?=$this->title?></title>
            <link rel="stylesheet" href="<?=Page::$urlBaseStaticContent?>/bs/css/bootstrap.min.css"  />
            <script src="<?=Page::$urlBaseStaticContent?>/js/jquery-3.1.1.min.js" ></script>
            <script src="<?=Page::$urlBaseStaticContent?>/bs/js/bootstrap.min.js" ></script>
        </head>
        <body>

        <div class="container-fluid">
            <div class="row" style="margin-top: 8%;">
                <div class="col-xs-2 col-xs-offset-5">
                    
                    <div class="well">
                        
                        <?
                        switch( $this->action )
                        {
                            case 'login':  $this->renderLoginStep();  break;
                            case 'forgot': $this->renderForgotStep(); break;
                            case 'reset':  $this->renderResetStep();  break;
                            case 'secret': $this->renderSecretStep(); break;
                            case 'wait_email': $this->renderWaitStep(); break;
                        }
                        ?>
                        
                    </div>
                </div>
            </div>
        </div>
        
        <?$this->javascriptMain()?>
        </body>
        </html>
        <?
    }
    
	protected function renderLoginStep() :void
	{
		?>
		<form class="form-horizontal" method="post">
            
            <? if( !empty(self::$urlLogoImage ) ):?>
                <p class="text-center" style="margin-bottom: 16px;">
                    <img src="<?=self::$urlLogoImage?>" style="border: none;"/>
                </p>
            <? endif;?>
            
            <p class="help-block text-center">
                ENTER USERNAME AND PASSWORD BELOW
            </p>
            
            <p id="pErrorLogin" class="help-block invisible text-center" style="color: red; text-transform: uppercase;">
                &nbsp;
            </p>

			<div class="form-group">
                <div class="col-xs-12">
				    <input id="inpUsername" name="username" type="text" class="form-control" placeholder="USERNAME" required />
                </div>
			</div>
            
            <div class="form-group">
                <div class="col-xs-12">
				    <input id="inpPassword" name="password" type="password" class="form-control" placeholder="PASSWORD" required />
                </div>
            </div>
            
            <div id="divCodeInput" class="form-group <?=$this->showCodeField?'':'hidden'?>">
                <div class="col-xs-12">
				    <input id="inpCode" name="code" type="text" class="form-control" placeholder="CODE" required />
                </div>
            </div>
            
            <div class="form-group">
                <div class="col-xs-12">
                    <button type="button" onclick="clkLogin()" class="btn btn-primary pull-right">LOGIN &gt;</button> 
                    <button type="button" onclick="window.location.href='/login.php?action=forgot'" class="btn btn-default pull-left" style="margin-right: 8px;">FORGOT ?</button>
                </div>
            </div>
            
		</form>
		<?
	}
	
	protected function renderForgotStep() :void
	{
		?>
		<form class="form-horizontal" method="post">
            
            <p class="help-block text-center" style="text-transform: uppercase;">
                Enter your email address below and we'll send you password reset instructions.
            </p>
            
            <p id="pErrorForgot" class="help-block invisible" style="color: red; text-transform: uppercase;">
                &nbsp;
            </p>

            <div class="form-group">
                <div class="col-xs-12">
                    <input id="inpEmail" name="email" type="email" class="form-control" placeholder="Email" required/>
                </div>
            </div>

            <div class="form-group">
                <div class="col-xs-8 col-xs-offset-4">
                    <button type="button" onclick="clkForgotPassword()" class="btn btn-primary pull-right">SEND</button>
                    <button type="button" onclick="window.location.href='/login.php'" class="btn btn-default pull-right"
                            style="margin-right: 8px;">&lt; LOGIN</button>
                </div>
            </div>
            
		</form>
		<?
	}
	
	protected function renderResetStep() :void
	{
		?>
		<form class="form-horizontal">
            
            <input type="hidden" id="inpHash" value="<?=@$_REQUEST['hash']?>"/>
            
            <p class="help-block text-center" style="text-transform: uppercase;">
                Enter new password
            </p>
            
            <p id="pErrorReset" class="help-block invisible" style="color: red;text-transform: uppercase;">
                &nbsp;
            </p>
            
            <div class="form-group">
                <div class="col-xs-12">
				    <input id="inpPassword1" name="password1" type="password" class="form-control" placeholder="New Password" required />
                </div>
            </div>
            
            <div class="form-group">
                <div class="col-xs-12">
				    <input id="inpPassword2" name="password2" type="password" class="form-control" placeholder="Confirm Password" required />
                </div>
            </div>
            
            <div class="form-group">
                <div class="col-xs-8 col-xs-offset-4">
                    <button type="button" onclick="clkResetPassword()" class="btn btn-primary pull-right">CONFIRM</button>
                    <button type="button" onclick="window.location.href='/login.php'" class="btn btn-default pull-right"
                            style="margin-right: 8px;">&lt; LOGIN</button>
                </div>
            </div>

		</form>
		<?
	}
	
	protected function renderSecretStep() :void
	{
		?>
        <div class="row">
            
            <div class="col-xs-6">
                <p>Scan QRCode with your phone app.</p>
                
                <img class="img-responsive" src="<?=$_SESSION['_tmp_ga_qr']?>" />
                
            </div>
            
            <div class="col-xs-6">
                
                <p>
                    Get Google Authenticator app:
                </p>
                
                <a class="img-thumbnail" href="https://itunes.apple.com/us/app/google-authenticator/id388497605" target="_blank">
                    <img class="img-responsive" src="/_public/img/appStore.png"/>
                </a><br/>
                
                <a class="img-thumbnail" href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank">
                    <img class="img-responsive" src="/_public/img/googlePlay.png"/>
                </a><br/>
                
                <a class="img-thumbnail" href="https://www.microsoft.com/en-us/store/apps/authenticator/9nblggh08h54" target="_blank">
                    <img class="img-responsive" src="/_public/img/windowsPhoneStore.png"/>
                </a><br/>
            </div>
            
            <div class="clearfix"></div>
            
            <div class="col-xs-2 col-xs-offset-5">
                <br/>
                <button onclick="window.location.href='?code=1'" class="btn btn-primary center-block">DONE</button>
            </div>
        </div>
        
        
		<?
	}
	
	private function renderWaitStep() :void
    {
        		?>
		<p>
            Email was sent to your address. Wait for it and follow instructions.
        </p>
        <div class="row">
            <button type="button" onclick="window.location.href='/login.php'" class="btn btn-default pull-right"
                                style="margin-right: 8px;">&lt; LOGIN</button>
            
        </div>
		<?
    }

	protected function javascriptMain() :void
	{
        ?>
        <script>
            
            function clkResetPassword() {
                
                var password1 = $('#inpPassword1').val();
                var password2 = $('#inpPassword2').val();
                var hash = $('#inpHash').val();
                var pError = $('#pErrorReset');
                
                if( password1.length === 0 || password1 !== password2 ) {
                    pError.css('visibility', 'visible');
                    pError.html('Passwords not match or empty');
                } else {
                    pError.css('visibility', 'invisible');
                    pError.html('&nbsp;');
                }
                
                $.ajax('?AJX=ResetPassword', {
                    data: { hash: hash, password : password1 },
                    success: function (data) {
                        
                        if( data['ERROR'] ) {
                            pError.css('visibility', 'visible');
                            pError.html(data['ERROR']);
                        } else {
                            pError.css('visibility', 'invisible');
                            pError.html('&nbsp;');
                        }
                        
                        if( data['redirect'] ) {
                            window.location.href = data['redirect'];
                        }
                    }
                });
                
            }
            
            function clkLogin() {
                
                var username = $('#inpUsername').val();
                var password = $('#inpPassword').val();
                var code = $('#inpCode').val();
                var pError = $('#pErrorLogin');
                
                $.ajax('?AJX=LoginAcpUser', {
                    data: { username : username, password : password, code: code},
                    success: function (data) {
                        
                        if( data['code'] ) {
                            $('#divCodeInput').css('visibility', 'visible');   
                        }
                        
                        if( data['ERROR'] ) {
                            pError.css('visibility', 'visible');
                            pError.html(data['ERROR']);
                        } else {
                            pError.css('visibility', 'invisible');
                            pError.html('&nbsp;');
                        }
                        
                        if( data['redirect'] ) {
                            window.location.href = data['redirect'];
                        }
                    }
                });
            }
            
            function clkForgotPassword() {
                
                var email = $('#inpEmail').val();
                var pError = $('#pErrorForgot');
                
                $.ajax('?AJX=ForgotPassword', {
                    data: { email : email },
                    success: function (data) {
                        
                        if( data['ERROR'] ) {
                            pError.css('visibility', 'visible');
                            pError.html(data['ERROR']);
                        } else {
                            pError.css('visibility', 'invisible');
                            pError.html('&nbsp;');
                        }
                        
                        if( data['redirect'] ) {
                            window.location.href = data['redirect'];
                        }
                    }
                });

            }
	        
        </script>
        <?
	}
	
	public static function startSession( $dUser ) :void 
	{
		$_SESSION['AcpUser_username'] = $dUser['_id'];
    }
	
	/** @noinspection PhpMissingParentCallCommonInspection
	 * @param User $user
	 * @return bool
	 */
	protected function isAccessible( User $user ) :bool { return true; }
	
	protected function renderMain() :void { }
	
	protected function onUserLogout() :void { }
}

abstract class LoginAcpUser extends AjaxEndpoint
{
    protected $checkUserAccess = false;
    
    abstract protected function getAcpUsersCollection() :MongoDbCollection;
    
    protected function respondMain() :array 
    {
        if( empty($_REQUEST['username']) or empty($_REQUEST['password']) ) return ['ERROR' => 'Wrong request'];

        $dUser = $this->getAcpUsersCollection()->findOne(['_id' => $_REQUEST['username'], 'password' => md5($_REQUEST['password'])]);

	    if( !$dUser ) return ['ERROR' => 'Username or password incorrect'];
	    
	    if( isset($dUser['isSuspended']) ) return [ 'ERROR' => 'Account suspended' ];
	    
	    if( !LoginPage::$useGoogleAuth )
        {
            LoginPage::startSession($dUser);

	        $this->getAcpUsersCollection()->updateById(
		        $dUser['_id'], [ '$set' => [ 'lastLogin' => time() ] ], [ 'writeConcern' => new WriteConcern(0) ]
	        );
	        
            return [ 'redirect' => $this->getSuccessRedirectUrl() ];
        }
	    
        if( empty($dUser['secret']) and empty($_SESSION['_tmp_ga_secret']) )
        {
            $gAuth = new PHPGangsta_GoogleAuthenticator();
            $secret = $gAuth->createSecret();
            $QRCode = $gAuth->getQRCodeGoogleUrl($dUser['_id'].'@'.LoginPage::$gaAppNameForSecret, $secret);

	        $_SESSION['_tmp_ga_secret'] = $secret;
	        $_SESSION['_tmp_ga_qr']     = $QRCode;
            
            return [ 'redirect' => '/login.php?action=secret' ];
        }

        $userSecret = !empty($dUser['secret']) ? $dUser['secret'] : @$_SESSION['_tmp_ga_secret'];
	    
        $hashRememberDevice = sha1($dUser['_id'].$dUser['password'].$userSecret);

        if( !empty($_COOKIE[LoginPage::$cookieSecretHash]) )
        {
            if( strpos(@$_COOKIE[LoginPage::$cookieSecretHash], $hashRememberDevice, 2) === 2 )
            {
                LoginPage::startSession($dUser);
	        
                return [ 'redirect' => $this->getSuccessRedirectUrl() ];
            }
            
            setcookie(LoginPage::$cookieSecretHash, null);
        }
        
        if( empty($userSecret) ) ['ERROR' => 'GA secret empty'];

	    /** @noinspection PhpUndefinedClassInspection */
	    $gAuth       = new PHPGangsta_GoogleAuthenticator();
        $checkResult = $gAuth->verifyCode($userSecret, $_REQUEST['code'], 2);

        if( !$checkResult )
        {
	        unset($_SESSION['_tmp_ga_qr'], $_SESSION['_tmp_ga_secret']);

	        return [ 'ERROR' => 'Code is incorrect' ];
        }

	    /** @noinspection RandomApiMigrationInspection */
	    setcookie(
            LoginPage::$cookieSecretHash, rand(10, 99).$hashRememberDevice.rand(1, 99).'_'.rand(99, 999),
            time() + 86400 * LoginPage::$daysKeepCode, '/', null
        );

        LoginPage::startSession($dUser);
        
        if( empty($dUser['secret']) )
        {
	        $this->getAcpUsersCollection()->updateById($dUser['_id'], [ '$set' => [ 'secret' => $userSecret ] ]);
        }
	            
        return [ 'redirect' => $this->getSuccessRedirectUrl() ];
    }
    
    protected function getSuccessRedirectUrl() :string
    {
        if( !empty($_COOKIE['_U']) and !empty($_COOKIE['_UH'] ) )
        {
            $urlRedirect = $_COOKIE['_U'];
	        $hash        = $_COOKIE['_UH'];
	        $hashValid   = sha1($urlRedirect.'~'.$_SERVER['REMOTE_ADDR'].'~'.Config::dir__projectRoot());
                
            setcookie('_U', '', time()-100, '/');
            setcookie('_UH', '', time()-100, '/');
            
            if( false!==stripos($urlRedirect, 'login.php') ) return '/';
            
            if( $hash===$hashValid ) return $urlRedirect;
        }
        
        return '/';
    }
}

abstract class ForgotPassword extends AjaxEndpoint
{
    protected $checkUserAccess = false;
    
    abstract protected function getAcpUsersCollection() :MongoDbCollection;
    
    protected function respondMain() :array 
    {
        if( empty($_REQUEST['email']) ) return ['ERROR' => 'Wrong request'];

        $dUser = $this->getAcpUsersCollection()->findOne(['email' => $_REQUEST['email']]);

        if( empty($dUser) ) return ['ERROR' => 'No user found with this email'];

        if( isset($dUser['isSuspended']) ) return [ 'ERROR' => 'Account suspended' ];
        
        $acpDomain = $_SERVER['SERVER_NAME'];
        $acpScheme = $_SERVER['REQUEST_SCHEME'];
        
        $headers  = "From: ".LoginPage::$emailFromForgotPassword."\r\n";
        $headers .= "X-Mailer: PHP/".PHP_VERSION."\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";

        $link = $acpScheme.'://'.$acpDomain.'/login.php?action=reset&hash='.sha1($dUser['_id'].$dUser['password'].floor(time() / 3600) * 3600);

	    mail($dUser['email'], LoginPage::$sbjEmailForgot, str_replace('{link}', $link, LoginPage::$tplEmailForgot), $headers);

        return ['redirect' => '?action=wait_email'];
    }
}

abstract class ResetPassword extends AjaxEndpoint
{
    protected $checkUserAccess = false;
    
    abstract protected function getAcpUsersCollection() :MongoDbCollection;

	protected function respondMain() :array 
	{
		if( empty($_REQUEST['password']) or empty($_REQUEST['hash']) ) return [ 'ERROR' => 'Wrong request' ];

		$hash      = $_REQUEST['hash'];
		$userFound = null;

		$users = $this->getAcpUsersCollection()->find([], [ 'sort' => [ '_id' => 1 ] ])->toArray();

		foreach( $users as $user )
		{
			if( $hash == sha1($user['_id'].$user['password'].floor(time() / 3600) * 3600) or
			    $hash == sha1($user['_id'].$user['password'].floor((time() - 300) / 3600) * 3600)
			)
			{
				$userFound = $user;
				break;
			}
		}

		if( empty($userFound) ) return [ 'ERROR' => 'Hash expired' ];
		
		if( isset($userFound['isSuspended']) ) return [ 'ERROR' => 'Account suspended' ];

		$this->getAcpUsersCollection()->updateById($userFound['_id'], [ '$set' => [ 'password' => md5($_REQUEST['password']) ] ]);

		return [ 'redirect' => '/login.php' ];
	}
}
