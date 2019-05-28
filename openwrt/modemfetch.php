<?php
$networkTypes = array(
        1 => 'GSM',
        2 => 'GPRS',
        3 => 'EDGE',
        4 => 'UMTS',
        5 => 'HSDPA',
        6 => 'HSUPA',
        7 => 'HSPA',
        9 => 'HSPA+',
        18 => 'LTE',
        41 => 'UMTS',
        42 => 'HSDPA',
        43 => 'HSUPA',
        44 => 'HSPA',
        45 => 'HSPA+',
        46 => 'DC-HSPA+',
        61 => 'TD-SCDMA',
        62 => 'TD-HSDPA',
        63 => 'TD-HSUPA',
        64 => 'TD-HSPA',
        65 => 'TD-HSPA+',
        101 => 'LTE'
);


$res = array();

function fetchTag($xml, $tag) {
  $start = "<$tag>";
  $res = substr($xml, strpos($xml, $start) + strlen($start));
  $res = substr($res, 0, strpos($res, "</$tag>"));
  return $res;
}
function fetch() {
  global $res;

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
    $res['error'] = $curl_error;
    $res['offline'] = 1;
    return $res;
  }
  $cookie = substr($cookieRequestResult, strpos($cookieRequestResult, 'Set-Cookie:') + 11);
  $cookie = substr($cookie, 0, strpos($cookie, "\n"));
  $res['error'] = false;

  $res['offline'] = 0;

  echo "requesting...\n";
  exec('curl "http://192.168.8.1/api/device/signal" -H "Cookie: ' . $cookie . '" -s | cat -', $deviceSignal); $deviceSignal = implode("\n", $deviceSignal);
  exec('curl "http://192.168.8.1/api/monitoring/status" -H "Cookie: ' . $cookie . '" -s | cat -', $monitoringStatus); $monitoringStatus = implode("\n", $monitoringStatus);
  exec('curl "http://192.168.8.1/api/net/current-plmn" -H "Cookie: ' . $cookie . '" -s | cat -', $currentPLMN); $currentPLMN = implode("\n", $currentPLMN);

  $res['ConnectionStatus'] = intval(fetchTag($monitoringStatus, 'ConnectionStatus')); # 900 Connecting | 901 Connected | 902 Disconnected | 903 Disconnecting
  $res['CurrentServiceDomain'] = intval(fetchTag($monitoringStatus, 'CurrentServiceDomain')); # 0 No Service | 2 Available
  $res['ServiceStatus'] = intval(fetchTag($monitoringStatus, 'ServiceStatus')); # 0 No Service | 2 Available
  $res['CurrentNetworkType'] = intval(fetchTag($monitoringStatus, 'CurrentNetworkType'));
  $res['CurrentNetworkTypeEx'] = intval(fetchTag($monitoringStatus, 'CurrentNetworkTypeEx'));
  $res['RoamingStatus'] = intval(fetchTag($monitoringStatus, 'RoamingStatus')) == 1;

  $res['SignalIcon'] = intval(fetchTag($monitoringStatus, 'SignalIcon')); # 0 - 5 Statusbars


  $res['rssi'] = fetchTag($deviceSignal, 'rssi');
  $res['cellid'] = intval(fetchTag($deviceSignal, 'cell_id'));

  $res['fullname'] = fetchTag($currentPLMN, 'FullName');
  $res['service'] = strlen($res['fullname']) > 1;
  $res['rat'] = intval(fetchTag($currentPLMN, 'rat'));
  switch ($res['rat']) {
    case 0: $res['network'] = '2G'; break;
    case 2: $res['network'] = '3G'; break;
    case 5: $res['network'] = 'H'; break;
    case 7: $res['network'] = '4G'; break;
    default: $res['network'] = 'I' . $res['rat']; break;
  }





  #$res['deviceSignal'] = $deviceSignal;
  ###$res['monitoringStatus'] = $monitoringStatus;
  #$res['currentPLMN'] = $currentPLMN;

  return $res;
}

while (true) {
  $res = fetch();
  print_r($res);

  $fp = fopen('/dev/ttyS0', 'w');
  fwrite($fp, 'ESP' . json_encode($res) . "\n");
  fclose($fp);

  sleep(1);
}