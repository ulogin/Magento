<?php
class Ulogin_Plugin_Model_Resource_Account_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        $this->_init('ulogin/account');
    }
}