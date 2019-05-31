<?php
/**
 * TODO: USB Modem Toggle over [/sys/class/gpio/gpio2/value]
 */

/**
* https://github.com/HSPDev/Huawei-E5180-API
*/
class CustomHttpClient
{
	private $connectionTimeout = 3;
	private $responseTimeout = 5;
	//The API gives us cookie data in an API request, so manual cookies.
	private $manualCookieData = '';
	//We will have up to three tokens being used at once by the router? Whut?
	private $requestToken = '';
	private $requestTokenOne = '';
	private $requestTokenTwo = '';
	/**
	* We will call this, when we have parsed the data out from a login request.
	*/
	public function setSecurity($cookie, $token)
	{
		$this->manualCookieData = $cookie;
		$this->requestToken = $token;
	}
	/**
	* We need the current token to make the login hash.
	*/
	public function getToken()
	{
		return $this->requestToken;
	}
	/**
	* Builds the Curl Object.
	*/
	private function getCurlObj($url, $headerFields = array())
	{
		$ch = curl_init();
		//curl_setopt($ch, CURLOPT_VERBOSE, true); // DEBUGGING 
		$header= array(
			'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8;charset=UTF-8',
			'Accept-Language: da-DK,da;q=0.8,en-US;q=0.6,en;q=0.4',
			'Accept-Charset: utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			//The router expects these two to be there, but empty, when not in use.
			'Cookie: '.$this->manualCookieData, 
			'__RequestVerificationToken: '.$this->requestToken 
		);
		foreach($headerFields as $h)
		{
			$header[] = $h;
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->responseTimeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch,CURLOPT_ENCODING , "gzip"); //The router is fine with this, so no problem.
		curl_setopt($ch,CURLOPT_HTTPHEADER, $header);
		//The router rotates tokens in the response headers randomly, so we will parse them all.
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'HandleHeaderLine'));
		return $ch;
	} //end function
	/**
	* Makes HTTP POST requests containing XML data to the router.
	*/
	public function postXml($url, $xmlString)
	{
		//The API wants it like this.
		$ch = $this->getCurlObj($url, array('Content-Type: text/plain; charset=UTF-8', 'Cookie2: $Version=1'));
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlString);
		$result=curl_exec($ch);
		curl_close($ch);
		if(!$result)
		{
			throw new \Exception("A network error occured with cURL.");	
		}
		return $result;
	} 
	/**
	* Handles the HTTP Response header lines from cURL requests, 
	* so we can extract all those tokens.
	*/
	public function HandleHeaderLine( $curl, $header_line ) {
		/*
		* Not the prettiest way to parse it out, but hey it works.
		* If adding more or changing, remember the trim() call 
		* as the strings have nasty null bytes.
		*/
	    if(strpos($header_line, '__RequestVerificationTokenOne') === 0)
	    {
	    	$token = trim(substr($header_line, strlen('__RequestVerificationTokenOne:')));
	    	$this->requestTokenOne = $token;
	    }
	    elseif(strpos($header_line, '__RequestVerificationTokenTwo') === 0)
	    {
	    	$token = trim(substr($header_line, strlen('__RequestVerificationTokenTwo:')));
	    	$this->requestTokenTwo = $token;
	    }
	    elseif(strpos($header_line, '__RequestVerificationToken') === 0)
	    {
	    	$token = trim(substr($header_line, strlen('__RequestVerificationToken:')));
	    	$this->requestToken = $token;
	    }
	    elseif(strpos($header_line, 'Set-Cookie:') === 0)
	    {
	    	$cookie = trim(substr($header_line, strlen('Set-Cookie:')));
	    	$this->manualCookieData = $cookie;
	    }
	    return strlen($header_line);
	}
	/**
	* Performs a HTTP GET to the specified URL.
	*/
	public function get($url)
	{
		$ch = $this->getCurlObj($url);
		$result=curl_exec($ch);
		curl_close($ch);
		if(!$result)
		{
			throw new \Exception("A network error occured with cURL.");	
		}
		return $result;
	} 
}
class Router
{
	private $http = null; //Our custom HTTP provider.
	private $routerAddress = 'http://192.168.8.1'; //This is the one for the router I got.
	//These two we need to acquire through an API call.
	private $sessionInfo = '';
	private $tokenInfo = '';
	public function __construct()
	{
		$this->http = new CustomHttpClient();
	}
	/**
	* Sets the router address.
	*/
	public function setAddress($address)
	{
		//Remove trailing slash if any.
		$address = rtrim($address, '/');
		//If not it starts with http, we assume HTTP and add it.
		if(strpos($address, 'http') !== 0)
		{
			$address = 'http://'.$address;
		}
		$this->routerAddress = $address;
	}
	/**
	* Most API responses are just simple XML, so to avoid repetition
	* this function will GET the route and return the object.
	* @return SimpleXMLElement
	*/
	public function generalizedGet($route)
	{
		//Makes sure we are ready for the next request.
		$this->prepare();
		$xml = $this->http->get($this->getUrl($route));
		$obj = new \SimpleXMLElement($xml);
		//Check for error message
		if(property_exists($obj, 'code'))
		{
			throw new \UnexpectedValueException('The API returned error code: '.$obj->code);
		}
		return $obj;
	}
	public function getStatus()
	{
		return $this->generalizedGet('api/monitoring/status');
	}
	/**
	* Gets traffic statistics (numbers are in bytes)
	* @return SimpleXMLElement
	*/
	public function getTrafficStats()
	{
		return $this->generalizedGet('api/monitoring/traffic-statistics');
	}
	/**
	* Gets monthly statistics (numbers are in bytes)
	* This probably only works if you have setup a limit.
	* @return SimpleXMLElement
	*/
	public function getMonthStats()
	{
		return $this->generalizedGet('api/monitoring/month_statistics');
	}
	/**
	* Info about the current mobile network. (PLMN info)
	* @return SimpleXMLElement
	*/
	public function getNetwork()
	{
		return $this->generalizedGet('api/net/current-plmn');
	}
	/**
	* Gets the current craddle status
	* @return SimpleXMLElement
	*/
	public function getCraddleStatus()
	{
		return $this->generalizedGet('api/cradle/status-info');
	}
	/**
	* Get current SMS count
	* @return SimpleXMLElement
	*/
	public function getSmsCount()
	{
		return $this->generalizedGet('api/sms/sms-count');
	}
	/**
	* Get current WLAN Clients
	* @return SimpleXMLElement
	*/
	public function getWlanClients()
	{
		return $this->generalizedGet('api/wlan/host-list');
	}
	/**
	* Get notifications on router
	* @return SimpleXMLElement
	*/
	public function getNotifications()
	{
		return $this->generalizedGet('api/monitoring/check-notifications');
	}
	/**
	* Gets the SMS inbox. 
	* Page parameter is NOT null indexed and starts at 1.
	* I don't know if there is an upper limit on $count. Your milage may vary.
	* unreadPrefered should give you unread messages first.
	* @return boolean
	*/
	public function setLedOn($on = false)
	{
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		$ledXml = '<?xml version:"1.0" encoding="UTF-8"?><request><ledSwitch>'.($on ? '1' : '0').'</ledSwitch></request>';
		$xml = $this->http->postXml($this->getUrl('api/led/circle-switch'), $ledXml);
		$obj = new \SimpleXMLElement($xml);
		//Simple check if login is OK.
		return ((string)$obj == 'OK');
	}
	/**
	* Checks whatever we are logged in
	* @return boolean
	*/
	public function getLedStatus()
	{
		$obj = $this->generalizedGet('api/led/circle-switch');
		if(property_exists($obj, 'ledSwitch'))
		{
			if($obj->ledSwitch == '1')
			{
				return true;
			}
		}
		return false;
	}
	/**
	* Checks whatever we are logged in
	* @return boolean
	*/
	public function isLoggedIn()
	{
		$obj = $this->generalizedGet('api/user/state-login');
		if(property_exists($obj, 'State'))
		{
			/*
			* Logged out seems to be -1
			* Logged in seems to be 0.
			* What the hell?
			*/
			if($obj->State == '0')
			{
				return true;
			}
		}
		return false;
	}
	/**
	* Gets the SMS inbox. 
	* Page parameter is NOT null indexed and starts at 1.
	* I don't know if there is an upper limit on $count. Your milage may vary.
	* unreadPrefered should give you unread messages first.
	* @return SimpleXMLElement
	*/
	public function getInbox($page = 1, $count = 20, $unreadPreferred = false)
	{
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		$inboxXml = '<?xml version="1.0" encoding="UTF-8"?><request>
			<PageIndex>'.$page.'</PageIndex>
			<ReadCount>'.$count.'</ReadCount>
			<BoxType>1</BoxType>
			<SortType>0</SortType>
			<Ascending>0</Ascending>
			<UnreadPreferred>'.($unreadPreferred ? '1' : '0').'</UnreadPreferred>
			</request>
		';
		$xml = $this->http->postXml($this->getUrl('api/sms/sms-list'), $inboxXml);
		$obj = new \SimpleXMLElement($xml);
		return $obj;
	}
	/**
	* Deletes an SMS by ID, also called "Index".
	* The index on the Message object you get from getInbox
	* will contain an "Index" property with a value like "40000" and up.
	* Note: Will return true if the Index DOES NOT exist already.
	* @return boolean
	*/
	public function deleteSms($index)
	{
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		$deleteXml = '<?xml version="1.0" encoding="UTF-8"?><request>
			<Index>'.$index.'</Index>
			</request>
		';
		$xml = $this->http->postXml($this->getUrl('api/sms/delete-sms'), $deleteXml);
		$obj = new \SimpleXMLElement($xml);
		//Simple check if login is OK.
		return ((string)$obj == 'OK');
	}
	/**
	* Sends SMS to specified receiver. I don't know if it works for foreign numbers, 
	* but for local numbers you can just specifiy the number like you would normally 
	* call it and it should work, here in Denmark "42952777" etc (mine).
	* Message parameter got the normal SMS restrictions you know and love.
	* @return boolean
	*/
	public function sendSms($receiver, $message)
	{
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		/*
		* Note how it wants the length of the content also.
		* It ALSO wants the current date/time wtf? Oh well.. 
		*/
		$sendSmsXml = '<?xml version="1.0" encoding="UTF-8"?><request>
			<Index>-1</Index>
			<Phones>
				<Phone>'.$receiver.'</Phone>
			</Phones>
			<Sca/>
			<Content>'.$message.'</Content>
			<Length>'.strlen($message).'</Length>
			<Reserved>1</Reserved>
			<Date>'.date('Y-m-d H:i:s').'</Date>
			<SendType>0</SendType>
			</request>
		';
		$xml = $this->http->postXml($this->getUrl('api/sms/send-sms'), $sendSmsXml);
		$obj = new \SimpleXMLElement($xml);
		//Simple check if login is OK.
		return ((string)$obj == 'OK');
	}
	/**
	* Not all methods may work if you don't login.
	* Please note that the router is pretty aggressive 
	* at timing your session out. 
	* Call something periodically or just relogin on error.
	* @return boolean
	*/
	public function login($username, $password)
	{
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		/*
		* Note how the router wants the password to be the following:
		* 1) Hashed by SHA256, then the raw output base64 encoded.
		* 2) The username is appended with the result of the above, 
		*	 AND the current token. Yes, the password changes everytime 
		*	 depending on what token we got. This really fucks with scrapers.
		* 3) The string from above (point 2) is then hashed by SHA256 again, 
		*    and the raw output is once again base64 encoded.
		* 
		* This is how the router login process works. So the password being sent 
		* changes everytime depending on the current user session/token. 
		* Not bad actually.
		*/
		$loginXml = '<?xml version="1.0" encoding="UTF-8"?><request>
		<Username>'.$username.'</Username>
		<password_type>4</password_type>
		<Password>'.base64_encode(hash('sha256', $username.base64_encode(hash('sha256', $password, false)).$this->http->getToken(), false)).'</Password>
		</request>
		';
		$xml = $this->http->postXml($this->getUrl('api/user/login'), $loginXml);
		$obj = new \SimpleXMLElement($xml);
		//Simple check if login is OK.
		return ((string)$obj == 'OK');
	}
	/**
	 * Sets the data switch to enable or disable the mobile connection.
	 * @return boolean
	 */
	public function setDataSwitch($value) {
		if (is_int($value) === false) {
			throw new \Exception('Parameter can only be integer.');
		}
		if ($value !== 0 && $value !== 1) {
			throw new \Exception('Parameter can only be integer.');
		}
		//Makes sure we are ready for the next request.
		$this->prepare(); 
		$dataSwitchXml = '<?xml version="1.0" encoding="UTF-8"?><request><dataswitch>'.$value.'</dataswitch></request>';
		$xml = $this->http->postXml($this->getUrl('api/dialup/mobile-dataswitch'), $dataSwitchXml);
		$obj = new \SimpleXMLElement($xml);
		
		//Simple check if login is OK.
		return ((string)$obj == 'OK');
	}
	/**
	* Internal helper that lets us build the complete URL 
	* to a given route in the API
	* @return string
	*/
	private function getUrl($route)
	{
		return $this->routerAddress.'/'.$route;
	}
	/**
	* Makes sure that we are ready for API usage.
	*/
	private function prepare()
	{
		//Check to see if we have session / token.
		if(strlen($this->sessionInfo) == 0 || strlen($this->tokenInfo) == 0)
		{
			//We don't have any. Grab some.
			$xml = $this->http->get($this->getUrl('api/webserver/SesTokInfo'));
			$obj = new \SimpleXMLElement($xml);
			if(!property_exists($obj, 'SesInfo') || !property_exists($obj, 'TokInfo'))
			{
				throw new \RuntimeException('Malformed XML returned. Missing SesInfo or TokInfo nodes.');
			}
			//Set it for future use.
			$this->http->setSecurity($obj->SesInfo, $obj->TokInfo);
		}
	}
}
function ESPMessage($type, $data) {
	$fp = fopen('/dev/ttyS0', 'w');
	fwrite($fp, 'ESP' . $type . json_encode($data) . "\n");
	fclose($fp);
}
$networkLecture = array();
$csv = file_get_contents('/root/NetworkNames.csv');
$csv = explode("\n", $csv);
array_shift($csv); array_pop($csv);
foreach ($csv as $line) {
  $line = str_replace(chr( 194 ) . chr( 160 ), ' ', $line);
	$line = str_replace("\r", ' ', $line);
	$line = trim($line);
  $data = explode(",", $line);
  $networkLecture[ intval($data[0]) ] = array(
    '_' => $data[1],
    'D' => $data[2],
    'G' => $data[3],
    'T' => $data[4],
    'N' => $data[5],    
  );
}

