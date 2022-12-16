<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class airsend extends eqLogic {
    /*     * *************************Attributs****************************** */

    /*     * ************************Dependancy****************************** */
	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = '/tmp/dependancy_airsend_in_progress';
        $return['state'] = 'nok';
        $signed = self::deamon_is_signed();
        if($signed == 1 || $signed == 2){
            $return['state'] = 'ok';
        }
        $deamon_root = self::getScriptPath();
        chdir($deamon_root);
		return $return;
    }

    public static function dependancy_install() {
        self::deamon_stop();
		log::remove(__CLASS__ . '_update');
        $deamon_root = self::getScriptPath();
		try{
			$request_shell = new com_shell('sudo chmod 777 ' . $deamon_root . ' 2>&1');
			$request_shell->exec();	//Permission fix prevent
		} catch (Exception $e) {}
        chdir($deamon_root);
        return array('script' => $deamon_root . 'dependancy_install.sh #stype# "'. self::getUpdateUrl(). '"', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

    /*     * **************************Deamon******************************** */
    public static function deamon_is_signed() {
        $signed = 0;
        $disable_sign = config::byKey('disable_sign', self::getPluginId(), 0);
        if($disable_sign > 0){
            $signed = 2;
        }else{
            try{
                $deamon_file = self::getDeamon();
                $request_shell = new com_shell('LC_MESSAGES=C GNUPGHOME="'.self::getDataPath().'" gpg --no-secmem-warning --no-tty --no-default-keyring --no-options --no-permission-warning --keyring '.self::getPublicSignKey().' --verify '.$deamon_file.'.sig');
                $result = $request_shell->exec();
                if(strpos($result, "Good signature from \"Devmel Apps <apps@devmel.com>\"") !== false){
                    $signed = 1;
                }
            } catch (Exception $e) {
                $signed = -2;
                if(strpos($e->getMessage(), ": not found") == true){
                    $signed = -1;
                }
            }
        }
        return $signed;
    }

    public static function deamon_info() {
        $port_server = config::byKey('port_server', self::getPluginId(), 33863)&0xffff;
		$return = array();
		$return['state'] = 'nok';
        $return['launchable'] = 'nok';
        $status = @file_get_contents("http://127.0.0.1:" . $port_server . "/service/status");
        if($status !== false){
            $jstatus = json_decode($status, true);
            if(isset($jstatus['version'])){
                $return['state'] = 'ok';
            }
        }
        $deamon_file = self::getDeamon();
		if (file_exists($deamon_file) && (extension_loaded('curl') === true)) {
            $return['launchable'] = 'ok';
        }
		return $return;
	}

	public static function deamon_start($_auto = false) {
        if (!is_dir(self::getDataPath())) {
            mkdir(self::getDataPath());
        }
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok' || $deamon_info['state'] == 'ok') {
            return;
        }
		$randstr = "S".rand()."S";
		$cbstatus = @file_get_contents(self::getCallbackUrl()."&status=".$randstr);
		if($cbstatus !== $randstr){
            throw new Exception( __('Page de callback injoignable : ', __FILE__) . __('veuillez vérifier la configuration réseau, notamment lurl interne', __FILE__));
		}
        $port_server = config::byKey('port_server', self::getPluginId(), 33863);
        $signed = self::deamon_is_signed();
        if($signed == 1 || $signed == 2){
            $launchpath = self::getTmpPath();
            if(chdir($launchpath)){
                $deamon_file = self::getDeamon();
                try{
                    $request_shell = new com_shell($deamon_file . " " . $port_server . ' 2>&1');
                    $result = $request_shell->exec();
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), "command not found") == true || strpos($e->getMessage(), "Permission denied") == true) {
                        $request_shell = new com_shell("sudo chmod 777 " . $deamon_file . ' 2>&1');
                        $request_shell->exec();
                        $request_shell = new com_shell($deamon_file . " " . $port_server);
                        $result = $request_shell->exec();
                    }
                }
                self::devices_start();
                return true;
            }
        }else if($signed == -1){
            throw new Exception( __('Signature invérifiable (gpg manquant) : ', __FILE__) . __('veuillez mettre a jour les dépendances', __FILE__));
        }else{
            throw new Exception( __('Signature erronée : ', __FILE__). __('veuillez mettre a jour les dépendances', __FILE__));
        }
    }

    public static function deamon_stop() {
        $launchpath = self::getTmpPath();
        @chdir($launchpath);
        if (file_exists('AirSendWebService.lock')) {
            $pid = trim(file('AirSendWebService.lock')[0]);
            if(isset($pid) && is_numeric($pid)){
                system::kill(intval($pid));
				sleep(1);
				try{
					$request_shell = new com_shell('sudo kill -9 ' . intval($pid));
					$request_shell->exec();
				} catch (Exception $e) {}
            }
        }else{
            $procs = exec("pidof AirSendWebService");
            if(isset($procs)){
                $pids = explode(" ", $procs);
                if(isset($pids[0]) && is_numeric($pids[0])){
                    system::kill(intval($pids[0]));
                }
            }else{
                system::kill('AirSendWebService');
            }
        }
    }

    /*     * ***********************Methode static*************************** */
    public static function getDeamon(){
        $path = self::getDeamonPath();
        $archs = self::getArch();
        foreach($archs as $arch){
            $deamon_file = $path.$arch.'/AirSendWebService';
            if (file_exists($deamon_file)) {
                return $deamon_file;
            }
        }
        return null;
    }
    public static function getDeamonPath(){
        return self::getPluginPath() . 'ressources/scripts/bin/unix/';
    }
    public static function getScriptPath(){
        return self::getPluginPath() . 'ressources/scripts/';
    }
    public static function getPublicSignKey(){
        return self::getPluginPath() . 'ressources/Devmel_Apps.gpg';
    }
    public static function getChannelsInformationFile(){
        return self::getDataPath() . 'channels.json';
    }
    public static function getDataPath(){
        return self::getPluginPath() . 'data/';
    }
    public static function getTmpPath(){
        return '/tmp/'; //jeedom::getTmpFolder(self::getPluginId())
    }
    public static function getPluginPath(){
		$adir = dirname(__FILE__) . '/../..';
		$rp = realpath($adir);
		if($rp !== false){
			$adir = $rp;
		}
        return $adir.'/';
    }
    public static function getArch(){
        $ret = array();
        $arch = php_uname('m');  //aarch64, armv7l, armv6l, x86_64, i386
        if(stripos($arch, 'aarch64') !== false){
            $ret[] = "arm64";
            $ret[] = "armhf";
            $ret[] = "arm";
        }else if(stripos($arch, 'arm') !== false){
            $ret[] = "armhf";
            $ret[] = "arm";
        }else if(stripos($arch, 'x86_64') !== false){
            $ret[] = "x86_64";
            $ret[] = "x86";
        }else if(stripos($arch, '86') !== false){
            $ret[] = "x86";
        }else{
            $ret[] = $arch;
        }
        return $ret;
    }
    public static function getUpdateUrl() {
        $url_update = config::byKey('url_update', self::getPluginId(), null);
        if (empty($url_update) || filter_var($url_update, FILTER_VALIDATE_URL) === false) {
            $url_update = 'http://devmel.com/dl/AirSendWebService.tgz';
        }
        return $url_update;
    }
    public static function getCallbackUrl(){
        $base = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp');
        if(empty($base) || stripos($base, 'https://') === 0){
            $base = "http://127.0.0.1";
        }
		return $base . '/plugins/'. self::getPluginId() .'/core/php/jeeAirSend.php?apikey='.jeedom::getApiKey(self::getPluginId());
    }
    public static function getPluginId(){
		return 'airsend';
    }

    public static function getChannelsInformation(){
        $file = self::getChannelsInformationFile();
        $content = file_get_contents($file);
        if($content !== false){
            return json_decode($content);
        }
        return array();
    }

    public static function request($url, $data = null, $method = 'GET', $token = null) { 
        $port_server = config::byKey('port_server', self::getPluginId(), 33863);
        $full_url = 'http://127.0.0.1:' . $port_server . '/' . $url;
        $result = false;
        if (extension_loaded('curl') === true){
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $full_url);
            curl_setopt($curl, CURLOPT_AUTOREFERER, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
            if (preg_match('~^(?:DELETE|GET|POST|PUT)$~i', $method) > 0){
                if (preg_match('~^(?:POST|PUT)$~i', $method) > 0){
                    if (is_array($data) === true){
                        foreach (preg_grep('~^@~', $data) as $key => $value){
                            $data[$key] = sprintf('@%s', rtrim(str_replace('\\', '/', realpath(ltrim($value, '@'))), '/') . (is_dir(ltrim($value, '@')) ? '/' : ''));
                        }
                        if (count($data) != count($data, COUNT_RECURSIVE)){
                            $data = http_build_query($data, '', '&');
                        }
                    }
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                if (isset($token)){
                    $token = str_replace('"', "", $token);
                    $options = array(CURLOPT_HTTPHEADER => array('Content-Type: application/json' , "Authorization: Bearer ".$token));
                    curl_setopt_array($curl, $options);
                }
                $result = array();
                $result['data'] = curl_exec($curl);
                $result['info'] = curl_getinfo($curl);
                if (curl_errno($curl)) {
                    $result['error'] = curl_error($curl);
                }
            }
            curl_close($curl);
        }
        return $result;
    } 

	public static function devices_start(){
		$eqLogics = eqLogic::byType(self::getPluginId());
		foreach ($eqLogics as $eqLogic)
		{
            if ($eqLogic->getIsEnable() == 0) continue;
            try{
                $deviceType = intval($eqLogic->getConfiguration('device_type'));
                if($deviceType == 0){
                    $eqLogic->updateChannelInformation();
                    $asAddr = $eqLogic->getAddress();
                    $channelid = $eqLogic->getConfiguration('listenmode', 0);
                    if($channelid > 0){
                        $eqLogic->bind($channelid);
                    }
                }
            } catch (Exception $exc) {}
		}
    }

    public static function hex2ip($bin){ 
        if(strlen($bin) <= 8)  
            return long2ip(base_convert($bin,16,10)); 
        if(strlen($bin) != 32) 
            return false; 
        $pad = 32 - strlen($bin); 
        for ($i = 1; $i <= $pad; $i++) 
        { 
            $bin = "0".$bin; 
        } 
        $bits = 0; 
        $ipv6 = '';
        while ($bits <= 7) 
        { 
            $bin_part = substr($bin,($bits*4),4); 
            $ipv6 .= $bin_part.":"; 
            $bits++; 
        } 
        return inet_ntop(inet_pton(substr($ipv6,0,-1))); 
    }

	public static function refreshInfo(){
		$eqLogics = eqLogic::byType(self::getPluginId());
		foreach ($eqLogics as $eqLogic)
		{
            if ($eqLogic->getIsEnable() == 0) continue;
            try{
                $deviceType = intval($eqLogic->getConfiguration('device_type'));
                if($deviceType == 0){
                    airsendCmd::readSensors($eqLogic);
                }
            } catch (Exception $exc) {}
		}
    }

    public static function getDeviceName($name){
        $plugin = plugin::byId(self::getPluginId());
        $eqLogics = eqLogic::byType($plugin->getId());
        foreach ($eqLogics as $eqLogic) {
            $n = $eqLogic->getName();
            if($name == $n){
                return $eqLogic;
            }
        }
        return null;
    }

    public static function getBaseDevice($localip){
        $plugin = plugin::byId(self::getPluginId());
        $eqLogics = eqLogic::byType($plugin->getId());
        foreach ($eqLogics as $eqLogic) {
            $deviceType = intval($eqLogic->getConfiguration('device_type'));
            if($deviceType == 0){
                $lip = $eqLogic->getConfiguration('localip');
                if (filter_var($lip, FILTER_VALIDATE_IP)) {
                    if($lip == $localip || $lip === self::hex2ip($localip)){
                        return $eqLogic;
                    }
                }
            }
        }
        return null;
    }

    public static function importDevices($devices){
        $result = array();
        foreach ($devices as $device){
            $status = array();
            $status['name'] = $device['name'];
            $status['status'] = "error";
            $nameEq = self::getDeviceName($device['name']);
            if(!$nameEq){
                $baseEq = self::getBaseDevice($device['localip']);
                if ($baseEq) {
                    $deviceType = intval($device['type']);
                    if($deviceType >= 4096 && $deviceType <= 4099){
                        $protocol = intval($device['pid']);
                        if ($protocol > 0) {
                            try{
                                $eqLogic = new airsend();
                                $eqLogic->setEqType_name(self::getPluginId());
                                $eqLogic->setName($device['name']);
                                $eqLogic->setConfiguration('device_type', $device['type']);
                                $eqLogic->setConfiguration('localip', $device['localip']);
                                $eqLogic->setConfiguration('protocol', $device['pid']);
                                $eqLogic->setConfiguration('address', $device['addr']);
                                if(isset($device['opt'])){
                                    $eqLogic->setConfiguration('opt', $device['opt']);
                                }
                                if(isset($device['mac'])){
                                    $eqLogic->setConfiguration('mac', $device['mac']);
                                }
                                if(isset($device['seed'])){
                                    $eqLogic->setConfiguration('seed', $device['seed']);
                                }
                                if(isset($device['presstype'])){
                                    $eqLogic->setConfiguration('presstype', $device['presstype']);
                                }
                                $eqLogic->save();
                                if (method_exists($eqLogic, 'postAjax')) {
                                    $eqLogic->postAjax();
                                }
                                $status['status'] = "ok";
                            } catch (Exception $e) {
                            }
                        }
                    }
                }else{
                    $status['status'] = __('Ce localip n\'existe pas', __FILE__);
                }
            }else{
                $status['status'] = __('Ce nom existe déjà', __FILE__);
            }
            $result[] = $status;
        }
        return $result;
    }

    public static function importInterfaces($interfaces){
        $result = array();
        foreach ($interfaces as $iface){
            $status = array();
            $status['name'] = $iface['name'];
            $status['status'] = "error";
            $baseEq = self::getBaseDevice($iface['localip']);
            if (!$baseEq) {
                try{
                    $eqLogic = new airsend();
                    $eqLogic->setEqType_name(self::getPluginId());
                    $eqLogic->setName($iface['name']);
                    $eqLogic->setConfiguration('device_type', '0');
                    $eqLogic->setConfiguration('localip', $iface['localip']);
                    $eqLogic->setConfiguration('password', $iface['password']);
                    $eqLogic->setConfiguration('gateway', '1');
                    $eqLogic->save();
                    if (method_exists($eqLogic, 'postAjax')) {
                        $eqLogic->postAjax();
                    }
                    $status['status'] = "ok";
                } catch (Exception $e) {
                }
            }else{
                $status['status'] = __('Cette interface existe déjà', __FILE__);
            }
            $result[] = $status;
        }
        return $result;
    }

    public static function importFile($data){
        $result = array();
        if(isset($data['interfaces'])){
            $result['interfaces'] = self::importInterfaces($data['interfaces']);
        }
        if(isset($data['devices'])){
            $result['devices'] = self::importDevices($data['devices']);
        }
        return $result;
    }

    public static function createFromEvent($event, $device_base) {
        if(isset($event)){
            $type = 0;
            $channel = $event['channel'];
            $eqLogic = new airsend();
            $name = self::toUniqueChannelName($channel);
            if($name == null)
                return null;
            $eqLogic->setName($name);
            $eqLogic->setLogicalId($name);
            $eqLogic->setEqType_name(self::getPluginId());
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
            if(array_key_exists("counter", $channel)){
                $eqLogic->setConfiguration('nocopy', 1);
            }
            $eqLogic->setConfiguration('localip', $device_base->getConfiguration('localip'));
            $eqLogic->setConfiguration('protocol', $channel['id']);
            $eqLogic->setConfiguration('address', $channel['source']);
            if(isset($channel['mac'])){
                $eqLogic->setConfiguration('mac', $channel['mac']);
            }
            if(isset($channel['seed'])){
                $eqLogic->setConfiguration('seed', $channel['seed']);
            }
            $thingnotes = $event['thingnotes'];
            $notes = $thingnotes['notes'];
            $cmdLogicalId = array();
            if(is_array($notes) && count($notes) > 0){
                foreach ($notes as $key => $note) {
                    $value = $note['value'];
                    if($note['type'] == 0){		    //STATE
                        if($value == 18){
                            $type = 4096;
                        }else if($value == 19 || $type == 20){
                            $type = 4097;
                        }else{
                            $type = 4098;
                        }
                    }else if($note['type'] == 1){   //DATA
                        $type = 4096;
                        $eqLogic->setConfiguration('opt', $value);
                    }else if($note['type'] == 2){   //TEMPERATURE
                        $type = 1;
                        $cmdLogicalId[] = "temperature";
                    }else if($note['type'] == 3){   //ILLUMINANCE
                        $type = 1;
                        $cmdLogicalId[] = "illuminance";
                    }else if($note['type'] == 4){   //R_HUMIDITY
                        $type = 1;
                        $cmdLogicalId[] = "r_humidity";
                    }
                }
            }
            if($type > 0){
                $eqLogic->setConfiguration('device_type', $type);
                $eqLogic->save();
                if (method_exists($eqLogic, 'postAjax')) {
                    $eqLogic->postAjax();
                }
                if(count($cmdLogicalId) > 0){
                    foreach ($cmdLogicalId as $key => $value) {
                        $cmd = airsendCmd::createFromLogicalId($value, $eqLogic->getId());
                        if($cmd){
                            $cmd->save();
                        }
                    }
                }
                $msg = __('Nouvel appareil sans fil ajouté', __FILE__);
                event::add('jeedom::alert', array('level' => 'warning', 'message' => $msg));
                return $eqLogic;
            }
        }
        return null;
    }


    public static function toUIntValue($s){
        $value = preg_replace('/[^0-9]/', '', $s);
        if((int)$value == $value){
            return (int)$value;
        }
        return $value;
	}

    public static function toBasicChannel($channel){
        $result = array();
        $uniquefield = array('id', 'source');
        foreach ($channel as $key => $value) {
            if (in_array($key, $uniquefield)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }
    public static function toUniqueChannel($channel){
        $result = array();
        $uniquefield = array('id', 'source', 'mac', 'seed');
        foreach ($channel as $key => $value) {
            if (in_array($key, $uniquefield)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public static function getChannelName($id){
        $channels = self::getChannelsInformation();
        foreach ($channels as $chan) {
            if($id == $chan->id){
                return $chan->name;
            }
        }
        return null;
    }

    public static function toUniqueChannelName($channel){
        $result = self::getChannelName($channel['id']);
        if($result){
            $uniquefield = array('source', 'mac', 'seed');
            foreach ($uniquefield as $field) {
                if(array_key_exists($field, $channel)){
                    $result .= "_";
                    $result .= $channel[$field];
                }
            }
        }
        return $result;
    }


    /*
     * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
     */
	public static function cron15()
	{
        self::refreshInfo();
	}

    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */



    /*     * *********************Méthodes d'instance************************* */

    public function getAddress(){
        $localip = $this->getConfiguration('localip');
        if (filter_var($localip, FILTER_VALIDATE_IP)) {
            $addr = $localip;
            if (filter_var($localip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $addr = '['.$localip.']';
            }
            $deviceType = intval($this->getConfiguration('device_type'));
            if($deviceType == 0){
                $password = $this->getConfiguration('password');
                $gateway = $this->getConfiguration('gateway');
                $failover = $this->getConfiguration('failover');
            }else{
                //Search password
                $eqLogics = eqLogic::byType(self::getPluginId());
                foreach ($eqLogics as $eqLogic){
                    $lip = $eqLogic->getConfiguration('localip');
                    if($lip == $localip){
                        $lpw = $eqLogic->getConfiguration('password');
                        if(strlen($lpw)>0){
                            $password = $lpw;
                            $gateway = $eqLogic->getConfiguration('gateway');
                            $failover = $eqLogic->getConfiguration('failover');
                        }
                    }
                    if($password && $eqLogic->getIsEnable() <> 0)
                        break;
                }
            }
            if($password){
                $str = "\"sp://".$password."@".$addr."/?timeout=6000";
                $str .= "&gw=".$gateway;
                if(strlen($failover)>0){
                    $str .= "&rhost=".$failover;
                }
                $str .= "\"";
                return $str;
            }
        }
        return false;
    }

    public function bind($channelid){
        $deviceType = intval($this->getConfiguration('device_type'));
        if($deviceType == 0){
            $asAddr = $this->getAddress();
            if(isset($asAddr)){
                $channelid = intval($channelid);
                $data = "{\"channel\":{\"id\":".$channelid."},\"duration\":0,\"callback\":\"". self::getCallbackUrl() ."\"}";
                self::request("airsend/bind", $data, 'POST', $asAddr);
            }
        }
    }
    public function close(){
        $this->updateChannelInformation();
        $deviceType = intval($this->getConfiguration('device_type'));
        if($deviceType == 0){
            $asAddr = $this->getAddress();
            if(isset($asAddr)){
                self::request("airsend/close", null, 'GET', $asAddr);
            }
        }
    }

    public function updateChannelInformation(){
        $asAddr = $this->getAddress();
        if($asAddr !== false){
            $updated = false;
            $res = airsend::request("airsend/channels", null, 'GET', $asAddr);
            if($res !== false && isset($res) && is_array($res) && isset($res['data'])){
                $channelsInformation = json_decode($res['data']);
                if(is_array($channelsInformation)){
                    $updated = true;
                    $cur_channels = self::getChannelsInformation();
                    if(count($channelsInformation) > count($cur_channels)){
                        $file = self::getChannelsInformationFile();
                        if(file_put_contents($file, json_encode($channelsInformation)) == false){
                            $msg = __('Erreur decriture du fichier de gestion des canaux', __FILE__);
                            log::add(self::getPluginId(), 'error', $msg);
                            event::add('jeedom::alert', array('level' => 'error', 'message' => $msg));
                        }
                    }
                }
            }
            if($updated == false){
                $msg = $this->getConfiguration('localip')." : ".__('Echec de la mise à jour des canaux', __FILE__);
                log::add(self::getPluginId(), 'warning', $msg);
                event::add('jeedom::alert', array('level' => 'warning', 'message' => $msg));
            }
        }
    }

    public function getChannel(){
        $result = array();
        $result['id'] = self::toUIntValue($this->getConfiguration('protocol'));
        if($result['id'] > 0){
            $source = $this->getConfiguration('address', null);
            $mac = $this->getConfiguration('mac', null);
            $seed = $this->getConfiguration('seed', null);
            $presstype = $this->getConfiguration('presstype', 0);
            if($source){
                $result['source'] = self::toUIntValue($source);
            }
            if($mac){
                $result['counter'] = $mac;
                $result['mac'] = $mac;
            }
            if($seed){
                $result['seed'] = $seed;
            }
            if($presstype > 0){
                $result['duration'] = 3000;
            }
        }
        return $result;
    }

    public function isChannelCompatible($channel){
        $result = false;
        if(isset($channel)){
            $channel = self::toBasicChannel($channel);
            $lchan = self::toBasicChannel($this->getChannel());
            $result = ($channel == $lchan);
        }
        return $result;
    }
    public function isThingNotesCompatible($notes){
        $result = false;
        if(is_array($notes) && count($notes) > 0){
            $deviceType = intval($this->getConfiguration('device_type'));
            if($deviceType == 4096){
                $opt = $this->getConfiguration('opt', null);
                foreach ($notes as $i => $note) {
                    if($note['type'] == 1){	//DATA
                        if($note['value'] == $opt){        //Check DATA
                            $result = true;
                        }
                    }
                }
            }else{
                $result = true;
            }
        }
        return $result;
    }

    public function updateErrorWithEvent($event_type, $collectdate, $cmd, $disp = true){
        //Message UNKNOWN,NETWORK,SYNCHRONIZATION,SECURITY,BUSY,TIMEOUT,UNSUPPORTED,INCOMPLETE,FULL
        $error_message = array();
        $error_message[] = __('Une erreur inconnue est survenue', __FILE__);
        $error_message[] = __('Le réseau n\'est pas accessible', __FILE__);
        $error_message[] = __('Une erreur de synchronisation s\'est produite', __FILE__);
        $error_message[] = __('Echec de l\'authentification', __FILE__);
        $error_message[] = __('Un autre utilisateur a verrouillé l\'appareil', __FILE__);
        $error_message[] = __('Echec de la connexion dans un délai raisonnable', __FILE__);
        $error_message[] = __('Requête non supportée', __FILE__);
        $error_message[] = __('Requête incomplète', __FILE__);
        $error_message[] = __('Requête trop grande', __FILE__);
        $event_type = intval($event_type) - 0x100;
        if($event_type < 0)
            $event_type = 0;
        $msg = $error_message[0];
        if($event_type < count($error_message))
            $msg = $error_message[$event_type];
		
        //Update state collectdate
        $statecmd = airsendCmd::byEqLogicIdAndLogicalId($this->getId(), 'state');
        if(isset($statecmd) && is_object($statecmd)){
			$svalue = 0;
            $this->checkAndUpdateCmd('state', $svalue, $collectdate);
        }
		if($disp){
			log::add(self::getPluginId(), 'error', __('Erreur exécution de la commande ', __FILE__) . $cmd->getHumanName() . ' : ' . $msg);
			event::add('jeedom::alert', array('level' => 'danger', 'message' => $msg));
		}
    }

    public function updateStateWithNotes($notes, $collectdate){
        $done = false;
        if(is_array($notes) && count($notes) > 0){
            foreach ($notes as $i => $note) {
                $toggle = false;
                $svalue = null;
                $slogicalid = null;
                $ovalue = $note['value'];
                if($note['type'] == 0){			//STATE
                    if(!is_numeric($ovalue)){
                        $statemap = array("","PING","PROG","UNPROG","RESET",
                        "","","","","","","","","","","","","STOP","TOGGLE","OFF","ON","CLOSE","OPEN",
                        "","","","","","","","","","","MIDDLE","DOWN","UP","LEFT","RIGHT","USERPOSITION");
                        $ovalue = array_search($ovalue, $statemap);
                    }
                    if(is_numeric($ovalue)){
                        $ovalue = intval($ovalue);
                        switch($ovalue){
                            case 18:		//TOGGLE
                                $toggle = true;
                            break;
                            case 19:		//OFF
                                $svalue = 0;
                            break;
                            case 20:		//ON
                                $svalue = 100;
                            break;
                            case 17:		//STOP
                            case 33:		//MIDDLE
                            case 38:		//USERPOS
                                $svalue = 50;
                            break;
                            case 34:		//DOWN
                                $svalue = 0;
                            break;
                            case 35:		//UP
                                $svalue = 100;
                            break;
                        }
                        $slogicalid = 'state';
                    }
                }else if($note['type'] == 1){	//DATA
                    $opt = $this->getConfiguration('opt', null);
                    if($ovalue == $opt){        //Check DATA
                        $slogicalid = 'state';
                        $toggle = true;
                    }
                }else if($note['type'] == 2){	//TEMPERATURE
                    $slogicalid = 'temperature';
                    $svalue = (floor((floatval($ovalue) - 273.15) * 10.0 + 0.5) / 10.0);	//Kelvins to celcius
                }else if($note['type'] == 3){	//ILLUMINANCE
                    $slogicalid = 'illuminance';
                    $svalue = floatval($ovalue);
                }else if($note['type'] == 4){	//R_HUMIDITY
                    $slogicalid = 'r_humidity';
                    $svalue = intval($ovalue);
                }else if($note['type'] == 9){	//LEVEL
                    $slogicalid = 'state';
                    $svalue = intval($ovalue);
                }
                if(isset($slogicalid) && $toggle == true){
                    $statecmd = airsendCmd::byEqLogicIdAndLogicalId($this->getId(), $slogicalid);
                    if(isset($statecmd) && is_object($statecmd)){
                        $ov = (intval($statecmd->execCmd()) > 0);
                        $svalue = (($ov + 1) % 2) * 100;
                    }
                }
                if(isset($slogicalid) && isset($svalue)){
                    $this->checkAndUpdateCmd($slogicalid, $svalue, $collectdate);
                    $done = true;
                }
            }
        }
        return $done;
    }

    public function autoGenerateCommands(){
        if($this->getId()){
            $deviceType = intval($this->getConfiguration('device_type'));
            $copyable = intval($this->getConfiguration('nocopy', 0))^1;
            $cmd_list = $this->getCmd();
            if(!is_array($cmd_list)){
                $cmd_list = array();
            }
            if($deviceType == 4099){
                foreach ($cmd_list as $cmd){
                    if ($cmd->getLogicalId() == 'slide' && !$cmds["slide"]) {
                        $cmds["slide"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'state' && !$cmds["state"]) {
                        $cmds["state"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if(!$cmds["state"]){
                    $cmds["state"] = airsendCmd::createFromLogicalId("state", $this->getId());
                    if($cmds["state"]){
                        $cmds["state"]->setTemplate('dashboard', 'shutter');
                        $cmds["state"]->setTemplate('mobile', 'shutter');
                        $cmds["state"]->setDisplay('generic_type', 'FLAP_STATE');
                        $cmds["state"]->setOrder(1);
                        $cmds["state"]->save();
                    }
                }
                if($copyable && !$cmds["slide"]){
                    $cmds["slide"] = airsendCmd::createFromLogicalId("slider", $this->getId());
                    if($cmds["slide"]){
                        $cmds["slide"]->setValue(null);
                        $cmds["slide"]->setOrder(2);
                        $cmds["slide"]->save();
                    }
                }

            }else if($deviceType == 4098){
                foreach ($cmd_list as $cmd){
                    if ($cmd->getLogicalId() == 'stop' && !$cmds["stop"]) {
                        $cmds["stop"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'down' && !$cmds["down"]) {
                        $cmds["down"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'up' && !$cmds["up"]) {
                        $cmds["up"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'state' && !$cmds["state"]) {
                        $cmds["state"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if(!$cmds["state"]){
                    $cmds["state"] = airsendCmd::createFromLogicalId("state", $this->getId());
                    if($cmds["state"]){
                        $cmds["state"]->setTemplate('dashboard', 'shutter');
                        $cmds["state"]->setTemplate('mobile', 'shutter');
                        $cmds["state"]->setDisplay('generic_type', 'FLAP_STATE');
                        $cmds["state"]->setOrder(1);
                        $cmds["state"]->save();
                    }
                }
                if($copyable && !$cmds["stop"]){
                    $cmds["stop"] = airsendCmd::createFromLogicalId("stop", $this->getId());
                    if($cmds["stop"]){
                        $cmds["stop"]->setValue('3');
                        $cmds["stop"]->setOrder(3);
                        $cmds["stop"]->save();
                    }
                }
                if($copyable && !$cmds["down"]){
                    $cmds["down"] = airsendCmd::createFromLogicalId("down", $this->getId());
                    if($cmds["down"]){
                        $cmds["down"]->setValue('4');
                        $cmds["down"]->setOrder(2);
                        $cmds["down"]->save();
                    }
                }
                if($copyable && !$cmds["up"]){
                    $cmds["up"] = airsendCmd::createFromLogicalId("up", $this->getId());
                    if($cmds["up"]){
                        $cmds["up"]->setValue('5');
                        $cmds["up"]->setOrder(4);
                        $cmds["up"]->save();
                    }
                }

            }else if($deviceType == 4097){
                foreach ($cmd_list as $cmd){
                    if ($cmd->getLogicalId() == 'off' && !$cmds["off"]) {
                        $cmds["off"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'on' && !$cmds["on"]) {
                        $cmds["on"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'state' && !$cmds["state"]) {
                        $cmds["state"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if(!$cmds["state"]){
                    $cmds["state"] = airsendCmd::createFromLogicalId("state", $this->getId());
                    if($cmds["state"]){
                        $cmds["state"]->setTemplate('dashboard', 'light');
                        $cmds["state"]->setTemplate('mobile', 'light');
                        $cmds["state"]->setDisplay('generic_type', 'LIGHT_STATE');
                        $cmds["state"]->setOrder(1);
                        $cmds["state"]->save();
                    }
                }
                if($copyable && !$cmds["off"]){
                    $cmds["off"] = airsendCmd::createFromLogicalId("off", $this->getId());
                    if($cmds["off"]){
                        $cmds["off"]->setValue('0');
                        $cmds["off"]->setOrder(2);
                        $cmds["off"]->save();
                    }
                }
                if($copyable && !$cmds["on"]){
                    $cmds["on"] = airsendCmd::createFromLogicalId("on", $this->getId());
                    if($cmds["on"]){
                        $cmds["on"]->setValue('1');
                        $cmds["on"]->setOrder(3);
                        $cmds["on"]->save();
                    }
                }

            }else if($deviceType == 4096){
                foreach ($cmd_list as $cmd){
                    if ($cmd->getLogicalId() == 'toggle' && !$cmds["toggle"]) {
                        $cmds["toggle"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'state' && !$cmds["state"]) {
                        $cmds["state"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if(!$cmds["state"]){
                    $cmds["state"] = airsendCmd::createFromLogicalId("state", $this->getId());
                    if($cmds["state"]){
                        $cmds["state"]->setSubType('binary');
                        $cmds["state"]->setDisplay('generic_type', 'BARRIER_STATE');
                        $cmds["state"]->setOrder(1);
                        $cmds["state"]->save();
                    }
                }
                if($copyable && !$cmds["toggle"]){
                    $cmds["toggle"] = airsendCmd::createFromLogicalId("toggle", $this->getId());
                    if($cmds["toggle"]){
                        $cmds["toggle"]->setValue('6');
                        $cmds["toggle"]->setOrder(2);
                        $cmds["toggle"]->save();
                    }
                }
            }else if($deviceType == 0){
                $airsend_version = $this->getConfiguration('airsend_version', 0);
                $has_sensors = ($airsend_version == 0) ? true : false;
                foreach ($cmd_list as $cmd){
                    if ($has_sensors && $cmd->getLogicalId() == 'temperature' && !$cmds["temperature"]) {
                        $cmds["temperature"] = $cmd;
                    }else if ($has_sensors && $cmd->getLogicalId() == 'illuminance' && !$cmds["illuminance"]) {
                        $cmds["illuminance"] = $cmd;
                    }else if (!$has_sensors && $cmd->getLogicalId() == 'state' && !$cmds["state"]) {
                        $cmds["state"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'refresh' && !$cmds["refresh"]) {
                        $cmds["refresh"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if($has_sensors && !$cmds["illuminance"]){
                    $cmds["illuminance"] = airsendCmd::createFromLogicalId("illuminance", $this->getId());
                    if($cmds["illuminance"]){
                        $cmds["illuminance"]->setOrder(2);
                        $cmds["illuminance"]->save();
                    }
                }
                if($has_sensors && !$cmds["temperature"]){
                    $cmds["temperature"] = airsendCmd::createFromLogicalId("temperature", $this->getId());
                    if($cmds["temperature"]){
                        $cmds["temperature"]->setOrder(1);
                        $cmds["temperature"]->save();
                    }
                }
                if(!$has_sensors && !$cmds["state"]){
                    $cmds["state"] = airsendCmd::createFromLogicalId("state", $this->getId());
                    if($cmds["state"]){
                        $cmds["state"]->setSubType('binary');
                        $cmds["state"]->setDisplay('generic_type', 'BARRIER_STATE');
                        $cmds["state"]->setOrder(1);
                        $cmds["state"]->save();
                    }
                }
                if(!$cmds["refresh"]){
                    $cmds["refresh"] = airsendCmd::createFromLogicalId("refresh", $this->getId());
                    if($cmds["refresh"]){
                        $cmds["refresh"]->setOrder(0);
                        $cmds["refresh"]->save();
                    }
                }
            }
        }
    }


    public function preInsert() {
    }

    public function postInsert() {
    }

    public function preAjax() {
        $this->close();
    }

    public function postAjax() {
        $channelid = $this->getConfiguration('listenmode', 0);
        if($channelid > 0){
            $this->bind($channelid);
        }
        $this->autoGenerateCommands();
    }

    public function preSave() {
        //Prevent error on creation
        $localip = $this->getConfiguration('localip');
        if($this->getId() || $localip<>""){
            if (!filter_var($localip, FILTER_VALIDATE_IP)) {
                throw new Exception( __('Erreur de configuration : ', __FILE__) . __('localip invalide', __FILE__));
            }
            $deviceType = intval($this->getConfiguration('device_type'));
            if($deviceType >= 4096 && $deviceType <= 4099){
                $protocol = intval($this->getConfiguration('protocol'));
                if ($protocol <= 0) {
                    throw new Exception( __('Erreur de configuration : ', __FILE__) . __('protocole invalide', __FILE__));
                }
            }
        }else{
            $this->setConfiguration('device_type', '0');
        }
    }

    public function postSave() {
    }

    public function preUpdate() {
    }

    public function postUpdate() {
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class airsendCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */
    public static function createFromLogicalId($logicalId, $eqLogicId){
        $infofield = array('state', 'temperature', 'illuminance', 'r_humidity');
        $actionfield = array('refresh', 'toggle', 'off', 'on', 'stop', 'down', 'up', 'slider');
        if(isset($eqLogicId) && isset($logicalId) && (in_array($logicalId, $infofield) || in_array($logicalId, $actionfield))){
            $cmd = new airsendCmd();
            $cmd->setEqLogic_id($eqLogicId);
            $cmd->setEqType(airsend::getPluginId());
            $cmd->setLogicalId($logicalId);
            $cmd->setTemplate('dashboard', 'default');
            $cmd->setTemplate('mobile', 'default');
            if(in_array($logicalId, $actionfield)){
                $cmd->setType('action');
                $cmd->setSubType('other');
                $cmd->setValue($logicalId);
                if($logicalId == "refresh"){
                    $cmd->setName(__('Rafraichir', __FILE__));
                    $cmd->setDisplay('generic_type', 'GENERIC_ACTION');
                }else if($logicalId == "toggle"){
                    $cmd->setName(__('Basculer', __FILE__));
                    $cmd->setDisplay('icon', '<i class="fas fa-rss"></i>');
                    $cmd->setDisplay('generic_type', 'GB_TOGGLE');
                }else if($logicalId == "off"){
                    $cmd->setName(__('Éteindre', __FILE__));
					$cmd->setDisplay('icon', '<i class="icon jeedomapp-ampoule-off"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_OFF');
                }else if($logicalId == "on"){
                    $cmd->setName(__('Allumer', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon jeedomapp-ampoule-on"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_ON');
                }else if($logicalId == "stop"){
                    $cmd->setName(__('Arrêter', __FILE__));
					$cmd->setDisplay('icon', '<i class="fas fa-stop"></i>');
                    $cmd->setDisplay('generic_type', 'FLAP_STOP');
                }else if($logicalId == "down"){
                    $cmd->setName(__('Descendre', __FILE__));
					$cmd->setDisplay('icon', '<i class="fas fa-arrow-down"></i>');
                    $cmd->setDisplay('generic_type', 'FLAP_DOWN');
                }else if($logicalId == "up"){
                    $cmd->setName(__('Monter', __FILE__));
                    $cmd->setDisplay('icon', '<i class="fas fa-arrow-up"></i>');
                    $cmd->setDisplay('generic_type', 'FLAP_UP');
                }else if($logicalId == "slider"){
                    $cmd->setName(__('Niveau', __FILE__));
                    $cmd->setConfiguration('minValue' , '0');
                    $cmd->setConfiguration('maxValue' , '100');
                    $cmd->setSubType('slider');
                    $cmd->setTemplate('dashboard', 'sliderVertical');
                    $cmd->setTemplate('mobile', 'sliderVertical');
                    $cmd->setDisplay('generic_type', 'FLAP_SLIDER');
                }
            }else{
                $cmd->setType('info');
                $cmd->setSubType('numeric');
                $cmd->setDisplay('showNameOndashboard', '0');
                $cmd->setDisplay('showNameOnmobile', '0');
                if($logicalId == "state"){
                    $cmd->setName(__('État', __FILE__));
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                }else if($logicalId == "temperature"){
					$cmd->setTemplate('dashboard', 'tile');
                    $cmd->setName(__('Température', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon jeedom-thermometre-celcius"></i>');
                    $cmd->setDisplay('generic_type', 'TEMPERATURE');
                    $cmd->setUnite('°C');
                }else if($logicalId == "illuminance"){
					$cmd->setTemplate('dashboard', 'tile');
                    $cmd->setName(__('Éclairement', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon nature-weather1"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_BRIGHTNESS');
                    $cmd->setUnite('lux');
                }else if($logicalId == "r_humidity"){
					$cmd->setTemplate('dashboard', 'tile');
                    $cmd->setName(__('Humidité relative', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon jeedomapp-humidity"></i>');
                    $cmd->setDisplay('generic_type', 'HUMIDITY');
                    $cmd->setUnite('%');
                }
            }
            return $cmd;
        }
        return null;
    }
    public static function readSensors($eqLogic, $source = null){
        $asAddr = $eqLogic->getAddress();
        $uid = $eqLogic->getId();
        $channel = array();
        $channel['id'] = 1;
        if($source)
            $channel['source'] = $source;
        if($asAddr && $uid){
            $cmds = cmd::byEqLogicId($uid);
            foreach ($cmds as $c) {
                if(is_object($c)){
                    $type = strtoupper($c->getLogicalId());
                    if($type == 'TEMPERATURE' || $type == 'ILLUMINANCE' || $type == 'STATE'){
                        $thingnotes = self::thingnotesQuery($type);
                        $thingnotes['uid'] = airsend::toUIntValue($c->getId());
                        self::transfer($asAddr, $channel, $thingnotes, $eqLogic);
                    }
                }
            }
        }
    }

    public static function transfer($device, $channel, $thingnotes, $eqLogic = null){
        $data = array();
        $data['callback'] = airsend::getCallbackUrl();
        $data['channel'] = $channel;
        $data['thingnotes'] = $thingnotes;
        $res = airsend::request("airsend/transfer", json_encode($data, true), 'POST', $device);
        if($res !== false && isset($res) && is_array($res)){
            if(isset($res['error'])){
                throw new Exception( __('Erreur retourné par la commande curl : ', __FILE__). $res['error']);
            }
            if(isset($res['info']) && isset($res['info']['http_code'])){
                $val = intval($res['info']['http_code']);
                if($val != 200){
                    throw new Exception( __('Erreur de rêquete HTTP : code ', __FILE__). $val);
                }
            }
        }else{
            throw new Exception( __('La commande curl n\'a pas aboutie', __FILE__));
        }
    }
    
    public static function thingnotesQuery($type){
        $thingnotes = array();
        $thingnotes['notes'] = array();
        $thingnotes['notes'][0] = array();
        $thingnotes['notes'][0]['method'] = "QUERY";
        $thingnotes['notes'][0]['type'] = $type;
        return $thingnotes;
    }


    /*     * *********************Methode d'instance************************* */
    public function getThingNotes($_options){
        $statemap = array("OFF", "ON", "PROG", "STOP", "DOWN", "UP", "TOGGLE");

        $thingnotes = array();
        $thingnotes['uid'] = airsend::toUIntValue($this->getId());
        $thingnotes['notes'] = array();

        $note = array();
        $note['method'] = "PUT";

        $logicalId = strtoupper($this->getLogicalId());
        $eqLogic = $this->getEqLogic();
        $opt = (isset($eqLogic)) ? airsend::toUIntValue($eqLogic->getConfiguration('opt', null)) : null;
        if(!empty($opt) && $logicalId === 'TOGGLE'){
            $logicalId = 'DATA';   
        }

        if(in_array($logicalId, $statemap)){
            $note['type'] = "STATE";
            $note['value'] = $logicalId;
        }else if($logicalId === 'SLIDER'){
            $note['type'] = "LEVEL";
            $note['value'] = airsend::toUIntValue($_options['slider']);
        }else{
            $note['type'] = "DATA";
            $note['value'] = $opt;
        }
        $thingnotes['notes'][0] = $note;
        return $thingnotes;
    }

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
     */
    public function dontRemoveCmd() {
        return false;
    }
            
    public function execute($_options = array()) {
        if ($this->getType() == 'action'){
            $eqLogic = $this->getEqLogic();
            $asAddr = $eqLogic->getAddress();
            if($asAddr){
                if($this->getLogicalId() == 'refresh'){
                    airsendCmd::readSensors($eqLogic, 0x1);
                }else{
                    $channel = $eqLogic->getChannel();
                    $thingnotes = $this->getThingNotes($_options);
                    self::transfer($asAddr, $channel, $thingnotes, $eqLogic);
                }
            }else{
                throw new Exception( __('Erreur de configuration : ', __FILE__) . __('localip invalide', __FILE__));
            }
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}


