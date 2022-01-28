<?php namespace Bbt\Dpf;

use Bbt\Acp\AjaxEndpoint;
use Bbt\Acp\User;

class DpfResponseEndpoint extends AjaxEndpoint
{
	public static $directGetData = false;

	/** @noinspection PhpMissingParentConstructorInspection */
    /** @noinspection MagicMethodsValidityInspection */
    public function __construct()
	{
		$this->isResponsePossible = true;
	}

	/** @noinspection PhpMissingParentCallCommonInspection
	 * @param User $user
	 *
	 * @return bool
	 */
	protected function isAccessible( User $user ) :bool 
	{
		return true;
	}

	protected function respondMain() :array 
	{
		/** @noinspection PhpUsageOfSilenceOperatorInspection */
		$hooksRequested = @explode(' ', $_REQUEST['hooks']);

		if( empty($hooksRequested) ) return [];

		$hooksRequested = array_filter(array_unique($hooksRequested));
		
		if( isset($_REQUEST['isQueueRequest']) )
        {
            foreach( $hooksRequested as $hookId_each )
            {
                $dpfInstance = DpfInstance::constructByRequestedHook($hookId_each);
                $dpfInstance->dropCache();
                $dpfInstance->queue();
            }
            
            return [];
        }

		$responseData = [];

		foreach( $hooksRequested as $hookId_each )
		{
			$dpfInstance = DpfInstance::constructByRequestedHook($hookId_each);

			$data = $dpfInstance->getPreCachedData();

            if( !is_null($data) )
            {
            	$responseData[$hookId_each] = $data;
            	continue;
            }
            
			if( self::$directGetData && is_null($data) )
			{
				$dpfInstance->preCache();
				$data = $dpfInstance->getPreCachedData();
				if( !is_null($data) ) $responseData[$hookId_each] = $data;

				break;
			}
			
			if( null===$data )
			{
				$dpfInstance->reQueueIfPossible();
			}
        }

		return $responseData;
	}
}