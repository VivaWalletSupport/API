<?php
class vivawallet extends PaymentModule
{
	const INSTALL_SQL_FILE = 'install.sql';
	
	private $_html = '';
	private $_postErrors = array();

	public function __construct()
	{
		if(substr(_PS_VERSION_,2,1) > 4){
		if (empty(Context::getContext()->link))
		Context::getContext()->link = new Link();
		}
		
		$this->name = 'vivawallet';
		$this->displayName = 'Vivawallet';		
		$this->tab = 'payments_gateways';
		$this->author = 'Viva Wallet';
		$this->version = 1.6;
		//hookDisplayPaymentEU - added compatible
		$this->is_eu_compatible = 1;

		$config = Configuration::getMultiple(array('VIVAWALLET_MERCHANTID','VIVAWALLET_MERCHANTPASS','VIVAWALLET_SOURCE','VIVAWALLET_INSTAL','VIVAWALLET_URL','VIVAWALLET_CURRENCIES'));	
		
		
		
		if (isset($config['VIVAWALLET_MERCHANTID']))
			$this->MerchantId = $config['VIVAWALLET_MERCHANTID'];	
		if (isset($config['VIVAWALLET_MERCHANTPASS']))
			$this->MerchantPass = $config['VIVAWALLET_MERCHANTPASS'];	
		if (isset($config['VIVAWALLET_SOURCE']))
			$this->Source = $config['VIVAWALLET_SOURCE'];	
		if (isset($config['VIVAWALLET_INSTAL']))
			$this->wb_instal = $config['VIVAWALLET_INSTAL'];
		if (isset($config['VIVAWALLET_URL']))
			$this->wb_url = $config['VIVAWALLET_URL'];			
		if (isset($config['VIVAWALLET_CURRENCIES']))
			$this->currencies = $config['VIVAWALLET_CURRENCIES'];	
		
		parent::__construct();

		$this->page = basename(preg_replace('/old/', '', __FILE__), '.php');
		$this->description = $this->l('Accept payments with Vivawallet');
		
		if (!isset($this->MerchantId) OR !isset($this->MerchantPass))
			$this->warning = $this->l('your Vivawallet settings must be configured in order to use this module correctly');
		if (!Configuration::get('VIVAWALLET_CURRENCIES'))
		{
			$currencies = Currency::getCurrencies();
			$authorized_currencies = array();
			foreach ($currencies as $currency)
				$authorized_currencies[] = $currency['id_currency'];
			Configuration::updateValue('VIVAWALLET_CURRENCIES', implode(',', $authorized_currencies));
		}
	}
		

	function install()
	{
		$currencies = Currency::getCurrencies();
		$authorized_currencies = array();
		foreach ($currencies as $currency)
		$authorized_currencies[] = $currency['id_currency'];
		
		// SQL Table
		if (!file_exists(dirname(preg_replace('/old/', '', __FILE__)).'/'.self::INSTALL_SQL_FILE))
			die('error 1');
		elseif (!$sql = file_get_contents(dirname(preg_replace('/old/', '', __FILE__)).'/'.self::INSTALL_SQL_FILE))
			die('error 2');
		$sql = preg_split("/;\s*[\r\n]+/", $sql);
		foreach ($sql as $query)
			if ($query AND sizeof($query) AND !Db::getInstance()->Execute(trim($query)))
				return false;
			
		//hookDisplayPaymentEU - added hook
		if (!parent::install()
			OR !Configuration::updateValue('VIVAWALLET_CURRENCIES', implode(',', $authorized_currencies))
			OR !$this->registerHook('payment')
			OR !$this->registerHook('displayPaymentEU')
			OR !$this->registerHook('paymentReturn'))
			return false;
		return true;
	}
	
	
	
	function uninstall()
	{
		if (!Configuration::deleteByName('VIVAWALLET_MERCHANTID')
			OR !Configuration::deleteByName('VIVAWALLET_MERCHANTPASS')
			OR !Configuration::deleteByName('VIVAWALLET_INSTAL')
			OR !Configuration::deleteByName('VIVAWALLET_URL')
		    OR !Configuration::deleteByName('VIVAWALLET_SOURCE')
			OR !Configuration::deleteByName('VIVAWALLET_CURRENCIES')
			OR !parent::uninstall())
			return false;
		return true;		
	}	


