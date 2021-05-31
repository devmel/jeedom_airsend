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
require_once dirname(__FILE__) . "/../class/airsend.class.php";

if (!jeedom::apiAccess(init('apikey'), 'airsend')) {
	echo __('Clé API non valide, vous n\'etes pas autorisé à effectuer cette action', __FILE__);
	die();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);

if (is_array($data) && isset($data['events'])) {
	foreach ($data['events'] as $i => $val) {
		$collectdate = strftime("%Y-%m-%d %H:%M:%S", ($val['timestamp']/1000));
		if(isset($val['channel']) && isset($val['type']) && isset($val['thingnotes'])){
			//Get channel
			$channel = $val['channel'];
			$forwardstate = null;
			$forwardstatevalue = null;
			//Transfer event
			if(isset($val['thingnotes']['uid'])){
				//Search cmd & eqLogic
				$eqLogic = null;
				$cmd = airsendCmd::byId($val['thingnotes']['uid']);
				if(isset($cmd) && is_object($cmd)){
					$eqLogic = $cmd->getEqLogic();
				}
				if(isset($eqLogic) && is_object($eqLogic)){
					if($val['type'] == 3 || $val['type'] == 2 || $val['type'] == 1){
						if($eqLogic->updateStateWithNotes($val['thingnotes']['notes'], $collectdate)){
							$forwardstate = $eqLogic->getConfiguration('forwardstate', null);
							$forwardstatevalue = $eqLogic->getConfiguration('forwardstatevalue', null);
						}
					}else if($channel['id'] > 1 || isset($channel['source'])){
						$eqLogic->updateErrorWithEvent($val['type'], $collectdate, $cmd);
					}
				}
			//Interrupt event
			}else{
				if($val['type'] == 3){			//Event type GOT (sensor)
					$found = false;
					//Search eqLogic and update
					$plugin = plugin::byId('airsend');
					$eqLogics = eqLogic::byType($plugin->getId());
					foreach ($eqLogics as $eqLogic) {
						if($eqLogic->isChannel($channel)){
							if($eqLogic->getIsEnable()){
								if($eqLogic->updateStateWithNotes($val['thingnotes']['notes'], $collectdate)){
									$forwardstate = $eqLogic->getConfiguration('forwardstate', null);
									$forwardstatevalue = $eqLogic->getConfiguration('forwardstatevalue', null);
									$found = true;
								}
							}
						}
					}
					if($found == false){
						$device = airsend::getBaseDevice($data['localip']);
						if($device && $device->getConfiguration('autoinclude', null)){
							airsend::createFromEvent($val, $device);
						}
					}
				}
			}
			//Forward event one time only
			if(isset($forwardstate) && is_numeric($forwardstate)){
				$forwardeqLogic = airsend::byId($forwardstate);
				if(isset($forwardeqLogic) && is_object($forwardeqLogic)){
					$notes = $val['thingnotes']['notes'];
					if(is_array($notes) && count($notes) > 0){
						$note = &$notes[0];
						if($note['type'] == 1){
							$note['value'] = 'TOGGLE';
						}
						$note['type'] = 0;
						if(isset($forwardstatevalue)){
							switch($forwardstatevalue){
								case '0':
									$note['value'] = 'DOWN';
								break;
								case '50':
									$note['value'] = 'STOP';
								break;
								case '100':
									$note['value'] = 'UP';
								break;
							}
						}
					}
					$forwardeqLogic->updateStateWithNotes($notes, $collectdate);
				}
			}
		}
/*		//Debug in file
		$val["original_timestamp"] = $val['timestamp'];
		$val["localip"] = $data['localip'];
		$val["timestamp"] = $collectdate;
		file_put_contents("cb_dump.txt", json_encode($val)."\r\n", FILE_APPEND);
*/
	}
}

?>