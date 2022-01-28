<?php namespace Bbt\Acp;

use Bbt\HpdInstance;

abstract class HpdEnabledPage extends Page
{
	protected $preCacheOnPage = false;
	
	protected     $isHpdReady;
	
	abstract protected function getHpdInstance() :HpdInstance;
	abstract protected function renderHpdReady();

	protected function initMain()
	{
		if( $_SERVER['REQUEST_METHOD']=='POST' ) throw new \Exception('POST method not supported');
		
		$hpd = $this->getHpdInstance();
		
		if( $this->preCacheOnPage )
        {
            $hpd->preCache();
            
            $this->isHpdReady = true;

	        foreach( $hpd as $field => $value ) $this->{$field} = &$hpd->{$field};
	        
	        return;
        }
		
		$this->isHpdReady = $hpd->isReady();
		
		if( $_SERVER['REQUEST_METHOD']=='PATCH' )
		{
			header('Cache-Control: no-cache, must-revalidate');
			header('Content-type: application/json');
			echo('{"r":'.intval($this->isHpdReady).'}');
			die();
		}
	    
        if( !$this->isHpdReady )
	    {
		    $hpd->ensureQueued();
		    
		    return;
	    }
        
	    $hpd->loadData();
        
        foreach( $hpd as $field => $value ) $this->{$field} = &$hpd->{$field};
	}

	
	protected function renderMain()
	{
		if( $this->isHpdReady )
		{
			$this->renderHpdReady();
			return;
		}
		
		?>
		
		<div class="row">
			<div class="col-xs-2 col-xs-offset-5" style="margin-top: 132px;">
				<i class="fas fa-spinner fa-pulse fa-3x fa-fw"></i>
				<span class="sr-only">Loading...</span>
			</div>
		</div>
		
		<script>
			function checkHpd() {
				$.ajax(window.location.href, {method:'PATCH', success: function (data) {
				    if( data['r'] ) window.location.reload();
                    setTimeout(checkHpd, 500);
                }});
            }
            setTimeout(checkHpd, 500);
		</script>
		
		
		<?
	}
}