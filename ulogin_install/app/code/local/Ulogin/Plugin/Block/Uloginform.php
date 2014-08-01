<?php
class Ulogin_Plugin_Block_Uloginform extends Mage_Core_Block_Template
{
    protected $_type;
    protected $_display = 1;
    protected $_ulogin_id;
    protected $_label;
    protected static $count_form = 0;
    protected static $allFormIdStr = '';

    protected function getCurrentCmsPage() {
        $pageId = Mage::getBlockSingleton('cms/page')->getPage()->getIdentifier();
        return $pageId;
    }

    protected function getRedirectUri() {
        return $this->getUrl('ulogin/index/login');
    }

    protected function getDataUlogin() {
	    $data_ulogin = 'data-ulogin="';
	    if (empty($this->_ulogin_id)) {
		    $data_ulogin .= 'display=panel;fields=first_name,last_name,email;providers=vkontakte,odnoklassniki,mailru,facebook;hidden=other;';
	    }

        if ($this->_type == 'account') {
	        $data_ulogin .= 'redirect_uri=;callback=uloginCallback';
        } else {
	        $data_ulogin .= 'redirect_uri=' . $this->getUrl('ulogin/index/login');
        }

	    $data_ulogin .= '"';
	    return $data_ulogin;
    }

    public function setDisplayParams($type = 'login', $display = 1){
        $this->_display = $display;
        $this->_type = $type;
    }

    protected function setRedirectLoginAfter() {
        Mage::getSingleton('customer/session')->setUloginRedirect(Mage::helper('core/url')->getCurrentUrl());
    }

    protected function getLabel() {
        if (empty($this->_label) && $this->_type != 'account') {
            $this->_label = Mage::getStoreConfig('ulogin_tabs/u_options/u_label_login');
        }
        return $this->_label;
    }

    protected function setUloginId() {
        $ulogin_id = '';
        $display = $this->_display;
        $session = Mage::getSingleton('customer/session');
        if ($display) {
            switch ($this->_type){
                case 'account':
                    $ulogin_id = Mage::getStoreConfig('ulogin_tabs/u_options/u_id_account');
                    break;
                case 'login': default:
                    if (!$session->isLoggedIn()) {
                        $ulogin_id = Mage::getStoreConfig('ulogin_tabs/u_options/u_id_login');
                    } else {
                        $ulogin_id = '';
                        $display = 0;
                    }
                    break;
            }
        }
        $this->_ulogin_id = $ulogin_id;
        $this->_display = $display;
    }

    protected function getUloginId() {
        if ($this->_ulogin_id == '') {
            $this->setUloginId();
        }
        return $this->_ulogin_id;
    }

    protected function isVisible() {
        $this->getUloginId();
        return ($this->_display);
    }
    
    protected function getFormId() {
        return 'ulogin_'.$this->getUloginId().'_'.(self::$count_form);
    }

    protected function incCountForm() {
        self::$count_form++;
    }

    protected function getAllFormIdStr() {
        $str = self::$allFormIdStr;
        $str = ($str == '') ? $str : $str . ', ';
        self::$allFormIdStr = $str . '"' . $this->getFormId() . '"';
        return self::$allFormIdStr;
    }
}