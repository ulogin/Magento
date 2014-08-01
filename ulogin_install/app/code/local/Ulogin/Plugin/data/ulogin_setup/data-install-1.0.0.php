<?php
try {
    $groups = Mage::getModel('customer/group')
        ->getCollection()
        ->addFieldToFilter('customer_group_code', 'uLogin');
    if (count($groups) == 0) {
        $group = array(
            'customer_group_code' => 'uLogin',
            'tax_class_id' => '3',
        );
        Mage::getModel('customer/group')
            ->setData($group)
            ->save();
    }

    $groupId = Mage::getModel('customer/group')
        ->getCollection()
        ->addFieldToFilter('customer_group_code', 'uLogin')
        ->getFirstItem()
        ->getId();
    $path = 'ulogin_tabs/u_options/u_customer_group';
    Mage::app()->getConfig()->saveConfig($path, $groupId);
} catch ( Exception $e ) {
}