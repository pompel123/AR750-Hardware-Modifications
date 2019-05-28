<?php
$networkLecture = array();
$csv = file_get_contents('/root/NetworkNames.csv');
$csv = explode("\n", $csv);
array_shift($csv); array_pop($csv);
foreach ($csv as $line) {
  $line = str_replace(chr( 194 ) . chr( 160 ), ' ', $line);
  $data = explode(",", $line);
  $networkLecture[ intval($data[0]) ] = array(
    '_' => $data[1],
    'D' => $data[2],
    'G' => $data[3],
    'T' => $data[4],
    'N' => $data[5],    
  );
}
#print_r($networkLecture);
#exit;

$res = array();

function fetchTag($xml, $tag) {
  $start = "<$tag>";
  $res = substr($xml, strpos($xml, $start) + strlen($start));
  $res = substr($res, 0, strpos($res, "</$tag>"));
  return $res;
}
function translateNetworkName($type, $typeex) {
  global $networkLecture;
  if (isset($networkLecture[ $typeex ])) return $networkLecture[ $typeex ];
  if (isset($networkLecture[ $type ])) return $networkLecture[ $type ];
  return $networkLecture[0];
}
function modemApi($url, $cookie) {
  exec('curl "http://192.168.8.1/api/device/signal" -H "Cookie: ' . $cookie . '" -s | cat -', $output); 
  $output = implode("\n", $output);

  $request = curl_init();
  curl_setopt($request, CURLOPT_URL, $url);
  curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($request, CURLOPT_TIMEOUT, 2);
  curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($request, CURLOPT_TIMEOUT, 2);
  curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($request, CURLOPT_HEADER, 0);
  curl_setopt($request, CURLOPT_HTTPHEADER, array("Cookie: $cookie"));
  $result = curl_exec($request);
  curl_close($request);
  return $result;
}
function fetch() {
  $res = array();
  #global $res;

  $cookieRequest = curl_init();
  curl_setopt($cookieRequest, CURLOPT_URL, "192.168.8.1/html/home.html");
  curl_setopt($cookieRequest, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($cookieRequest, CURLOPT_TIMEOUT, 2);
  curl_setopt($cookieRequest, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($cookieRequest, CURLOPT_HEADER, 1);


  $cookieRequestResult = curl_exec($cookieRequest);
  $curl_error = curl_error($cookieRequest);
  curl_close($cookieRequest);

  if ($cookieRequestResult === false) {
    echo $curl_error;
    $res['Error'] = $curl_error;
    $res['Offline'] = 1;
    return $res;
  }
  $cookie = substr($cookieRequestResult, strpos($cookieRequestResult, 'Set-Cookie:') + 11);
  $cookie = substr($cookie, 0, strpos($cookie, "\n"));

  #$res['Cookie'] = $cookie;
  $res['Error'] = false;
  $res['Offline'] = 0;

  echo "requesting...\n";
  $deviceSignal = modemApi("http://192.168.8.1/api/device/signal", $cookie);
  $monitoringStatus = modemApi("http://192.168.8.1/api/monitoring/status", $cookie);
  $currentPLMN = modemApi("http://192.168.8.1/api/net/current-plmn", $cookie);
  #echo $currentPLMN . "\n";
  #echo $deviceSignal . "\n";

  # Connection
  $res['Connection_Status'] = intval(fetchTag($monitoringStatus, 'ConnectionStatus')); # 900 Connecting | 901 Connected | 902 Disconnected | 903 Disconnecting

  # Service
  $res['Service'] = array(
    'Available' => strlen( fetchTag($currentPLMN, 'FullName') ) > 1,
    'Domain' => intval(fetchTag($monitoringStatus, 'CurrentServiceDomain')), # 0 No Service | 2 Available
    'Status' => intval(fetchTag($monitoringStatus, 'ServiceStatus')), # 0 No Service | 2 Available
  );
  # Network
  $_Rat = intval(fetchTag($currentPLMN, 'rat'));
  switch ( $Rat ) {
    case 0: $_Rat = '2G'; break;
    case 2: $_Rat = '3G'; break;
    case 5: $_Rat = 'HSPA'; break;
    case 7: $_Rat = '4G'; break;
    default: $_Rat = 'I' . $_Rat; break;
  }

  $_Network_Type = intval(fetchTag($monitoringStatus, 'CurrentNetworkType'));
  $_Network_TypeEx = intval(fetchTag($monitoringStatus, 'CurrentNetworkTypeEx'));

  $res['Network'] = array(
    'Rate' => $_Rat,
    'Name' => fetchTag($currentPLMN, 'FullName'),
    'IP' => fetchTag($monitoringStatus, 'WanIPAddress'),
    'Roaming' => intval(fetchTag($monitoringStatus, 'RoamingStatus')),
    '_' => $_Network_Type,
    '__' => $_Network_TypeEx,
    'Type' => translateNetworkName($_Network_Type, $_Network_TypeEx),
    'Cell' => intval(fetchTag($deviceSignal, 'cell_id')),
  );
  # Signal
  $RSSI = fetchTag($deviceSignal, 'rssi'); $RSSI = substr($RSSI, 0, strpos($RSSI, 'dBm')); // <rssi>-90dBm</rssi>
  $RSCP = fetchTag($deviceSignal, 'rscp'); $RSCP = substr($RSCP, 0, strpos($RSCP, 'dBm')); // rscp>-105dBm</rscp>
  $ECIO = fetchTag($deviceSignal, 'ecio'); $ECIO = substr($ECIO, 0, strpos($ECIO, 'dB')); // <ecio>-14dB</ecio>

  $res['Signal'] = array(
    'RSSI' => intval($RSSI),
    'RSCP' => intval($RSCP),
    'ECIO' => intval($ECIO)
  );

  return $res;
}

while (true) {
  $res = fetch();
  print_r($res);

  $fp = fopen('/dev/ttyS0', 'w');
  fwrite($fp, 'ESPMODEM' . json_encode($res) . "\n");
  fclose($fp);

  sleep(1);
}
