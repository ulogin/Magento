<?php
class Ulogin_Plugin_Model_Resource_Account extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('ulogin/account', 'account_id');
    }
}