	function getContent()
	{
		$this->_html = '<h2>'.$this->displayName.'</h2>';

		if (!empty($_POST))
		{
			
			if ($wb_instal = Tools::getValue('vivawallet_wb_instal'))
				Configuration::updateValue('VIVAWALLET_INSTAL', $wb_instal);
			if ($wb_url = Tools::getValue('vivawallet_wb_url'))
				Configuration::updateValue('VIVAWALLET_URL', $wb_url);		
			if ($MerchantId = Tools::getValue('vivawallet_MerchantId'))
				Configuration::updateValue('VIVAWALLET_MERCHANTID', $MerchantId);
			if ($MerchantPass = Tools::getValue('vivawallet_MerchantPass'))
				Configuration::updateValue('VIVAWALLET_MERCHANTPASS', $MerchantPass);
			if ($Source = Tools::getValue('vivawallet_Source'))
				Configuration::updateValue('VIVAWALLET_SOURCE', $Source);
			
			$this->_postValidation();
			if (!sizeof($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors AS $err)
					$this->_html .= "<div class='alert error'>{$err}</div>";
		}
		else
		{
			$this->_html .= "<br />";
		}

		$this->_displayvivawallet();
		$this->_displayForm();

		return $this->_html;
	}



	function hookPayment($params)
	{
		global $smarty, $cart, $cookie;
		
		$currency = Currency::getCurrencyInstance($cart->id_currency);
		
		$delivery = new Address(intval($cart->id_address_delivery));
		$invoice = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));		

		$currencies = $this->getCurrency((int)$cart->id_currency);
		$authorized_currencies = array_flip(explode(',', $this->currencies));
        $currencies_used = array();
		
	  
	  //currency correction
	  $authorized_currencies = explode(",", $this->currencies);
	  if(in_array((int)$currency->id, $authorized_currencies)){
	   $dest_currency['iso_code'] = $currency->iso_code;
	   $currency_override = '';
	  } else {
	   $currency = Currency::getCurrencyInstance((int)$authorized_currencies[0]);
	   $currencycart = Currency::getCurrencyInstance((int)$cart->id_currency);
	   $dest_currency['iso_code'] = $currency->iso_code;
	   $dest_currency['id'] = $currency->id;
	   $currency_override = $currency->id;
	  }
	  
      	$currency_symbol ='';
		$language_code ='';
		$eb_total ='';
		$ebfullname ='';
		
		$currency_symbol ='';
		switch ($dest_currency['iso_code']) {
		case 'EUR':
   		$currency_symbol = 978;
   		break;
		case 'GBP':
   		$currency_symbol = 826;
   		break;
		case 'BGN':
   		$currency_symbol = 975;
   		break;
		case 'RON':
   		$currency_symbol = 946;
   		break;
		default:
        $currency_symbol = 978;
		}
		
		$amount = $cart->getOrderTotal(true, Cart::BOTH);

		//currency correction
		if (isset($currency_override) && $currency_override!='')
		{
			$amount = ($amount / $currencycart->conversion_rate) * $currency->conversion_rate;
			$amount = Tools::convertPrice($amount, new Currency((int)($dest_currency['id'])));
		}	
		
		$wb_total = number_format($amount, 2, '.', '');		
		$wb_instal_total = round($amount);
		
		$wb_total_cents = number_format($amount, 2, '.', '')*100;		
		$wb_total_cents = round($wb_total_cents);

            $products = $cart->getProducts();
			
				
			foreach ($products as $key => $product)
			{
				$products[$key]['name'] = str_replace('"', '\'', $product['name']);
				$products[$key]['name'] = htmlentities(utf8_decode($product['name']));
			}									
					
	if(strtolower(Language::getIsoById($cookie->id_lang))=='el' || strtolower(Language::getIsoById($cookie->id_lang))=='gr'){
	$languagecode ='el-GR';
	} else {
	$languagecode ='en-US';
	}
	
	$MerchantID =  Configuration::get('VIVAWALLET_MERCHANTID');
	$Password =   html_entity_decode(Configuration::get('VIVAWALLET_MERCHANTPASS'));
	$BaseUrl =  trim(Configuration::get('VIVAWALLET_URL'));
	
