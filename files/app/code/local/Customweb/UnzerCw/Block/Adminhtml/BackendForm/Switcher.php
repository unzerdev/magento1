<?php
/**
 * You are allowed to use this API in your web application.
 *
 * Copyright (C) 2018 by customweb GmbH
 *
 * This program is licenced under the customweb software licence. With the
 * purchase or the installation of the software in your application you
 * accept the licence agreement. The allowed usage is outlined in the
 * customweb software licence which can be found under
 * http://www.sellxed.com/en/software-license-agreement
 *
 * Any modification or distribution is strictly forbidden. The license
 * grants you the installation in one application. For multiuse you will need
 * to purchase further licences at http://www.sellxed.com/shop.
 *
 * See the customweb software licence agreement for more details.
 *
 *
 * @category	Customweb
 * @package		Customweb_UnzerCw
 * 
 */

class Customweb_UnzerCw_Block_Adminhtml_BackendForm_Switcher extends Mage_Adminhtml_Block_Template
{
    protected function _prepareLayout()
    {
        $this->setTemplate('customweb/unzercw/backendform/switcher.phtml');
        return parent::_prepareLayout();
    }

    /**
     * Enter description here...
     *
     * @return array
     */
    public function getStoreSelectOptions()
    {
        $section = $this->getRequest()->getParam('tab');

        $curWebsite = $this->getRequest()->getParam('website');
        $curStore   = $this->getRequest()->getParam('store');

        $storeModel = Mage::getSingleton('adminhtml/system_store');
        /* @var $storeModel Mage_Adminhtml_Model_System_Store */

        $url = Mage::getModel('adminhtml/url');

        $options = array();
        $options['default'] = array(
            'label'    => Mage::helper('adminhtml')->__('Default Config'),
            'url'      => $url->getUrl('*/*/*', array('tab'=>$section)),
            'selected' => !$curWebsite && !$curStore,
            'style'    => 'background:#ccc; font-weight:bold;',
        );

        foreach ($storeModel->getWebsiteCollection() as $website) {
            $websiteShow = false;
            foreach ($storeModel->getGroupCollection() as $group) {
                if ($group->getWebsiteId() != $website->getId()) {
                    continue;
                }
                $groupShow = false;
                foreach ($storeModel->getStoreCollection() as $store) {
                    if ($store->getGroupId() != $group->getId()) {
                        continue;
                    }
                    if (!$websiteShow) {
                        $websiteShow = true;
                        $options['website_' . $website->getCode()] = array(
                            'label'    => $website->getName(),
                            'url'      => $url->getUrl('*/*/*', array('tab'=>$section, 'website'=>$website->getCode())),
                            'selected' => !$curStore && $curWebsite == $website->getCode(),
                            'style'    => 'padding-left:16px; background:#DDD; font-weight:bold;',
                        );
                    }
                    if (!$groupShow) {
                        $groupShow = true;
                        $options['group_' . $group->getId() . '_open'] = array(
                            'is_group'  => true,
                            'is_close'  => false,
                            'label'     => $group->getName(),
                            'style'     => 'padding-left:32px;'
                        );
                    }
                    $options['store_' . $store->getCode()] = array(
                        'label'    => $store->getName(),
                        'url'      => $url->getUrl('*/*/*', array('tab'=>$section, 'website'=>$website->getCode(), 'store'=>$store->getCode())),
                        'selected' => $curStore == $store->getCode(),
                        'style'    => '',
                    );
                }
                if ($groupShow) {
                    $options['group_' . $group->getId() . '_close'] = array(
                        'is_group'  => true,
                        'is_close'  => true,
                    );
                }
            }
        }

        return $options;
    }

    /**
     * Return store switcher hint html
     *
     * @return mixed
     */
    public function getHintHtml()
    {
        return Mage::getBlockSingleton('adminhtml/store_switcher')->getHintHtml();
    }
}