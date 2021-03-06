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
        chdir($deamon_root);
        return array('script' => $deamon_root . 'dependancy_install.sh #stype#', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

    /*     * **************************Deamon******************************** */
    public static function deamon_is_signed() {
        $signed = 0;
        $deamon_file = self::getDeamon();
        if($deamon_file){
            $disable_sign = config::byKey('disable_sign', 'airsend', 0);
            if($disable_sign > 0){
                $signed = 2;
            }else{
                $deamon_root = self::getDeamonPath();
                if(@chdir($deamon_root)){
                    try{
                        $request_shell = new com_shell('export GNUPGHOME="'.self::getTmpPath().'"; gpg --no-options --no-default-keyring --keyring '.self::getPublicSignKey().' --verify '.$deamon_file.'.sig');
                        $result = $request_shell->exec();
                        if(strpos($result, "Good signature from \"Devmel Apps <apps@devmel.com>\"") !== false){
                            $signed = 1;
                        }
                    } catch (Exception $e) {
                        if(strpos($e->getMessage(), ": not found") == true){
                            $signed = -1;
                        }
                    }
                }
            }
        }
        return $signed;
    }

    public static function deamon_info() {
        $port_server = config::byKey('port_server', 'airsend', 33863)&0xffff;
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
        $deamon_info = self::deamon_info();
        if ($deamon_info['launchable'] != 'ok' || $deamon_info['state'] == 'ok') {
            return;
        }
        $port_server = config::byKey('port_server', 'airsend', 33863);
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
        }else if($signed < 0){
            throw new Exception( __('Signature inv??rifiable (gpg manquant) : ', __FILE__) . __('veuillez mettre a jour les d??pendances', __FILE__));
        }else{
            throw new Exception( __('Signature erron??e : ', __FILE__). __('veuillez mettre a jour les d??pendances', __FILE__));
        }
    }

    public static function deamon_stop() {
        $launchpath = self::getTmpPath();
        @chdir($launchpath);
        if (file_exists('AirSendWebService.lock')) {
            $pid = trim(file('AirSendWebService.lock')[0]);
            if(isset($pid) && is_numeric($pid)){
                system::kill(intval($pid));
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
        return dirname(__FILE__) . '/../../ressources/scripts/bin/unix/';
    }
    public static function getScriptPath(){
        return dirname(__FILE__) . '/../../ressources/scripts/';
    }
    public static function getTmpPath(){
        return '/tmp';
    }
    public static function getPublicSignKey(){
        return dirname(__FILE__) . '/../../ressources/Devmel_Apps.gpg';
    }
    public static function getChannelsInformationFile(){
        return dirname(__FILE__) . '/../../ressources/channels.json';
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

    public static function getChannelsInformation(){
        $file = self::getChannelsInformationFile();
        $content = file_get_contents($file);
        if($content !== false){
            return json_decode($content);
        }
        return array();
    }

    public static function request($url, $data = null, $method = 'GET', $token = null) { 
        $port_server = config::byKey('port_server', 'airsend', 33863);
        $full_url = 'http://127.0.0.1:' . $port_server . '/' . $url;
        $result = false;
        if ((extension_loaded('curl') === true) && (is_resource($curl = curl_init()) === true)){
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
		$eqLogics = eqLogic::byType('airsend');
		foreach ($eqLogics as $eqLogic)
		{
            if ($eqLogic->getIsEnable() == 0) continue;
            try{
                $deviceType = intval($eqLogic->getConfiguration('device_type'));
                if($deviceType == 0){
                    $eqLogic->updateChannelInformation();
                    $asAddr = $eqLogic->getAddress();
                    if($eqLogic->getConfiguration('listenmode', null)){
                        $eqLogic->bind();
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
		$eqLogics = eqLogic::byType('airsend');
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
        $plugin = plugin::byId('airsend');
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
        $plugin = plugin::byId('airsend');
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
                    if($deviceType >= 4096 && $deviceType <= 4098){
                        $protocol = intval($device['pid']);
                        if ($protocol > 0) {
                            try{
                                $eqLogic = new airsend();
                                $eqLogic->setEqType_name("airsend");
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
                $status['status'] = __('Ce nom existe d??j??', __FILE__);
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
                    $eqLogic->setEqType_name("airsend");
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
                $status['status'] = __('Cette interface existe d??j??', __FILE__);
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
            $eqLogic->setEqType_name('airsend');
            $eqLogic->setIsEnable(1);
            $eqLogic->setIsVisible(1);
            if(array_key_exists("counter", $channel)){
                $eqLogic->setConfiguration('nocopy', 1);
            }
            $eqLogic->setConfiguration('localip', $device_base->getConfiguration('localip'));
            $eqLogic->setConfiguration('protocol', $channel['id']);
            $eqLogic->setConfiguration('address', $channel['source']);
            if(isset($channel['seed'])){
                $eqLogic->setConfiguration('mac', $channel['seed']);
            }
            $thingnotes = $event['thingnotes'];
            $notes = $thingnotes['notes'];
            $cmdLogicalId = null;
            if(is_array($notes) && count($notes) > 0){
                $note = $notes[0];
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
                    $cmdLogicalId = "temperature";
                }
            }
            if($type > 0){
                $eqLogic->setConfiguration('device_type', $type);
                $eqLogic->save();
                if (method_exists($eqLogic, 'postAjax')) {
                    $eqLogic->postAjax();
                }
                if(isset($cmdLogicalId)){
                    $cmd = airsendCmd::createFromLogicalId($cmdLogicalId, $eqLogic->getId());
                    if($cmd){
                        $cmd->save();
                    }
                }
                $msg = __('Nouvel appareil sans fil ajout??', __FILE__);
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

    public static function toUniqueChannel($channel){
        $result = array();
        $uniquefield = array('id', 'source', 'seed');
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
            $uniquefield = array('source', 'seed');
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
     * Fonction ex??cut??e automatiquement toutes les 15 minutes par Jeedom
     */
	public static function cron15()
	{
        self::refreshInfo();
	}

    /*
     * Fonction ex??cut??e automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction ex??cut??e automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */



    /*     * *********************M??thodes d'instance************************* */

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
                $eqLogics = eqLogic::byType('airsend');
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

    public function bind(){
        $deviceType = intval($this->getConfiguration('device_type'));
        if($deviceType == 0){
            $asAddr = $this->getAddress();
            if(isset($asAddr)){
                $callbackurl = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/airsend/core/php/jeeAirSend.php?apikey='.jeedom::getApiKey('airsend');
                $data = "{\"channel\":{\"id\":1},\"duration\":0,\"callback\":\"".$callbackurl."\"}";
                self::request("airsend/bind", $data, 'POST', $asAddr);
            }
        }
    }
    public function close(){
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
            $res = airsend::request("airsend/channels", null, 'GET', $asAddr);
            if($res !== false && isset($res) && is_array($res) && isset($res['data'])){
                $cur_channels = self::getChannelsInformation();
                $channelsInformation = json_decode($res['data']);
                if(is_array($channelsInformation) && count($channelsInformation) > count($cur_channels)){
                    $file = self::getChannelsInformationFile();
                    file_put_contents($file, json_encode($channelsInformation));
                }
            }
        }
    }

    public function getChannel(){
        $result = array();
        $result['id'] = self::toUIntValue($this->getConfiguration('protocol'));
        if($result['id'] > 0){
            $source = $this->getConfiguration('address', null);
            $seed = $this->getConfiguration('mac', null);
            $presstype = $this->getConfiguration('presstype', 0);
            if($source){
                $result['source'] = self::toUIntValue($source);
            }
            if($seed){
                $result['counter'] = $seed;
                $result['seed'] = $seed;
            }
            if($presstype > 0){
                $result['duration'] = 3000;
            }
        }
        return $result;
    }

    public function isChannel($channel){
        $result = false;
        if(isset($channel)){
            $channel = self::toUniqueChannel($channel);
            $lchan = self::toUniqueChannel($this->getChannel());
            $result = ($channel == $lchan);
        }
        return $result;
    }

    public function updateErrorWithEvent($event_type, $collectdate, $cmd){
        //Message UNKNOWN,NETWORK,SYNCHRONIZATION,SECURITY,BUSY,TIMEOUT,UNSUPPORTED,INCOMPLETE,FULL
        $error_message = array();
        $error_message[] = __('Une erreur inconnue est survenue', __FILE__);
        $error_message[] = __('Le r??seau n\'est pas accessible', __FILE__);
        $error_message[] = __('Une erreur de synchronisation s\'est produite', __FILE__);
        $error_message[] = __('Echec de l\'authentification', __FILE__);
        $error_message[] = __('Un autre utilisateur a verrouill?? l\'appareil', __FILE__);
        $error_message[] = __('Echec de la connexion dans un d??lai raisonnable', __FILE__);
        $error_message[] = __('Requ??te non support??e', __FILE__);
        $error_message[] = __('Requ??te incompl??te', __FILE__);
        $error_message[] = __('Requ??te trop grande', __FILE__);
        $event_type = intval($event_type) - 0x100;
        if($event_type < 0)
            $event_type = 0;
        $msg = $error_message[0];
        if($event_type < count($error_message))
            $msg = $error_message[$event_type];
        log::add('airsend', 'error', __('Erreur ex??cution de la commande ', __FILE__) . $cmd->getHumanName() . ' : ' . $msg);
        event::add('jeedom::alert', array('level' => 'danger', 'message' => $msg));
        //Update state collectdate
        $statecmd = airsendCmd::byEqLogicIdAndLogicalId($this->getId(), 'state');
        if(isset($statecmd) && is_object($statecmd)){
            $svalue = $statecmd->execCmd();
            $this->checkAndUpdateCmd('state', $svalue, $collectdate);
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
            if($deviceType == 4098){
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
                foreach ($cmd_list as $cmd){
                    if ($cmd->getLogicalId() == 'temperature' && !$cmds["temperature"]) {
                        $cmds["temperature"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'illuminance' && !$cmds["illuminance"]) {
                        $cmds["illuminance"] = $cmd;
                    }else if ($cmd->getLogicalId() == 'refresh' && !$cmds["refresh"]) {
                        $cmds["refresh"] = $cmd;
                    }else{
                        $cmd->remove();
                    }
                }
                if(!$cmds["illuminance"]){
                    $cmds["illuminance"] = airsendCmd::createFromLogicalId("illuminance", $this->getId());
                    if($cmds["illuminance"]){
                        $cmds["illuminance"]->setOrder(2);
                        $cmds["illuminance"]->save();
                    }
                }
                if(!$cmds["temperature"]){
                    $cmds["temperature"] = airsendCmd::createFromLogicalId("temperature", $this->getId());
                    if($cmds["temperature"]){
                        $cmds["temperature"]->setOrder(1);
                        $cmds["temperature"]->save();
                    }
                }
                if($copyable && !$cmds["refresh"]){
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
        if($this->getConfiguration('listenmode', null)){
            $this->bind();
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
            if($deviceType >= 4096 && $deviceType <= 4098){
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
     * Non obligatoire mais ca permet de d??clencher une action apr??s modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de d??clencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class airsendCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */
    public static function createFromLogicalId($logicalId, $eqLogicId){
        $infofield = array('state', 'temperature', 'illuminance');
        $actionfield = array('refresh', 'toggle', 'off', 'on', 'stop', 'down', 'up');
        if(isset($eqLogicId) && isset($logicalId) && (in_array($logicalId, $infofield) || in_array($logicalId, $actionfield))){
            $cmd = new airsendCmd();
            $cmd->setEqLogic_id($eqLogicId);
            $cmd->setEqType('airsend');
            $cmd->setLogicalId($logicalId);
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
                    $cmd->setName(__('??teindre', __FILE__));
					$cmd->setDisplay('icon', '<i class="icon jeedomapp-ampoule-off"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_OFF');
                }else if($logicalId == "on"){
                    $cmd->setName(__('Allumer', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon jeedomapp-ampoule-on"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_ON');
                }else if($logicalId == "stop"){
                    $cmd->setName(__('Arr??ter', __FILE__));
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
                }
            }else{
                $cmd->setType('info');
                $cmd->setSubType('numeric');
                $cmd->setTemplate('dashboard', 'default');
                $cmd->setTemplate('mobile', 'default');
                $cmd->setDisplay('showNameOndashboard', '0');
                $cmd->setDisplay('showNameOnmobile', '0');
                if($logicalId == "state"){
                    $cmd->setName(__('??tat', __FILE__));
                    $cmd->setDisplay('generic_type', 'GENERIC_INFO');
                }else if($logicalId == "temperature"){
					$cmd->setTemplate('dashboard', 'tile');
                    $cmd->setName(__('Temp??rature', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon jeedom-thermometre-celcius"></i>');
                    $cmd->setDisplay('generic_type', 'TEMPERATURE');
                    $cmd->setUnite('??C');
                }else if($logicalId == "illuminance"){
					$cmd->setTemplate('dashboard', 'tile');
                    $cmd->setName(__('??clairement', __FILE__));
                    $cmd->setDisplay('icon', '<i class="icon nature-weather1"></i>');
                    $cmd->setDisplay('generic_type', 'LIGHT_BRIGHTNESS');
                    $cmd->setUnite('lux');
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
                    if($type == 'TEMPERATURE' || $type == 'ILLUMINANCE'){
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
        $data['callback'] = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/airsend/core/php/jeeAirSend.php?apikey='.jeedom::getApiKey('airsend');
        $data['channel'] = $channel;
        $data['thingnotes'] = $thingnotes;
        $res = airsend::request("airsend/transfer", json_encode($data, true), 'POST', $device);
        if($res !== false && isset($res) && is_array($res)){
            if(isset($res['error'])){
                throw new Exception( __('Erreur retourn?? par la commande curl : ', __FILE__). $res['error']);
            }
            if(isset($res['info']) && isset($res['info']['http_code'])){
                $val = intval($res['info']['http_code']);
                if($val != 200){
                    throw new Exception( __('Erreur de r??quete HTTP : code ', __FILE__). $val);
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
    public function getThingNotes(){
        $opt = null;
        $command = airsend::toUIntValue($this->getValue());
        $eqLogic = $this->getEqLogic();
        if(isset($eqLogic))
            $opt = $eqLogic->getConfiguration('opt', null);
        $thingnotes = array();
        $thingnotes['uid'] = airsend::toUIntValue($this->getId());
        $thingnotes['notes'] = array();

        $note = array();
        $note['method'] = "PUT";
        if($command == 6 && isset($opt)){
            $note['type'] = "DATA";
            $note['value'] = airsend::toUIntValue($opt);
        }else{
            $note['type'] = "STATE";
            $cmdmap = array("OFF", "ON", "PROG", "STOP", "DOWN", "UP", "TOGGLE");
            $value = "TOGGLE";
            if($command < count($cmdmap)){
                $value = $cmdmap[$command];
            }
            $note['value'] = $value;
        }
        $thingnotes['notes'][0] = $note;
        return $thingnotes;
    }

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes m??me si elles ne sont pas dans la nouvelle configuration de l'??quipement envoy?? en JS
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
                    $thingnotes = $this->getThingNotes();
                    self::transfer($asAddr, $channel, $thingnotes, $eqLogic);
                }
            }else{
                throw new Exception( __('Erreur de configuration : ', __FILE__) . __('localip invalide', __FILE__));
            }
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}


