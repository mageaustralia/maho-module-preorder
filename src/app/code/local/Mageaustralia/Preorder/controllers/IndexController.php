<?php
class Mageaustralia_Preorder_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        if (!Mage::helper('mageaustralia_preorder')->isLandingEnabled()) {
            $this->norouteAction();
            return;
        }
        $this->loadLayout();
        $this->_initLayoutMessages('catalog/session');
        $this->renderLayout();
    }
}