	$poststring['Amount'] = $wb_total_cents;
	$poststring['RequestLang'] = $languagecode;
	$poststring['Email'] = $customer->email;
	
	if(isset($_REQUEST['wperiod']) && $_REQUEST['wperiod'] > 0){
	$poststring['MaxInstallments'] = $_REQUEST['wperiod'];
	} else {
	$poststring['MaxInstallments'] = '1';
	}

	$poststring['MerchantTrns'] = $cart->id;
	$poststring['SourceCode'] = Configuration::get('VIVAWALLET_SOURCE');
	$poststring['CurrencyCode'] = $currency_symbol;
	$poststring['PaymentTimeOut'] = '300';	
	$TmSecureKey = 'd2ViaXQuYnovbGljZW5zZS50eHQ='; // for extra encryption options
	
	$curl = curl_init($BaseUrl."/api/orders");
	curl_setopt($curl, CURLOPT_PORT, 443);
	
	$postargs = 'Amount='.urlencode($poststring['Amount']).'&RequestLang='.urlencode($poststring['RequestLang']).'&Email='.urlencode($poststring['Email']).'&MaxInstallments='.urlencode($poststring['MaxInstallments']).'&MerchantTrns='.urlencode($poststring['MerchantTrns']).'&SourceCode='.urlencode($poststring['SourceCode']).'&CurrencyCode='.urlencode($poststring['CurrencyCode']).'&PaymentTimeOut=300&DisableIVR=true';
	
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password); 
	$curlversion = curl_version();
	if(!preg_match("/NSS/" , $curlversion['ssl_version'])){
	curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, "TLSv1");
	}
	
	// execute curl
	$response = curl_exec($curl);
		
	if(curl_error($curl)){
	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	$response = curl_exec($curl);
	}
	
		curl_close($curl);
		
		try {
		if (version_compare(PHP_VERSION, '5.3.99', '>=')) {
		$resultObj=json_decode($response, false, 512, JSON_BIGINT_AS_STRING);
		} else {
		$response = preg_replace('/:\s*(\-?\d+(\.\d+)?([e|E][\-|\+]\d+)?)/', ': "$1"', $response, 1);
		$resultObj = json_decode($response);
		}
		} catch( Exception $e ) {
			throw new Exception("Result is not a json object (" . $e->getMessage() . ")");
		}
		
		if ($resultObj->ErrorCode==0){	//success when ErrorCode = 0
		$OrderCode = $resultObj->OrderCode;
		$ErrorCode = $resultObj->ErrorCode;
		$ErrorText = $resultObj->ErrorText;
		}
		else{
			throw new Exception("Unable to create order code (" . $resultObj->ErrorText . ")");
		}

	if (version_compare(_PS_VERSION_, '1.5', '<')){
	$seckey = $customer->secure_key;
	} else {
	$seckey = Context::getContext()->customer->secure_key;
	}
	
	$tmquery = "insert into vivawallet_data (secure_key, OrderCode, ErrorCode, ErrorText, Timestamp, ref, total_cost, currency, order_state) values ('".$seckey."','".$OrderCode."','".$ErrorCode."','".$ErrorText."',now(),'".$cart->id."','".$wb_total_cents."','".$dest_currency['iso_code']."','I')";
	Db::getInstance()->execute($tmquery); //tommodps15
	
	$this->VivawalletUrl = $BaseUrl.'/web/newtransaction.aspx';

		$wbsubmitbutton = '';
		$selectdisable = '';
		
		$instal_logic = Configuration::get('VIVAWALLET_INSTAL');
		if(isset($instal_logic) && $instal_logic !=''){
		$split_instal_vivawallet = explode(',', $instal_logic);
		$c = count ($split_instal_vivawallet);
		
		 if(isset($_REQUEST['wperiod'])){ 
		 $wbsubmitbutton = 'visibility:visible';
		 $selectdisable = ' disabled="disabled"';
		 } else {
		 $wbsubmitbutton = 'visibility:hidden';
		  $selectdisable = '';
		 }

		$instal_vivawallet = '';
		$instal_template = '';
		$instal_vivawallet .= $this->l('Instalments: ') . '<select '.$selectdisable.' name="wperiod" onChange="this.form.submit();">'."\n";
		
		$instal_vivawallet .= '<option value="">'.$this->l('Make a selection').'</option>'."\n";
		$instal_vivawallet .= '<option value="0"'. (isset($_REQUEST['wperiod']) && $_REQUEST['wperiod']=='0' ? ' selected="selected"' : '') . '>'.$this->l('No Instalments').'</option>'."\n";
	
		for($i=0; $i<$c; $i++)
		{
		list($instal_amount, $instal_term) = explode(":", $split_instal_vivawallet[$i]);
		
		if($wb_instal_total >= $instal_amount){
		$instal_template = 'true';
		$instal_vivawallet .= '<option value="'.$instal_term.'"'. (isset($_REQUEST['wperiod']) && $_REQUEST['wperiod']==$instal_term ? ' selected="selected"' : '') . '>'. $instal_term . $this->l(' instalments').'</option>'."\n"; 
		}
		}
		$instal_vivawallet .= '</select>'.$this->l(' wait until the confirmation button appears.').'<br><br>'."\n";
	
		
		} else {
		$instal_template = 'false';
		$instal_vivawallet = '<input type="hidden" name="wperiod" value="" />';
		}

		if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 1){
		$instalment_url = '?controller=order-opc&isPaymentStep=true';
		} else {
		    if ((_PS_VERSION_ >= '1.6')){
			isset($_GET['multishipping']) ? $multishipping = addslashes($_GET['multishipping']) : $multishipping = '';
			$instalment_url = '?controller=order&step=3&multishipping='.$multishipping;
			} else {
			$instalment_url = 'order.php?step=3';
    		}
		}

		$smarty->assign(array( 
		'VivawalletUrl' 		=> $this->VivawalletUrl,
		'InstalmentUrl'		=> $instalment_url,
		'Ref' 				=> $OrderCode,
		'WbInstalLogic' 	=> $instal_vivawallet,
		'WbSubmit' 			=> $wbsubmitbutton,
		'this_path' 		=> $this->_path,
		'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));
		
		$smarty->assign(array(
            'this_path' 		=> $this->_path,
            'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

		if($instal_template=='true'){
		return $this->display(preg_replace('/old/', '', __FILE__), 'payment.tpl');
		} else {
		return $this->display(preg_replace('/old/', '', __FILE__), 'payment_noinstall.tpl');
		}
	}

	function hookPaymentReturn($params)
	{
		global $smarty;
		$state = $params['objOrder']->getCurrentState();
		if ($state == _PS_OS_OUTOFSTOCK_ or $state == _PS_OS_PAYMENT_)
			$smarty->assign(array( //tommodps15 remove 4th -false- parameter
				'total_to_pay' 	=> Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
				'status' 		=> 'ok',
				'id_order' 		=> $params['objOrder']->id
			));
		else
			$smarty->assign('status', 'failed');

		return $this->display(preg_replace('/old/', '', __FILE__), 'payment_return.tpl');
	}

	//displayPaymentEU
	function hookDisplayPaymentEU($params)
	{
		global $smarty, $cart, $cookie;
		
		$currency = Currency::getCurrencyInstance($cart->id_currency);
		
		$delivery = new Address(intval($cart->id_address_delivery));
		$invoice = new Address(intval($cart->id_address_invoice));
		$customer = new Customer(intval($cart->id_customer));		

		$currencies = $this->getCurrency((int)$cart->id_currency);
		$authorized_currencies = array_flip(explode(',', $this->currencies));
        $currencies_used = array();
		
	  
	  //currency correction
	  $authorized_currencies = explode(",", $this->currencies);
	  if(in_array((int)$currency->id, $authorized_currencies)){
	   $dest_currency['iso_code'] = $currency->iso_code;
	   $currency_override = '';
	  } else {
	   $currency = Currency::getCurrencyInstance((int)$authorized_currencies[0]);
	   $currencycart = Currency::getCurrencyInstance((int)$cart->id_currency);
	   $dest_currency['iso_code'] = $currency->iso_code;
	   $dest_currency['id'] = $currency->id;
	   $currency_override = $currency->id;
	  }
	  
      	$currency_symbol ='';
		$language_code ='';
		$eb_total ='';
		$ebfullname ='';
		
		$currency_symbol ='';
		switch ($dest_currency['iso_code']) {
		case 'EUR':
   		$currency_symbol = 978;
   		break;
		case 'GBP':
   		$currency_symbol = 826;
   		break;
		case 'BGN':
   		$currency_symbol = 975;
   		break;
		case 'RON':
   		$currency_symbol = 946;
   		break;
		default:
        $currency_symbol = 978;
		}      
			
		$amount = $cart->getOrderTotal(true, Cart::BOTH);
		
		//currency correction
		if (isset($currency_override) && $currency_override!='')
		{
			$amount = ($amount / $currencycart->conversion_rate) * $currency->conversion_rate;
			$amount = Tools::convertPrice($amount, new Currency((int)($dest_currency['id'])));
		}	
		
		$wb_total = number_format($amount, 2, '.', '');		
		$wb_instal_total = round($amount);
		
		$wb_total_cents = number_format($amount, 2, '.', '')*100;		
		$wb_total_cents = round($wb_total_cents);

            $products = $cart->getProducts();
			
				
			foreach ($products as $key => $product)
			{
				$products[$key]['name'] = str_replace('"', '\'', $product['name']);
				$products[$key]['name'] = htmlentities(utf8_decode($product['name']));
			}									
					
	if(strtolower(Language::getIsoById($cookie->id_lang))=='el' || strtolower(Language::getIsoById($cookie->id_lang))=='gr'){
	$languagecode ='el-GR';
	} else {
	$languagecode ='en-US';
	}
	
	$MerchantID =  Configuration::get('VIVAWALLET_MERCHANTID');
	$Password =   Configuration::get('VIVAWALLET_MERCHANTPASS');
	$BaseUrl =   trim(Configuration::get('VIVAWALLET_URL'));
	
	$poststring['Amount'] = $wb_total_cents;
	$poststring['RequestLang'] = $languagecode;
	$poststring['Email'] = $customer->email;
	
	if(isset($_REQUEST['wperiod']) && $_REQUEST['wperiod'] > 0){
	$poststring['MaxInstallments'] = $_REQUEST['wperiod'];
	$modInstallments = $_REQUEST['wperiod'];
	} else {
	$poststring['MaxInstallments'] = '1';
	$modInstallments = '';
	}
	
	//hookDisplayPaymentEU
	if(isset($_REQUEST['wperiod'])){
	$showDropdown = '1';
	} else {
	$showDropdown = '';
	}

	$poststring['MerchantTrns'] = $cart->id;
	$poststring['SourceCode'] = Configuration::get('VIVAWALLET_SOURCE');
	$poststring['CurrencyCode'] = $currency_symbol;
	$poststring['PaymentTimeOut'] = '300';	
	$TmSecureKey = 'd2ViaXQuYnovbGljZW5zZS50eHQ='; // for extra encryption options
	
	$curl = curl_init($BaseUrl."/api/orders");
	curl_setopt($curl, CURLOPT_PORT, 443);
	
	$postargs = 'Amount='.urlencode($poststring['Amount']).'&RequestLang='.urlencode($poststring['RequestLang']).'&Email='.urlencode($poststring['Email']).'&MaxInstallments='.urlencode($poststring['MaxInstallments']).'&MerchantTrns='.urlencode($poststring['MerchantTrns']).'&SourceCode='.urlencode($poststring['SourceCode']).'&CurrencyCode='.urlencode($poststring['CurrencyCode']).'&PaymentTimeOut=300&DisableIVR=true';
	
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password); 
	$curlversion = curl_version();
	if(!preg_match("/NSS/" , $curlversion['ssl_version'])){
	curl_setopt($curl, CURLOPT_SSL_CIPHER_LIST, "TLSv1");
	}
	
	// execute curl
	$response = curl_exec($curl);
		
	if(curl_error($curl)){
	curl_setopt($curl, CURLOPT_PORT, 443);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $postargs);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_USERPWD, $MerchantID.':'.$Password);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	$response = curl_exec($curl);
	}
	
		curl_close($curl);
		
		try {
		if (version_compare(PHP_VERSION, '5.3.99', '>=')) {
		$resultObj=json_decode($response, false, 512, JSON_BIGINT_AS_STRING);
		} else {
		$response = preg_replace('/:\s*(\-?\d+(\.\d+)?([e|E][\-|\+]\d+)?)/', ': "$1"', $response, 1);
		$resultObj = json_decode($response);
		}
		} catch( Exception $e ) {
			throw new Exception("Result is not a json object (" . $e->getMessage() . ")");
		}
		
		if ($resultObj->ErrorCode==0){	//success when ErrorCode = 0
		$OrderCode = $resultObj->OrderCode;
		$ErrorCode = $resultObj->ErrorCode;
		$ErrorText = $resultObj->ErrorText;
		}
		else{
			throw new Exception("Unable to create order code (" . $resultObj->ErrorText . ")");
		}

	if (version_compare(_PS_VERSION_, '1.5', '<')){
	$seckey = $customer->secure_key;
	} else {
	$seckey = Context::getContext()->customer->secure_key;
	}
	
	$tmquery = "insert into vivawallet_data (secure_key, OrderCode, ErrorCode, ErrorText, Timestamp, ref, total_cost, currency, order_state) values ('".$seckey."','".$OrderCode."','".$ErrorCode."','".$ErrorText."',now(),'".$cart->id."','".$wb_total_cents."','".$dest_currency['iso_code']."','I')";
	Db::getInstance()->execute($tmquery); //tommodps15
	
	$this->VivawalletUrl = $BaseUrl.'/web/newtransaction.aspx';

		$wbsubmitbutton = '';
		$selectdisable = '';
		
		$instal_logic = Configuration::get('VIVAWALLET_INSTAL');
		if(isset($instal_logic) && $instal_logic !=''){
		$split_instal_vivawallet = explode(',', $instal_logic);
		$c = count ($split_instal_vivawallet);
		
		$instal_vivawallet = '';
		$instal_template = '';
		$instal_vivawallet .= $this->l('Instalments: ') . '<select '.$selectdisable.' name="wperiod" onChange="this.form.submit();">'."\n";
		
		$instal_vivawallet .= '<option value="">'.$this->l('Make a selection').'</option>'."\n";
		$instal_vivawallet .= '<option value="0"'. (isset($_REQUEST['wperiod']) && $_REQUEST['wperiod']=='0' ? ' selected="selected"' : '') . '>'.$this->l('No Instalments').'</option>'."\n";
	
		for($i=0; $i<$c; $i++)
		{
		list($instal_amount, $instal_term) = explode(":", $split_instal_vivawallet[$i]);
		
		//hookDisplayPaymentEU
		if($wb_instal_total >= $instal_amount){
		$instal_template = 'true';
		$instal_vivawallet .= '<option value="'.$instal_term.'"'. (isset($_REQUEST['wperiod']) && $_REQUEST['wperiod']==$instal_term ? ' selected="selected"' : '') . '>'. $instal_term . $this->l(' instalments').'</option>'."\n"; 
		}
		}
		$instal_vivawallet .= '</select>'."\n";
	
		
		} else {
		$instal_template = 'false';
		$instal_vivawallet = '<input type="hidden" name="wperiod" value="" />';
		}

		//hookDisplayPaymentEU
		$smarty->assign(array( 
		'VivawalletUrl' 		=> $this->VivawalletUrl,
		'Ref' 				=> $OrderCode,
		'WbInstalLogic' 	=> $instal_vivawallet,
		'this_path' 		=> $this->_path,
		'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));
		
		$smarty->assign(array(
            'this_path' 		=> $this->_path,
            'this_path_ssl' 	=> Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));

		//hookDisplayPaymentEU
		if($instal_template=='true'){
		
			if($showDropdown!='1'){
			return array(
					'cta_text' => $this->l('Pay with Viva') . '<form id="vivawallet_confirmation_form" name="vivawallet_confirmation" data-ajax="false" action="'.Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'].'" method="post">'.$instal_vivawallet.'</form>',
					'logo' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/vivawallet.gif'),
					'form' => $this->display(preg_replace('/old/', '', __FILE__), 'payment_eu.tpl')
				);
			} else {
			
				if(isset($modInstallments) && $modInstallments > 0){
				 $instal_title = $this->l(' - Instalments: ') . $modInstallments . ' <img alt="'.$this->l('Reset').'" src="'.Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/reset_eu.gif').'" onclick="window.location.href=\''.Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'].'\'" />';
				 $instal_logo = Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/vivawallet_eu.gif');
				} else {
				 $instal_title = '';
				 $instal_logo = Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/vivawallet.gif');
				}
			
			return array(
					'cta_text' => $this->l('Pay with Viva') . $instal_title,
					'logo' => $instal_logo,
					'form' => $this->display(preg_replace('/old/', '', __FILE__), 'payment_eu.tpl')
				);
			}	
		
		} else {
		return array(
				'cta_text' => $this->l('Pay with Viva'),
				'logo' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/vivawallet.gif'),
				'form' => $this->display(preg_replace('/old/', '', __FILE__), 'payment_noinstall_eu.tpl')
			);
		}
	}

	private function _postValidation()
	{
		if (isset($_POST['btnSubmit']))
		{
			if (empty($_POST['vivawallet_MerchantId']))
				$this->_postErrors[] = $this->l('Your MerchantId is required.');	
			if (empty($_POST['vivawallet_MerchantPass']))
				$this->_postErrors[] = $this->l('Your MerchantPass is required.');	
		}
		elseif (isset($_POST['currenciesSubmit']))
		{
			$currencies = Currency::getCurrencies();
			$authorized_currencies = array();
			foreach ($currencies as $currency)
				if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
					$authorized_currencies[] = $currency['id_currency'];
			if (!sizeof($authorized_currencies))
				$this->_postErrors[] = $this->l('at least one currency is required.');
		}
	}

	private function _postProcess()
	{
		if (isset($_POST['btnSubmit']))
		{
			Configuration::updateValue('VIVAWALLET_MERCHANTID', trim($_POST['vivawallet_MerchantId']));
			Configuration::updateValue('VIVAWALLET_MERCHANTPASS', trim($_POST['vivawallet_MerchantPass']));
			Configuration::updateValue('VIVAWALLET_INSTAL', trim($_POST['vivawallet_wb_instal']));
			Configuration::updateValue('VIVAWALLET_URL', trim($_POST['vivawallet_wb_url']));
			Configuration::updateValue('VIVAWALLET_SOURCE', trim($_POST['vivawallet_Source']));
		}
		elseif (isset($_POST['currenciesSubmit']))
		{
			$currencies = Currency::getCurrencies();
			$authorized_currencies = array();
			foreach ($currencies as $currency)
				if (isset($_POST['currency_'.$currency['id_currency']]) AND $_POST['currency_'.$currency['id_currency']])
					$authorized_currencies[] = $currency['id_currency'];
			Configuration::updateValue('VIVAWALLET_CURRENCIES', implode(',', $authorized_currencies));
		}
		$ok = $this->l('Ok');
		$updated = $this->l('Settings Updated');
		$this->_html .= "<div class='conf confirm'><img src='../modules/vivawallet/ok.gif' alt='{$ok}' />{$updated}</div>";
	}
	
	private function _displayvivawallet()
	{
		$modDesc 	= $this->l('This module allows you to accept payments using Vivawallet.');
		$modStatus	= $this->l('Vivawallet online banking service could be the right solution for you');
		$modconfirm	= $this->l('');
		$this->_html .= "<img src='../modules/vivawallet/vivawallet.gif' style='float:left; margin-right:15px;' />
						<b>{$modDesc}</b>
						<br />
						{$modconfirm}
						<br />
						<br />
						<br />";
	}




	private function _displayForm()
	{
		$modvivawallet			= $this->l('Vivawallet Setup');
		$modvivawalletDesc		= $this->l('Please specify the gateway settings');	
		$modInstalLabel			= $this->l('Instalment logic');
		$modInstalDescription	= $this->l('Instalment logic example: 300:3,600:6 -> Order total 300 euro: allow 3 instalments, order total 600: allow 3 and 6 instalments.');
		
		$modUrlLabel			= $this->l('Base URL');
		$modUrlDescription	= $this->l('Use https://www.vivapayments.com for live and https://demo.vivapayments.com for demo environment.');
		
		$modMerchantId		= $this->l('MerchantId');
		$modMerchantPass		= $this->l('API Key');
		$modSource			= $this->l('Source Code');

		$modCurrencies				= $this->l('Currencies');
		$modUpdateSettings 			= $this->l('Update settings');
		$modCurrenciesDescription	=$this->l('Currencies authorized for Vivawallet payment.');
		$modAuthorizedCurrencies	= $this->l('Authorized currencies');		
		
		$this->_html .=
		"<form action='{$_SERVER['REQUEST_URI']}' method='post'>
			<fieldset>
			<legend><img src='../modules/vivawallet/access.png' />{$modvivawallet}</legend>
				<table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
					<tr>
						<td colspan='2'>
							{$modvivawalletDesc}<br /><br />
						</td>
					</tr>";
					
	$this->_html .="<tr>
						<td colspan='2'>
							{$modInstalDescription}
							<br />
							<br />
						</td>
					</tr><tr>
						<td width='130'>{$modInstalLabel}<br /><br /></td>
						<td>";
	$this->_html .= '<input type="text" name="vivawallet_wb_instal" value="'.Tools::getValue('vivawallet_wb_instal', Configuration::get('VIVAWALLET_INSTAL')).'" />';
											
	$this->_html .= "<br /><br /></td>
					</tr>";		
					
	$this->_html .="<tr>
						<td colspan='2'>
							{$modUrlDescription}
							<br />
							<br />
						</td>
					</tr><tr>
						<td width='130'>{$modUrlLabel}<br /><br /></td>
						<td>";
	$this->_html .= '<input type="text" name="vivawallet_wb_url" value="'.Tools::getValue('vivawallet_wb_url', Configuration::get('VIVAWALLET_URL')).'" />';
											
	$this->_html .= "<br /><br /></td>
					</tr>";							
					
	$this->_html .="<tr>
						<td width='130'>{$modMerchantId}<br /><br /></td>
						<td>";
	$this->_html .= '<input type="text" name="vivawallet_MerchantId" value="'.Tools::getValue('vivawallet_MerchantId', Configuration::get('VIVAWALLET_MERCHANTID')).'" />';
	$this->_html .= "<br /><br /></td>
					</tr>";	
	$this->_html .="<tr>
						<td width='130'>{$modMerchantPass}<br /><br /></td>
						<td>";
	$this->_html .= '<input type="text" name="vivawallet_MerchantPass" value="'.Tools::getValue('vivawallet_MerchantPass', Configuration::get('VIVAWALLET_MERCHANTPASS')).'" />';
	$this->_html .= "<br /><br /></td>
					</tr>";	
									
	$this->_html .="<tr>
						<td width='130'>{$modSource}<br /><br /></td>
						<td>";
	$this->_html .= '<input type="text" name="vivawallet_Source" value="'.Tools::getValue('vivawallet_Source', Configuration::get('VIVAWALLET_SOURCE')).'" />';
	$this->_html .= "<br /><br /></td>
					</tr>";		
					

	$this->_html .= "<tr>
						<td colspan='2' align='center'>
							<input class='button' name='btnSubmit' value='{$modUpdateSettings}' type='submit' />
						</td>
					</tr>
				</table>
			</fieldset>
		</form>
		<br />
		<br />
		<form action='{$_SERVER['REQUEST_URI']}' method='post'>
			<fieldset>
			<legend><img src='../modules/vivawallet/dollar.gif' />{$modAuthorizedCurrencies}</legend>
				<table border='0' width='500' cellpadding='0' cellspacing='0' id='form'>
					<tr>
						<td colspan='2'>
							{$modCurrenciesDescription}
							<br />
							<br />
						</td>
					</tr>			
					<tr>
						<td width='130' style='height: 35px; vertical-align:top'>{$modCurrencies}</td>
						<td>";
			$currencies = Currency::getCurrencies();
			$authorized_currencies = array_flip(explode(',', Configuration::get('VIVAWALLET_CURRENCIES')));
			foreach ($currencies as $currency)
				$this->_html .= '<label style="float:none; "><input type="checkbox" value="true" name="currency_'.$currency['id_currency'].'"'.(isset($authorized_currencies[$currency['id_currency']]) ? ' checked="checked"' : '').' />&nbsp;<span style="font-weight:bold;">'.$currency['name'].'</span> ('.$currency['sign'].')</label><br />';
				$this->_html .="
						</td>
					</tr>					
					<tr>
						<td colspan='2' align='center'>
							<br />
							<input class='button' name='currenciesSubmit' value='{$modUpdateSettings}' type='submit' />
						</td>
					</tr>
				</table>
			</fieldset>
		</form>";
	}
}

?>
