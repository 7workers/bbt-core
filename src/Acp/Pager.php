<?php namespace Bbt\Acp;

class Pager
{

    public static $defaultItemsPerPage = 100;
    public static $defaultDummyPagesCount = 50;

    public $itemsPerPage;
    public $totalPages;
    public $currentPage = 1;

	/**
	 * Pager constructor.
	 *
	 * @param $itemsPerPage
	 */
	public function __construct( $itemsPerPage=null )
	{
		if($itemsPerPage === null) $itemsPerPage = self::$defaultItemsPerPage;

		if( !empty($_REQUEST['perpage']) ) $itemsPerPage = (int)$_REQUEST['perpage'];
		$this->itemsPerPage = (int)$itemsPerPage;
		$this->currentPage = !empty($_REQUEST['p']) ? intval($_REQUEST['p']) : 1;
		$this->totalPages = self::$defaultDummyPagesCount;
	}

	public function getCursorOptions()
	{
        $itemsToSkip = ($this->currentPage - 1) * $this->itemsPerPage;

		return ['skip' => $itemsToSkip, 'limit' => $this->itemsPerPage]; 
	}
}