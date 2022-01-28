<?php namespace Bbt\Acp;

abstract class PageBlock extends Page
{

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function render()
    {
        if( $this->isRenderPossible() )
        {
            $this->renderMain();
        }
        else
        {
            $this->onRenderNotPossible();
        }
    }

    protected function javascriptMain() {}

}