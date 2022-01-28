<?php namespace Fdt\Dpf;

use Bbt\Dpf\DpfInstance;

class DpfExampleInstance extends DpfInstance
{
	/**
	 * You MUST define this constant it should be 2-3 signs long, unique string
	 */
	const classShortcut = 'EX';

	private $objectId;

	/**
	 * constructed will be called with serial key as parameter.
	 * You must construct related object so you can use in the renderMain() and  getProcessedData()
	 *
	 * @see DpfInstance::convertObject2SerialKey()
	 *
	 *@param string $serialKey
	 */
	public function __construct($serialKey)
	{
		$this->objectId = 'ID_'.$serialKey;
	}

	/**
	 * return JavaScript with implemented function
	 * function must inject processed data into hooks on the page
	 * function signature should be:
	 * function DPF__hook__{classShortcut}(hookElement, data)
	 *    where hookElement is jQuery wrapped DOM element <IMG> where renderHook() was called in the renderMain()
	 *          data is array passed from PHP getProcessedData()
	 *
	 * PHP function must be public static
	 *
	 * @return string javascript
	 */
	public static function javascriptHook()
	{
		/** @lang JavaScript */
		return
<<<EOT
			function DPF__hook__EX(hookElement, data) {
				hookElement.prev().prev().html(data['processed_k1']);
			}
EOT;
	}

	/**
	 * render HTML, you MUST call $this->renderHook() to inject hook later used in the javascriptHook()
	 */
	public function renderMain()
	{
		?>
			<div>
				THIS IS DPF with serialKey=<strong><?=$this->serialKey?></strong><br/>
				<span>THIS IS INCOMPLETE CONTENT</span><br/>
				<?$this->renderHook()?>
			</div>
		<?
	}

	/**
	 * process missing (heavy) data and return as array
	 * @return mixed $data
	 */
	public function getProcessedData()
	{
		$data['processed_k1'] = 'THIS_IS_PROCESSED:'.$this->objectId.'YES!';

		return $data;
	}

	/**
	 * @return int cache TTL in seconds
	 */
	public function getCacheTtl()
	{
		return 20;
	}

}