$router = new Router();
$router->setAddress('192.168.8.1');

ESPMessage("BOOTLOG", true);
while (true) {
	try {
		$router->login('admin', 'admin');
		break;
	} catch (Exception $e) {
		echo 'Exception abgefangen: ',  $e->getMessage(), "\n";
	}
}

ESPMessage("MODEM", array());
$res = array();
function translateNetworkName($type, $typeex) {
  global $networkLecture;
  if (isset($networkLecture[ $typeex ])) return $networkLecture[ $typeex ];
  if (isset($networkLecture[ $type ])) return $networkLecture[ $type ];
  return $networkLecture[0];
}
function fetch() {
	global $router;
  $res = array();
  #global $res;

	echo "requesting...\n";

  $deviceSignal = $router->generalizedGet('api/device/signal');
  $monitoringStatus = $router->generalizedGet('api/monitoring/status');
  $currentPLMN = $router->generalizedGet('api/net/current-plmn');
	$trafficStatistics = $router->generalizedGet('api/monitoring/traffic-statistics');

	$deviceInformation = $router->generalizedGet('api/device/information');
	$deviceInformationParams = $router->generalizedGet('config/deviceinformation/add_param.xml');
  # Connection
  $res['Connection'] = array(
    'Status' => intval($monitoringStatus->ConnectionStatus), # 900 Connecting | 901 Connected | 902 Disconnected | 903 Disconnecting
    'Now' => array(
      /*'Time' =>*/ intval($trafficStatistics->CurrentConnectTime),
      /*'Up' =>*/ intval($trafficStatistics->CurrentUpload),
      /*'Down' =>*/ intval($trafficStatistics->CurrentDownload),
      /*'UpRate' =>*/ intval($trafficStatistics->CurrentUploadRate),
      /*'DownRate' =>*/ intval($trafficStatistics->CurrentDownloadRate),
    ),
    'Total' => array(
      /*'Time' =>*/ intval($trafficStatistics->TotalConnectTime),
      /*'Up' =>*/ intval($trafficStatistics->TotalUpload),
      /*'Down' =>*/ intval($trafficStatistics->TotalDownload)
    ),
  );

  # Service
  $res['Service'] = array(
    'Available' => strlen( $currentPLMN->FullName ) > 1,
    'Domain' => intval($monitoringStatus->CurrentServiceDomain), # 0 No Service | 2 Available
    'Status' => intval($monitoringStatus->ServiceStatus), # 0 No Service | 2 Available
	);

  # Network
  $_Rat = 'I' . intval($currentPLMN->rat);
  switch ( intval($currentPLMN->rat) ) {
    case 0: $_Rat = '2G'; break;
    case 2: $_Rat = '3G'; break;
    case 5: $_Rat = 'HSPA'; break;
    case 7: $_Rat = '4G'; break;
  }
  $_Network_Type = intval($monitoringStatus->CurrentNetworkType);
  $_Network_TypeEx = intval($monitoringStatus->CurrentNetworkTypeEx);
	$_NetworkName = "Unbekannt";

	if (property_exists($currentPLMN, 'FullName')) $_NetworkName = strval($currentPLMN->FullName);
	if (property_exists($currentPLMN, 'ShortName')) $_NetworkName = strval($currentPLMN->ShortName);

  $res['Network'] = array(
    'Rate' => $_Rat,
    'Name' => $_NetworkName,
    'IP' => strval($monitoringStatus->WanIPAddress),
    'Roaming' => intval($monitoringStatus->RoamingStatus),
    '_' => $_Network_Type,
    '__' => $_Network_TypeEx,
		'Type' => translateNetworkName($_Network_Type, $_Network_TypeEx),
		'Numeric' => intval($currentPLMN->Numeric),
		'Cell' => array(intval('0x'.$deviceInformationParams->cell_id, 16), strval($deviceInformationParams->cell_id)),
		'LAC' => array(intval('0x'.$deviceInformationParams->lac, 16), strval($deviceInformationParams->lac)),
		#'CNT' => intval($deviceInformationParams->cnt, 10),
	);

	# Signal
  $RSSI = strval($deviceSignal->rssi); $RSSI = substr($RSSI, 0, strpos($RSSI, 'dBm')); // -90dBm
  $RSCP = strval($deviceSignal->rscp); $RSCP = substr($RSCP, 0, strpos($RSCP, 'dBm')); // -105dBm
  $ECIO = strval($deviceSignal->ecio); $ECIO = substr($ECIO, 0, strpos($ECIO, 'dB'));  // -14dB

  $res['Signal'] = array(
    'Icon' => intval($monitoringStatus->SignalIcon),
    'RSSI' => intval($RSSI),
    'RSCP' => intval($RSCP),
    'ECIO' => intval($ECIO)
  );

  return $res;
}

while (true) {
	try {
		$res = fetch();
		print_r($res);
	
		ESPMessage("MODEM", $res);
	} catch (Exception $e) {
		echo 'Exception abgefangen: ',  $e->getMessage(), "\n";
	}
  sleep(1);
}
