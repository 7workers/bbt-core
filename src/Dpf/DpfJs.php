<?php namespace Bbt\Dpf;


class DpfJs
{

	public static $uriLoadHooksScript = '/_sys/dpf.php';

	public function __construct()
	{


	}

	public function output()
	{
		if( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
		{
			header('HTTP/1.1 304 Not Modified');

			return;
		}

		header('Content-Type: application/javascript');
		header("Pragma: cache");
		header("Cache-Control: max-age=136000");
		header('Last-Modified: '.gmdate(DATE_RFC1123,time()));

		$uriScript = self::$uriLoadHooksScript;

		/** @lang JavaScript */
		echo <<<EOT

			$(document).ready(function() {
				setTimeout('DPF__requestData()', 1000);
			});

			//noinspection JSUnusedGlobalSymbols,JSUnusedLocalSymbols
			function DPF__requestData() {
				var arHookElements = $('.dpf-hook');
				var arHookIds = [];
				var arHookedElements = [];

				$.each(arHookElements, function (k, v) {
					var classAttr = $(v).attr('class');
					var dpfSerialKey = $(v).attr('alt');
					var dpfClassShortcut = classAttr.match(/dpf\-class\-(\w+)/)[1];
					var hookFullId = dpfClassShortcut + '~' + dpfSerialKey;

					if( -1 === arHookIds.indexOf(hookFullId) ) {
						arHookIds.push(hookFullId);
					}
					
					if( arHookedElements[dpfSerialKey + '~' + dpfClassShortcut] ) {
					    arHookedElements[dpfSerialKey + '~' + dpfClassShortcut].push(v);
					} else {
					    arHookedElements[dpfSerialKey + '~' + dpfClassShortcut] = [v];
					}
					
				});

				if (arHookIds.length > 0) {
					$.ajax({
						type: 'POST',
						url: '{$uriScript}',
						data: {hooks: arHookIds.join(' ')},

						success: function (data) {

							$.each(data, function (k, v) {
								var matches = k.toString().match(/^([^\~]+)\~(.*)$/);
								
								if( matches !== null && matches.length )
								{
									var dpfClassShortcut = matches[1];
									var dpfSerialKey = matches[2];
									
									//var arHookedElements = $('img[alt="'+dpfSerialKey + '"].dpf-class-'+dpfClassShortcut);
									//var arHookedElements = [];
	
									$.each(arHookedElements[dpfSerialKey + '~' + dpfClassShortcut], function(i, elementHooked) {
										var elementHookedJq = $(elementHooked);
										eval('DPF__hook__' + dpfClassShortcut + '(elementHookedJq,v);');
										elementHookedJq.remove();
									});
									
									arHookedElements.splice(arHookedElements.indexOf(dpfSerialKey + '~' + dpfClassShortcut) , 1);
									
								} else {
									console.log('class shortcut not extracted:' + k);
								}
								
							});

							setTimeout('DPF__requestData()', 800);
						}
					});
				} else {
					setTimeout('DPF__requestData()', 5000);
				}
			}
			
			function Dpf__queue(dpfClassShortcut, dpfSerialKey)
			{
			    var hookFullId = dpfClassShortcut + '~' + dpfSerialKey;
			    
			    $.ajax({ type: 'POST', url: '{$uriScript}', data: {hooks: hookFullId, isQueueRequest : true}});
			}
EOT;

		foreach( DpfInstance::$arDpfClasses as $className_each )
		{
			/** @noinspection PhpUnusedLocalVariableInspection */
			$classShortcut = constant($className_each.'::classShortcut');

			if( method_exists($className_each, 'javascriptHook') )
			{
				$script = call_user_func($className_each.'::javascriptHook');
				echo($script);
			}
			else
			{
				throw new \Exception('Implement '.$className_each.'::javascriptHook(), see example class DpfExampleInstance');
			}
		}
	}
}