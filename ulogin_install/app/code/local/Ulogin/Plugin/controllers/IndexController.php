<?php
class Ulogin_Plugin_IndexController extends Mage_Core_Controller_Front_Action
{
    protected $beforeAuthUrl;
    protected $_newAccount;

    public function preDispatch()
    {
        parent::preDispatch();

        $this->beforeAuthUrl = Mage::getSingleton('customer/session')->getUloginRedirect();
    }


    public function loginAction () {
	    $this->uloginLogin($this->__("Вход успешно выполнен"));
    }

	public function addaccountAction ()
	{
		$this->uloginLogin($this->__("Аккаунт успешно добавлен"));
	}

	protected function uloginLogin ($title = '', $msg = '')
	{
        $u_user = $this->uloginParseRequest();
        if (!$u_user){
            return;
        }

        try {
            $u_collection = Mage::getModel('ulogin/account')->getCollection()
                ->addFieldToFilter('identity', urlencode($u_user['identity']));

            $user_id = $u_collection->getFirstItem()->getUserid();

            if (isset($user_id) && intval($user_id) > 0) {
                $check_m_user = Mage::getResourceSingleton('customer/customer')->checkCustomerId($user_id);
                if ($check_m_user) {
                    if (!$this->checkCurrentCustomerId($user_id)) {
                        // если $user_id != ID текущего пользователя
                        return;
                    }
                    // пользователь зарегистрирован. Необходимо выполнить вход и обновить данные (если необходимо).
                    $user_id = $this->loginCustomer($u_user, $user_id);
                } else {
                    // данные о пользователе есть в ulogin_table, но отсутствуют в Magento. Необходимо добавить запись в ulogin_table и регистрацию/вход в Magento.
                    $user_id = $this->addUloginAccount($u_user, $u_collection);
                }
            } else {
                // пользователь НЕ обнаружен в ulogin_table. Необходимо добавить запись в ulogin_table и регистрацию/вход в Magento.
                $user_id = $this->addUloginAccount($u_user);
            }

	        if (!$user_id){
		        return;
	        }

            if ($this->_newAccount) {
	            $msg = $this->__('Thank you for registering with %s.', Mage::app()->getStore()->getFrontendName());
            }

            $this->resultMess(array(
                'title' => $title,
                'msg' => $msg,
                'user' => $u_user,
                'userId' => $user_id,
                'answerType' => 'ok'
            ));
            return;
        }
        catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $this->resultMess(array(
                'title' => $this->__("Ошибка при работе с БД"),
                'msg' => "Mage_Core_Exception: " . $e->getMessage(),
                'answerType' => 'error'
            ));
            return;
        }
        catch (Exception $e){
            Mage::logException($e);
            $this->resultMess(array(
                'title' => $this->__("Ошибка при работе с БД"),
                'msg' => "Exception: " . $e->getMessage(),
                'answerType' => 'error'
            ));
            return;
        }
    }

    protected function resultMess($result) {
        if (isset($result['answerType']) && isset($result['msg'])) {
            $str = (!empty($result['title'])) ? '<b>'.$result['title'].'</b>' : '';
            $str .= (!empty($str)) ? '<br>' : '';
            $str .= (!empty($result['msg'])) ? $result['msg'] : '';
            $session = Mage::getSingleton('core/session');

            switch ($result['answerType']) {
                case 'error':
                    $session->addError($str);
                    break;
                case 'ok':
                    $session->addSuccess($str);
                    break;
                case 'ulogin_error':
                    $str .= ' '.$this->__('Попробуйте ещё раз. В случае, если эта ошибка повторится, пожалуйста, сообщите о ней администрации сайта');
                    $session->addSuccess($str);
                    break;
	            case 'notice':
		            $session->addNotice($str);
		            break;
            }
        }
        Mage::app()->getResponse()->setRedirect($this->beforeAuthUrl);
    }


    /**
     * Добавление в таблицу uLogin
     * @param $u_user - данные о пользователе, полученные от uLogin
     * @param $u_collection - при непустом значении необходимо переписать данные в таблице uLogin
     */
    protected function addUloginAccount($u_user, $u_collection = ''){
        if ($u_collection instanceof Ulogin_Plugin_Model_Resource_Account_Collection){
            // данные о пользователе есть в ulogin_table, но отсутствуют в Magento => удалить их
            $u_collection->getFirstItem()->delete();
        }

        $user_id = Mage::getModel("customer/customer")
            ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($u_user['email'])
            ->getId();
        // $check_m_user == true -> есть пользователь с таким email
        $check_m_user = Mage::getResourceSingleton('customer/customer')->checkCustomerId($user_id);

        $cur_user_id = 0;
        // $isLoggedIn == true -> пользователь онлайн
        $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
        if ($isLoggedIn) {
            $cur_user_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
        }

        if (!$check_m_user && !$isLoggedIn) {
            // отсутствует пользователь с таким email в базе -> регистрация
            $user_id = $this->loginCustomer($u_user);
        } else {
            // существует пользователь с таким email или это текущий пользователь
            if (intval($u_user["verified_email"]) != 1){
                // Верификация аккаунта
                $this->resultMess(array(
                    'title' => $this->__("Подтверждение аккаунта"),
                    'msg' => $this->__("Электронный адрес данного аккаунта совпадает с электронным адресом существующего пользователя. <br>Требуется подтверждение на владение указанным email.") .
                             '<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("'.$_POST['token'].'")</script>',
                    'answerType' => 'notice'
                ));
	            return false;
            }

            $user_id = $isLoggedIn ? $cur_user_id : $user_id;

            $other_u = Mage::getModel('ulogin/account')->getCollection()
                ->addFieldToFilter('userid', $user_id)
                ->getFirstItem()
                ->getIdentity();

            if ($other_u) {
                // Синхронизация аккаунтов
                if(!$isLoggedIn && !isset($u_user['merge_account'])){
                    $this->resultMess(array(
                        'title' => $this->__("Синхронизация аккаунтов"),
                        'msg' => $this->__("С данным аккаунтом уже связаны данные из другой социальной сети. <br>Требуется привязка новой учётной записи социальной сети к этому аккаунту.") .
                                 '<script src="//ulogin.ru/js/ulogin.js"  type="text/javascript"></script><script type="text/javascript">uLogin.mergeAccounts("'.$_POST['token'].'","'.$other_u.'")</script>',
                        'answerType' => 'notice'
                    ));
	                return false;
                }
            }

            $user_id = $this->loginCustomer($u_user, $user_id);
        }

        $u_account = array(
            'userid' => $user_id,
            'identity' => urlencode($u_user['identity']),
            'network' => isset($u_user['network']) ? $u_user['network'] : ''
        );

        Mage::getModel('ulogin/account')
            ->setData($u_account)
            ->save();

        return $user_id;
    }

    /**
     * Проверка текущего пользователя
     */
    protected function checkCurrentCustomerId($user_id){
        if(Mage::getSingleton('customer/session')->isLoggedIn()) {
            $currentCustomerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
            if ($currentCustomerId == $user_id) {
                return true;
            }
            $this->resultMess(array(
                'title' => "",
                'msg' => $this->__("Данный аккаунт привязан к другому пользователю. </br>Вы не можете использовать этот аккаунт"),
                'answerType' => 'error'
            ));
            return false;
        }
        return true;
    }

    /**
     * Обработка ответа сервера авторизации
     */
    protected function uloginParseRequest(){
        if (!isset($_POST['token'])) {
            $this->resultMess(array(
                'title' => $this->__("Произошла ошибка при авторизации."),
                'msg' => $this->__("Не был получен токен uLogin."),
                'answerType' => 'ulogin_error'
            ));
            return false;
        }

        $s = $this->getUserFromToken($_POST['token']);

        if (!$s){
            $this->resultMess(array(
                'title' => $this->__("Произошла ошибка при авторизации."),
                'msg' => $this->__("Не удалось получить данные о пользователе с помощью токена."),
                'answerType' => 'ulogin_error'
            ));
            return false;
        }

        $u_user = json_decode($s, true);

        if (!$this->checkTokenError($u_user)){
            return false;
        }

        return $u_user;
    }

    /**
     * "Обменивает" токен на пользовательские данные
     */
    protected function getUserFromToken($token = false)
    {
        $response = false;
        if ($token){
            $request = 'http://ulogin.ru/token.php?token=' . $token . '&host=' . $_SERVER['HTTP_HOST'];
            if(in_array('curl', get_loaded_extensions())){
                $c = curl_init($request);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($c);
                curl_close($c);

            }elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')){
                $response = file_get_contents($request);
            }
        }
        return $response;
    }

    /**
     * Проверка пользовательских данных, полученных по токену
     */
    protected function checkTokenError($u_user){
        if (!is_array($u_user)){
            $this->resultMess(array(
                'title' => $this->__("Произошла ошибка при авторизации."),
                'msg' => $this->__("Данные о пользователе содержат неверный формат."),
                'answerType' => 'ulogin_error'
            ));
            return false;
        }

        if (isset($u_user['error'])){
            $strpos = strpos($u_user['error'],'host is not');
            if ($strpos){
                $this->resultMess(array(
                    'title' => $this->__("Произошла ошибка при авторизации."),
                    'msg' => $this->__("<i>ERROR</i>: адрес хоста не совпадает с оригиналом %s", sub($u_user['error'],intval($strpos)+12)),
                    'answerType' => 'ulogin_error'
                ));
                return false;
            }
            switch ($u_user['error']){
                case 'token expired':
                    $this->resultMess(array(
                        'title' => $this->__("Произошла ошибка при авторизации."),
                        'msg' => $this->__("<i>ERROR</i>: время жизни токена истекло"),
                        'answerType' => 'ulogin_error'
                    ));
                    break;
                case 'invalid token':
                    $this->resultMess(array(
                        'title' => $this->__("Произошла ошибка при авторизации."),
                        'msg' => $this->__("<i>ERROR</i>: неверный токен"),
                        'answerType' => 'ulogin_error'
                    ));
                    break;
                default:
                    $this->resultMess(array(
                        'title' => $this->__("Произошла ошибка при авторизации."),
                        'msg' => $this->__("<i>ERROR</i>: " . $u_user['error']),
                        'answerType' => 'ulogin_error'
                    ));
            }
            return false;
        }
        if (!isset($u_user['identity'])){
            $this->resultMess(array(
                'title' => $this->__("Произошла ошибка при авторизации."),
                'msg' => $this->__("В возвращаемых данных отсутствует переменная <b>%s</b>.", "identity"),
                'answerType' => 'ulogin_error'
            ));
            return false;
        }
        if (!isset($u_user['email'])){
            $this->resultMess(array(
                'title' => $this->__("Произошла ошибка при авторизации."),
                'msg' => $this->__("В возвращаемых данных отсутствует переменная <b>%s</b>.", "email"),
                'answerType' => 'ulogin_error'
            ));
            return false;
        }
        return true;
    }

    /**
     * Выполнение регистрации и входа пользователя в систему
     * @param $u_user - данные от uLogin
     * @return bool|int - Id нового пользователя или false
     */
    protected function createCustomer($u_user)
    {
        $customerId = 0;
        try {
            $newAccount = false;
            $passwordLength = 10;
            $customer = Mage::getModel('customer/customer');
            $password = $customer->generatePassword($passwordLength);

            $email = $u_user['email'];
            $customer->setWebsiteId(Mage::app()->getWebsite()->getId())
                ->loadByEmail($email);

            $gender = 0;
            if (isset( $u_user["sex"])){
                if ( $u_user["sex"] == 1) {
                    $gender = 2;
                } elseif ( $u_user["sex"] == 2) {
                    $gender = 1;
                }
            }

            $customerId = $customer->getId();
            if(!$customerId) {
                $newAccount = true;
                $customer->setEmail($email);
                $customer->setFirstname( $u_user["first_name"]);
                $customer->setLastname( $u_user["last_name"]);
                $customer->setGroupId(Mage::getStoreConfig('ulogin_tabs/u_options/u_customer_group'));
                if (isset($gender)){
                    $customer->setGender($gender);
                }
                if (isset( $u_user["bdate"])){
                    $customer->setDob( $u_user["bdate"]);
                }
                if ($gender > 0){
                    $customer->setGender($gender);
                }
                $customer->setPassword($password);
            } else {
                if (isset( $u_user["bdate"]) &&  '' == trim($customer->getDob())){
                    $customer->setDob( $u_user["bdate"]);
                }
                if ($gender > 0 && '' == trim($customer->getGender())){
                    $customer->setGender($gender);
                }
            }

            $customer->save();
            $customer->setConfirmation(null);
            $customer->save();

            $customerId = $customer->getId();

            $set_custom_address = '';

            if (($customerId)
                && count($customer->getAddresses()) == 0){
                $set_custom_address = $this->setCustomAddress ( $u_user, $customerId);
            }

            if ($newAccount){
                $customer->sendNewAccountEmail();
            }

            $this->_newAccount = $newAccount;

            //Make a "login" of new customer
            Mage::getSingleton('customer/session')->loginById($customerId);

            if ($set_custom_address !== ''){
                $this->resultMess(array(
                    'title' => $this->__("Ошибка при создании адреса"),
                    'msg' => $set_custom_address,
                    'answerType' => 'error'
                ));
            }
        }
        catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $this->resultMess(array(
                'title' => $this->__("Ошибка при входе"),
                'msg' => "Mage_Core_Exception: " . $e->getMessage(),
                'answerType' => 'error'
            ));
            return false;
        }
        catch (Exception $e){
            Mage::logException($e);
            $this->resultMess(array(
                'title' => $this->__("Ошибка при входе"),
                'msg' => "Exception: " . $e->getMessage(),
                'answerType' => 'error'
            ));
            return false;
        }
        return $customerId;
    }

    /**
     * Выполнение входа пользователя в систему по customerId
     * @param $u_user
     * @param int $customerId
     * @return bool|int
     */
    protected function loginCustomer($u_user, $customerId = 0)
    {
        if (!$customerId) {
            return $this->createCustomer($u_user);
        } else {
            try {
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId(Mage::app()->getWebsite()->getId())
                    ->load($customerId);

                $gender = 0;
                if (isset($u_user["sex"])) {
                    if ($u_user["sex"] == 1) {
                        $gender = 2;
                    } elseif ($u_user["sex"] == 2) {
                        $gender = 1;
                    }
                }

                if (isset($u_user["bdate"]) && '' == trim($customer->getDob())) {
                    $customer->setDob($u_user["bdate"]);
                }
                if ($gender > 0 && '' == trim($customer->getGender())) {
                    $customer->setGender($gender);
                }

                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();

                $set_custom_address = '';
                if (count($customer->getAddresses()) == 0) {
                    $set_custom_address = $this->setCustomAddress($u_user, $customerId);
                }

                //Make a "login" of new customer
                Mage::getSingleton('customer/session')->loginById($customerId);

                if ($set_custom_address !== ''){
                    $this->resultMess(array(
                        'title' => $this->__("Ошибка при создании адреса"),
                        'msg' => $set_custom_address,
                        'answerType' => 'error'
                    ));
                }

            } catch (Mage_Core_Exception $e) {
                Mage::logException($e);
                $this->resultMess(array(
                    'title' => $this->__("Ошибка при входе"),
                    'msg' => "Mage_Core_Exception: " . $e->getMessage(),
                    'answerType' => 'error'
                ));
                return false;
            } catch (Exception $e) {
                Mage::logException($e);
                $this->resultMess(array(
                    'title' => $this->__("Ошибка при входе"),
                    'msg' => "Exception: " . $e->getMessage(),
                    'answerType' => 'error'
                ));
                return false;
            }
            return $customerId;
        }
    }

    /**
     * Создание адресов для нового пользователя
     * @return string - текст ошибки при её возникновении
     */
    protected function setCustomAddress ( $u_user, $customerId){
        //Build billing and shipping address for customer, for checkout
        try {
            $_custom_address = array (
                'firstname' => isset( $u_user["first_name"]) ?  $u_user["first_name"] : '',
                'lastname' => isset( $u_user["last_name"]) ?  $u_user["last_name"] : '',
                'city' => isset( $u_user["city"]) ?  $u_user["city"] : '',
                'region_id' => '',
                'region' => '',
                'postcode' => '',
                'country_id' => isset( $u_user["country"]) ? $this->getCountryId( $u_user["country"]) : '',
                'telephone' => isset( $u_user["phone"]) ?  $u_user["phone"] : '',
            );

            $customAddress = Mage::getModel('customer/address');
            $customAddress->setData($_custom_address)
                ->setCustomerId($customerId)
//                ->setIsDefaultBilling('1')
//                ->setIsDefaultShipping('1')
//                ->setSaveInAddressBook('1')
//                ->save()
            ;

            Mage::getSingleton('checkout/session')
                ->getQuote()
                ->setBillingAddress(Mage::getSingleton('sales/quote_address')->importCustomerAddress($customAddress));
        }
        catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            return "Mage_Core_Exception" . $e->getMessage();
        }
        catch (Exception $e){
            Mage::logException($e);
            return "Exception" . $e->getMessage();
        }
        return '';
    }

    /**
     * Полчение ID страны из её названия
     */
    protected function getCountryId($countryName) {
        $countryId = '';
        $countryCollection = Mage::getModel('directory/country')->getCollection();
        foreach ($countryCollection as $country) {
            if ($countryName == $country->getName()) {
                $countryId = $country->getCountryId();
                break;
            }
        }
        $countryCollection = null;
        return $countryId;
    }

    public function deleteaccountAction ()
    {
        if (!isset($_POST['delete_account']) || $_POST['delete_account'] != 'delete_account') {
            $this->_forward('defaultNoRoute');
            return;
        }

        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        $network = isset($_POST['network']) ? $_POST['network'] : '';


        if ($user_id != '' && $network != '') {
            try {
                $check_m_user = Mage::getResourceSingleton('customer/customer')->checkCustomerId($user_id);
                if ($check_m_user) {
                    $collection = Mage::getModel('ulogin/account')->getCollection()
                        ->addFieldToFilter('userid', $user_id)
                        ->addFieldToFilter('network', $network);
                    foreach ($collection as $del_network) {
                        $del_network->delete();
                    }

                    $this->resultMess(array(
                        'title' => $this->__("Аккаунт успешно удалён"),
                        'msg' => $this->__("Удаление аккаунта <b>%s</b> успешно выполнено", $network),
                        'user' => $user_id,
                        'answerType' => 'ok'
                    ));
                    return;
                }
            } catch (Mage_Core_Exception $e) {
                Mage::logException($e);
                $this->resultMess(array(
                    'title' => $this->__("Ошибка при удалении аккаунта"),
                    'msg' => "Mage_Core_Exception: " . $e->getMessage(),
                    'answerType' => 'error'
                ));
                return;
            } catch (Exception $e) {
                Mage::logException($e);
                $this->resultMess(array(
                    'title' => $this->__("Ошибка при удалении аккаунта"),
                    'msg' => "Exception: " . $e->getMessage(),
                    'answerType' => 'error'
                ));
                return;
            }
        }
    }
}