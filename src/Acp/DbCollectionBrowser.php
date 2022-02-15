<?php namespace Bbt\Acp;

use Bbt\MongoDbCollection;
use MongoDB\Driver\Cursor;

abstract class DbCollectionBrowser extends Page
{
	/**
	 * @var Pager
	 */
	protected $pager;
	
	/**
	 * @var Cursor
	 */
	protected $cursItems;

	/**
	 * @var array mixed summary items / data
	 */
	protected $arSummary = [];

	/**
	 * @return MongoDbCollection
	 */
	abstract protected function getCollection();
	abstract protected function getDbFilter();
	abstract protected function renderFilterForm();
	abstract protected function renderItem($_id, $dItem);
	
	protected function initMain()
	{
		$this->beforeInitMain();
		
		$coll = $this->getCollection();
		$filter = $this->getDbFilter();
		
		$this->constructPager();
		$this->cursItems = $coll->find($filter, ['sort' => $this->getSortOrder()] + $this->pager->getCursorOptions());
		
	}
	
	protected function constructPager()
	{
		$this->pager = new Pager();
		return $this->pager;
	}

	protected function renderMain()
	{
		?>
		<!--suppress ALL -->
		<div class="row">
			<div class="col-xs-12">
				<div class="panel panel-default">
					<div class="panel-heading"><h3 class="panel-title"><?=$this->title?></h3></div>
					<div class="panel-body">
						<?$this->renderFilterForm();?>
					</div>
					
					<table class="table table-bordered table-striped small-font">
						<?$this->renderTableHead()?>
						<tbody>
							<?foreach($this->cursItems as $dItem):?>
								
								<?if( !$this->postFilterItem($dItem['_id'], $dItem) ) continue;?>
								<?$this->collectPageSummary($dItem['_id'], $dItem)?>
								<?$this->renderItem($dItem['_id'], $dItem)?>
								
							<?endforeach?>
						</tbody>
					</table>
					
					<hr/>
					<div class="panel-body">
						<?$this->renderPager()?>
					</div>
					
				</div>
			</div>
		</div>
		
		<div class="row">
			<?$this->renderSummary()?>
		</div>
		<?		
	}
	
    protected function renderPager()
    {
        if ($this->pager->totalPages < 2) return;
        ?>
        <div class="pagination pagination-centered">
            <ul class="pagination">
                <?
                for ($p = 1; $p <= $this->pager->totalPages; $p++)
                {
                    $query = http_build_query(array_merge($_REQUEST, ['p' => $p]));
                    echo '<li '.($p == $this->pager->currentPage ? 'class="active"' : '').'><a href="?'.$query.'">'.$p.'</a></li>';
                }
                ?>
            </ul>
        </div>
        <?
    }
	
	protected function getSortOrder() { return['_id' => -1]; }
	protected function beforeInitMain() {}
	protected function renderTableHead() {}
	protected function postFilterItem(/** @noinspection PhpUnusedParameterInspection */
		$_id, /** @noinspection PhpUnusedParameterInspection */
		&$dItem) { return true; }
	protected function renderTableFooter() {}
	protected function collectPageSummary($_id, &$dItem) {}
	protected function renderSummary()
	{
		if( empty($this->arSummary) ) return;
		
		?>
		<div class="col-md-5">
			<textarea rows="6" style="width: 100%; resize: vertical;"><?=join("\n", array_unique($this->arSummary));?></textarea>
		</div>
		<?
	}
	
	
}