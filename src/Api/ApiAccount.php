<?php namespace Bbt\Api;

use Bbt\MongoDbCollection;

abstract class ApiAccount
{
	public $_id;
	protected $dApiAccount;

	abstract protected function loadFromDoc($dAccount);

	/**
	 * @return MongoDbCollection
	 */
	abstract protected function getCollection();

	public function __construct( $idOrDoc )
	{
		if( is_array($idOrDoc) )
		{
			$this->loadFromDoc($idOrDoc);
		}
		else
		{
			$this->_id = $idOrDoc;
		}
	}

	protected function loadLazy()
	{
		if( is_null($this->dApiAccount) )
		{
			$this->dApiAccount = $this->getCollection()->findById($this->_id);
			$this->loadFromDoc($this->dApiAccount);
		}
	}
}