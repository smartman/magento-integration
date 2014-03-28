<?php
/**
 * NB! This is a BETA release of Erply Connector.
 *
 * Use with caution and at your own risk.
 *
 * The author does not take any responsibility for any loss or damage to business
 * or customers or anything at all. These terms may change without further notice.
 *
 * License terms are subject to change. License is all-restrictive until
 * said otherwise.
 *
 * @author Eepohs Ltd
 */
class Eepohs_Erply_Block_SidebarBlock extends Mage_Adminhtml_Block_Widget_Tabs
{

    public function __construct()
    {
        parent::__construct();
        $this->setTitle(Mage::helper('eepohs_erply')->__('ERPLY'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('erply_import', array(
            'label' => Mage::helper('eepohs_erply')->__('Import')
            , 'title' => Mage::helper('eepohs_erply')->__('Import')
            , 'content' => $this
                ->getLayout()
                ->createBlock('eepohs_erply/import','
                eepohs_erply_import',
                array('template' => 'erply/import.phtml'))
                ->toHtml(),
            'active' => TRUE
        ));

        parent::_beforeToHtml();
    }

}