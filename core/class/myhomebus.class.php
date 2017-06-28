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
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class myhomebus extends eqLogic {
    /*     * *************************Attributs****************************** */
	
	
    /*     * ***********************Methode static*************************** */
	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'myhomebus_dep';
		$request = realpath(dirname(__FILE__) . '/../../node/node_modules/request');
		$jsonfile = realpath(dirname(__FILE__) . '/../../node/node_modules/jsonfile');
		$return['progress_file'] = '/tmp/myhomebus_dep';
#		if (is_dir($jsonfile) && is_dir($request) && is_dir($jsonsocket)) {
			$return['state'] = 'ok';
#		} else {
#			$return['state'] = 'nok';
#		}
		return $return;
	}


	public static function dependancy_install() {
		log::add('myhomebus','info','Installation des dépéndances nodejs');
		$resource_path = realpath(dirname(__FILE__) . '/../../ressources');
		passthru('/bin/bash ' . $resource_path . '/nodejs.sh ' . $resource_path . ' > ' . log::getPathToLog('myhomebus_dep') . ' 2>&1 &');
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = 'myhomebuscmd';
		$return['state'] = 'nok';
		$pid = trim( shell_exec ('ps ax | grep "myhomebus/node/myhomebus.js" | grep -v "grep" | wc -l') );
		if ($pid != '' && $pid != '0') {
			$return['state'] = 'ok';
		}
		
		$return['launchable'] = 'ok';
    	if ((config::byKey('ipGateway', 'myhomebus') == '') && (config::byKey('portGateway', 'myhomebus') == '')) {
      		$return['launchable'] = 'nok';
      		$return['launchable_message'] = __('Aucune gateway configurée', __FILE__);
    	}
		
		return $return;
	}

	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		log::remove('myhomebuscmd');
		
		$enable_logging = (config::byKey('enableLogging', 'myhomebus', 0) == 1) ? 'yes' : 'no';
		if (file_exists(log::getPathToLog('myhomebuscmd') . '.message')) {
			unlink(log::getPathToLog('myhomebuscmd') . '.message');
		}
		if (!file_exists(log::getPathToLog('myhomebuscmd') . '.message')) {
			touch(log::getPathToLog('myhomebuscmd') . '.message');
		}
		
        $ipGateway = config::byKey('ipGateway', 'myhomebus', '192.168.1.35');
		$portGateway = config::byKey('portGateway', 'myhomebus', 20000);
		$mdpGateway = config::byKey('mdpGateway', 'myhomebus', 12345);
		$socketport = config::byKey('socketport', 'myhomebus', 55006);
		$log_path = log::getPathToLog('myhomebuscmd');
		$enable_log = $enable_logging;
		
		$url = network::getNetworkAccess('internal', 'proto:ip') . '/plugins/myhomebus/core/php/jeemyhome.php?apikey=' . jeedom::getApiKey('myhomebus');

		myhomebus::launch_svc($url, $ipGateway, $portGateway, $mdpGateway, $enable_log, $socketport);
	}

	public static function launch_svc($url, $ip, $port, $mdp, $enable_log, $socketport) {
		#$log = log::convertLogLevel(log::getLogLevel('myhomebus'));
		$myhome_path = realpath(dirname(__FILE__) . '/../../node');

		$cmd = 'nice -n 19 nodejs ' . $myhome_path . '/myhomebus.js ' . $url . ' ' . $ip . ' ' . $port . ' ' . $mdp . ' ' . $enable_log . ' ' . $socketport;

		log::add('myhomebus', 'debug', 'Lancement démon myHomeBus : ' . $cmd);

		$result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('myhomebuscmd') . ' 2>&1 &');
		if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
		log::add('myhomebus', 'error', $result);
		return false;
		}

		$i = 0;
		while ($i < 30) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			break;
		}
		sleep(1);
		$i++;
		}
		if ($i >= 30) {
		log::add('myhomebus', 'error', 'Impossible de lancer le démon myHomeBus, vérifiez le port', 'unableStartDeamon');
		return false;
		}
		message::removeAll('myhomebus', 'unableStartDeamon');
		log::add('myhomebus', 'info', 'Démon myHomeBus lancé');
		return true;
	}


	public static function deamon_stop() {
		exec('kill $(ps aux | grep "myhomebus/node/myhomebus.js" | awk \'{print $2}\')');
		log::add('myhomebus', 'info', 'Arrêt du service myHomeBus');
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
		sleep(1);
		exec('kill -9 $(ps aux | grep "myhomebus/node/myhomebus.js" | awk \'{print $2}\')');
		}
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
		sleep(1);
		exec('sudo kill -9 $(ps aux | grep "myhomebus/node/myhomebus.js" | awk \'{print $2}\')');
		}
	}

	public static function updateStatusCENP($pl) {
		$obj = json_decode($pl);
		$address = $obj->{'address'};
		$pushbutton = $obj->{'pushbutton'};
		$action = $obj->{'action'};
		$id = str_pad($address,4,'0',STR_PAD_LEFT).':'.str_pad($pushbutton,2,'0',STR_PAD_LEFT);		
		log::add('myhomebus','debug',"id CENP " . $id . " -> " . $action);
		$device = myhomebus::byLogicalId($id, 'myhomebus');		
		if (!is_object($myhome)) {
			log::add('myhomebus', 'debug', 'Aucun équipement trouvé pour : ' . $id . "\n");
			return;
		};		
		//$myhomecmd = $device->getCmd('info', 'press'.$action);
		//$myhomecmdnum = $myhome->getCmd('info', 'statusnum');		
		$status = NULL;
		//$statusnum = $action;

		log::add('myhomebus','debug',"mise a jour de l'action : ".'press'.$action."\n");
		//$myhomecmd->event(1);
		$device->checkAndUpdateCmd('press'.$action, 1);
		//$myhomecmdnum->checkAndUpdateCmd('press'.$action, 1);
		//$myhomecmdnum->event($statusnum);
		
		
		//$myhomecmd->save();
		//$myhomecmdnum->save();
	}

	public static function updateStatusPL($pl) {
		$obj = json_decode($pl);
		$amb = $obj->{'amb'};
		$pl = $obj->{'pl'};
		$status_pl = $obj->{'states'}->{'status'};
		$id = str_pad($amb,2,'0',STR_PAD_LEFT).':'.str_pad($pl,2,'0',STR_PAD_LEFT);
		log::add('myhomebus','debug',"id PL " . $id . " -> " . $status_pl);
		$device = myhomebus::byLogicalId($id, 'myhomebus');		
		if (!is_object($device)) {
			log::add('myhomebus', 'debug', 'Aucun équipement trouvé pour : ' . $id . "\n");
			return;
		};
		//$myhomecmd = $myhome->getCmd('info', 'status');
		//$myhomecmdnum = $myhome->getCmd('info', 'statusnum');		
		$status = NULL;
		$statusnum = NULL;
		if ($status_pl == 1) { //Allumage
			log::add('myhomebus','debug',"Allumage");
			$status = 'ON';
			$statusnum = 100;
		} else if ($status_pl == 0) { //Extinction 
			log::add('myhomebus','debug',"Extinction");
			$status = 'OFF';
			$statusnum = 0;
		} else {
			return;
		}

		log::add('myhomebus','debug',"mise a jour du status : ".$status."\n");
		//$myhomecmd->event($status);
		$device->checkAndUpdateCmd('status', $status);
		//$myhomecmdnum->event($statusnum);
		//$myhomecmd->save();
		//$myhomecmdnum->save();
	}

	public static function updateStatusDRYCONTACT($drycontact) {
		log::add('myhomebus','debug',"updateStatusDRYCONTACT");
		$obj = json_decode($vr);
		$value = $obj->{'value'};
		$adress = $obj->{'address'};
		$myhome = myhomebus::byLogicalId($adress, 'myhomebus');		
		if (!is_object($myhome)) {
			log::add('myhomebus', 'debug', 'Aucun équipement trouvé pour : ' . $id . "\n");
			return;
		};
		$device = $myhome->getConfiguration('device');
		if($device=='drycontact'){
			$myhomecmd = $myhome->getCmd('info', 'status');
			if($value){
				$status = 1;	
			}else{
				$status = 0;	
			}
			//$myhomecmd->event($status);
			$myhome->checkAndUpdateCmd('status', $status);
		}
	}

	public static function updateStatusVR($vr) {
		log::add('myhomebus','debug',"updateStatusVR");
		$obj = json_decode($vr);
		$amb = $obj->{'amb'};
		$pl = $obj->{'pl'};
		$status_vr = $obj->{'states'}->{'status'};
		$id = str_pad($amb,2,'0',STR_PAD_LEFT).':'.str_pad($pl,2,'0',STR_PAD_LEFT);				
		$myhome = myhomebus::byLogicalId($id, 'myhomebus');		
		if (!is_object($myhome)) {
			log::add('myhomebus', 'debug', 'Aucun équipement trouvé pour : ' . $id . "\n");
			return;
		};
		$device = $myhome->getConfiguration('device');
		if($device=='shutter'){
			log::add('myhomebus','debug',"id VR " . $id . " -> " . $status_vr);
			//$myhomecmd = $myhome->getCmd('info', 'status');
			//$myhomecmdnum = $myhome->getCmd('info', 'statusnum');		
			$status = NULL;
			$statusnum = NULL;
			if ($status_vr == -1) { //en monté
				log::add('myhomebus','debug',"En monté");
				$status = 'UP';
				$statusnum = 50;
			} else if ($status_vr == 1) { //Ouvert
				log::add('myhomebus','debug',"Ouvert");
				$status = 'OPEN';
				$statusnum = 100;
			} else if ($status_vr == 2) { //Fermé
				log::add('myhomebus','debug',"Fermé");			
				$status = 'CLOSED';
				$statusnum = 0;
			} else if ($status_vr == -2) { //en descente
				log::add('myhomebus','debug',"En descente");
				$status = 'DOWN';
				$statusnum = 50;
			} else {
				return;
			}
			log::add('myhomebus','debug',"mise a jour du status : ".$status."\n");
			//$myhomecmd->event($status);
			//$myhomecmdnum->event($statusnum);
			$myhome->checkAndUpdateCmd('status', $status);
			$myhome->checkAndUpdateCmd('statusnum', $statusnum);
			#$myhomecmd->save();
			//$myhomecmdnum->save();
		}
	}

	public static function updateStatusVRadv($vr) {
		log::add('myhomebus','debug',"updateStatusVRadv");
		$obj = json_decode($vr);
		$amb = $obj->{'amb'};
		$pl = $obj->{'pl'};
		$status_vr = $obj->{'states'}->{'status'};
		$ts = $obj->{'states'}->{'ts'};
		log::add('myhomebus','debug',"TS : ".$ts);
		$id = str_pad($amb,2,'0',STR_PAD_LEFT).':'.str_pad($pl,2,'0',STR_PAD_LEFT);
		$myhome = myhomebus::byLogicalId($id, 'myhomebus');		
		if (!is_object($myhome)) {
			log::add('myhomebus', 'debug', 'Aucun équipement trouvé pour : ' . $id . "\n");
			return;
		};
		$device = $myhome->getConfiguration('device');
		if($device=='shutteradv'){
			$myhomecmd = $myhome->getCmd('info', 'status'); //Etat actuel du VR
			$myhomecmdnum = $myhome->getCmd('info', 'statusnum'); //Position actuel du VR		
			$lastStatus = $myhomecmd->execCmd(null,2);
			$statusnum = $myhomecmdnum->execCmd(null,2);			
			log::add('myhomebus','debug','CURRENT STATUS : '.$lastStatus);
			log::add('myhomebus','debug','CURRENT POS : '.$statusnum);
			#$updatedate = $myhomecmd->getConfiguration('updatedate'); //Dt de dernier status
			$updatedate = $myhomecmdnum->getCollectDate();
			$duree_cmd	= $myhomecmdnum->getConfiguration('DureeCmd'); //Durée d'ouverture total du VR
			log::add('myhomebus','debug',"id VR " . $id . " ".$lastStatus." -> " . $status_vr);
			if (is_numeric($duree_cmd)) {
				$move_time = $duree_cmd;
			} else {
				$move_time = 30; //temps par defaut de monté du VR si non saisie
			}
			switch ($status_vr) {
				case -1 :  // Debut ouverture VR
					log::add('myhomebus','debug',"Debut ouverture VR");
					log::add('myhomebus','debug','LAST STATUS : '.$lastStatus);
					switch ($lastStatus) {
						case 'UP' : //S'il était déjà en monté, on ne fait rien
							break;
						case 'DOWN': //S'il était en descente, le sens a changé
							//On calcul sa nouvelle position au moment du changement de sens
							#$new_pos= ($move_time - ($updatedate - $ts)) / $move_time*100;
							$statusnum = round($new_pos);
							$status = 'UP';						
							#$myhomecmd->setConfiguration('updatedate',$ts);						
							#$myhomecmd->setConfiguration('status',$status);		
							#$myhomecmd->setConfiguration('updatedate',$ts);
							$myhomecmdnum->setCollectDate($ts);
							#$myhomecmdnum->setConfiguration('statusnum',$statusnum);						
							#$myhomecmdnum->setCollectDate($ts);
							//$myhomecmd->event($status);
							$myhome->checkAndUpdateCmd('status', $status);						
							break;
						case 'OPEN': //S'il était déjà ouvert, on ne fait rien
							$status = 'UP';	
							log::add('myhomebus','debug','OLD POSITION : '.$statusnum);
							#$myhomecmd->setConfiguration('updatedate',$ts);
							$myhomecmdnum->setCollectDate($ts);
							#$myhomecmdnum->setCollectDate($ts);
							//$myhomecmd->event($status);
							$myhome->checkAndUpdateCmd('status', $status);						
							break;
						case 'CLOSED': //S'il était fermé
							#$statusnum = 0;
							$status = 'UP';	
							#$myhomecmd->setConfiguration('updatedate',$ts);						
							#$myhomecmd->setConfiguration('status',$status);									
							#$myhomecmd->setConfiguration('updatedate',$ts);
							$myhomecmdnum->setCollectDate($ts);
							#$myhomecmdnum->setConfiguration('statusnum',$statusnum);						
							#$myhomecmdnum->setCollectDate($ts);
							//$myhomecmd->event($status);
							$myhome->checkAndUpdateCmd('status', $status);						
							break;					
					}				
					break;					
				case -2 :  // Debut fermeture VR
					log::add('myhomebus','debug',"Debut fermeture VR");							
					switch ($lastStatus) {
						case 'DOWN' : //S'il était déjà en monté, on ne fait rien
							break;
						case 'UP': //S'il était en descente, le sens a changé
							//On calcul sa nouvelle position au moment du changement de sens
#							$new_pos= ($move_time - ($updatedate - $ts)) / $move_time*100;
#							$statusnum = round($new_pos);
#							$status = 'UP';						
							#$myhomecmd->setConfiguration('updatedate',$ts);						
							#$myhomecmd->setConfiguration('status',$status);		
#							$myhomecmdnum->setConfiguration('updatedate',$ts);
							#$myhomecmdnum->setConfiguration('statusnum',$statusnum);						
							break;
						case 'OPEN': //S'il était déjà ouvert, on ne fait rien
							$status = 'DOWN';	
							log::add('myhomebus','debug','OLD POSITION : '.$statusnum);
							#$myhomecmd->setConfiguration('updatedate',$ts);
							#$myhomecmdnum->setCollectDate($ts);
							//$myhomecmd->event($status);
							$myhome->checkAndUpdateCmd('status', $status);						
							#$myhomecmdnum->event($statusnum);			
							break;
						case 'CLOSED': //S'il était fermé
							#$statusnum = 0;
							#$status = 'UP';	
							#$myhomecmd->setConfiguration('updatedate',$ts);						
							#$myhomecmd->setConfiguration('status',$status);		
							#$myhomecmdnum->setConfiguration('updatedate',$ts);
							#$myhomecmdnum->setConfiguration('statusnum',$statusnum);						
							break;					
					}				
					break;	
				case 1 :  // Arret ouverture totale du VR auto
					log::add('myhomebus','debug',"Arret ouverture totale du VR auto");
					$status = 'OPEN';
					$statusnum = 100;
					//$myhomecmd->event($status);
					//$myhomecmdnum->event($statusnum);	
					$myhome->checkAndUpdateCmd('status', $status);
					$myhome->checkAndUpdateCmd('statusnum', $statusnum);		
					break;	
				case 2 :  // Arret fermeture totale du VR auto
					log::add('myhomebus','debug',"Arret fermeture totale du VR auto");			
					$status = 'CLOSED';
					$statusnum = 0;
					//$myhomecmd->event($status);
					//$myhomecmdnum->event($statusnum);			
					$myhome->checkAndUpdateCmd('status', $status);
					$myhome->checkAndUpdateCmd('statusnum', $statusnum);
					break;	
				case 3 :  // Arret manuel du VR
					if(($lastStatus=='DOWN')||($lastStatus=='UP')){
						log::add('myhomebus','debug',"Arret manuel du VR");			
						$status = 'OPEN';	
						log::add('myhomebus','debug',"dt Deb : ".$updatedate);			
						log::add('myhomebus','debug',"dt Fin : ".$ts);			
						log::add('myhomebus','debug',"FullMove : ".$move_time);	
						log::add('myhomebus','debug',"move time : ".($ts - $updatedate));	
						log::add('myhomebus','debug','OLD POSITION : '.$statusnum);
						#$new_pos= ($move_time - ($updatedate - $ts)) / $move_time*100;
						$new_pos = $statusnum+(($ts-$updatedate)/$move_time*100);
						$statusnum = round($new_pos);
						log::add('myhomebus','debug','NOUVELLE POSITION : '.$statusnum);
						#$myhomecmd->setConfiguration('updatedate',$ts);						
						#$myhomecmd->setConfiguration('status',$status);		
						#$myhomecmd->setConfiguration('updatedate',$ts);
						$myhomecmdnum->setCollectDate($ts);
						#$myhomecmdnum->setConfiguration('statusnum',$statusnum);						
						//$myhomecmd->event($status);
						//$myhomecmdnum->event($statusnum);			
						$myhome->checkAndUpdateCmd('status', $status);
						$myhome->checkAndUpdateCmd('statusnum', $statusnum);
					}
					break;	
			};
			
			#$myhomecmd->save();
			#$myhomecmdnum->save();
		}
	}

/*     * *********************Methode d'instance************************* */

	public function preInsert() {
		if ($this->getLogicalId() == '') {
			for ($i = 0; $i < 20; $i++) {
				$logicalId = strtoupper(str_pad(dechex(mt_rand()), 8, '0', STR_PAD_LEFT));
				$result = eqLogic::byLogicalId($logicalId, 'myhomebus');
				if (!is_object($result)) {
					$this->setLogicalId($logicalId);
					break;
				}
			}
		}
	}

	public function preSave() {
		// Generation du LogicalID en fonction de la configuration		
		$device = $this->getConfiguration('device'); 
		switch ($device) {
			case 'light':
			case 'shutteradv':
			case 'shutter':
				$amb = $this->getConfiguration('ambiance');
				$pl = $this->getConfiguration('pl');
				$logicalId = str_pad($amb,2,'0',STR_PAD_LEFT).':'.str_pad($pl,2,'0',STR_PAD_LEFT);
				break;
			case 'cenp':
				$object = $this->getConfiguration('object');
				$button = $this->getConfiguration('button');
				$logicalId = str_pad($object,4,'0',STR_PAD_LEFT).':'.str_pad($button,2,'0',STR_PAD_LEFT);
				break;
		}		
		log::add('myhomebus','debug',"generation LogicalID : ".$logicalId);
		$this->setLogicalId($logicalId);
	}

	public function postSave() {
	
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	//                                                                                                                                               //
	//                                                      Gestion des Template d'equipement                                                       // 
	//                                                                                                                                               //
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
 	public static function devicesParameters($_device = '') {
		$path = dirname(__FILE__) . '/../config/devices';
		if (isset($_device) && $_device != '') {
			$files = ls($path, $_device . '.json', false, array('files', 'quiet'));
			if (count($files) == 1) {
				try {
					$content = file_get_contents($path . '/' . $files[0]);
					if (is_json($content)) {
						$deviceConfiguration = json_decode($content, true);
						return $deviceConfiguration[$_device];
					}
				} catch (Exception $e) {
					return array();
				}
			}
		}
		$files = ls($path, '*.json', false, array('files', 'quiet'));
		$return = array();
		foreach ($files as $file) {
			try {
				$content = file_get_contents($path . '/' . $file);
				if (is_json($content)) {
					$return = array_merge($return, json_decode($content, true));
				}
			} catch (Exception $e) {

			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				return $return[$_device];
			}
			return array();
		}
		return $return;
	}

	public function applyModuleConfiguration($template) {		
		$device = $this->getConfiguration('device'); 
		log::add('myhomebus','debug',"Device : ".$device);			
		log::add('myhomebus','debug',"Template : ".$template);			
		//$this->save();		
		if ($template == '') {
		//	$this->save();
			return true;
		}
		$device = self::devicesParameters($template);
		if (!is_array($device) || !isset($device['cmd'])) {
			return true;
		}
		if (isset($device['configuration'])) {
			foreach ($device['configuration'] as $key => $value) {
				$this->setConfiguration($key, $value);
				log::add('myhomebus','debug',"Set config ".$key.' '.$value);			
			}
		}
		$cmd_order = 0;
		$link_cmds = array();
		foreach ($device['cmd'] as $command) {
			log::add('myhomebus','debug',"Ajout d'une commande ".$command['name']);			
			if (isset($device['cmd']['logicalId'])) {
				continue;
			}
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if (isset($command['name']) && $liste_cmd->getName() == $command['name']) {
					$cmd = $liste_cmd;	
					break;
				}
			}
			try {
				if ($cmd == null || !is_object($cmd)) {
					log::add('myhomebus','debug',"1a");			
					$cmd = new myhomebusCmd();
					$cmd->setOrder($cmd_order);
					$cmd->setEqLogic_id($this->getId());
					log::add('myhomebus','debug','Id Cmd: '.$cmd->getEqLogic_id());			
					log::add('myhomebus','debug','Id : '.$this->getId());			
				} else {
					log::add('myhomebus','debug',"1b");			
					$command['name'] = $cmd->getName();
				}			
				
				utils::a2o($cmd, $command);
				if (isset($command['value']) && $command['value']!="") {
					$CmdValue=cmd::byEqLogicIdCmdName($this->getId(),$command['value']);
					if(is_object($CmdValue))
						$cmd->setValue('#'.$CmdValue->getId().'#');
					else
						$cmd->setValue(null);
				}
				if (isset($command['configuration']['option']) && $command['configuration']['option']!="") {
					$options=array();
					foreach($command['configuration']['option'] as $option => $cmd){
						$CmdValue=cmd::byEqLogicIdCmdName($this->getId(),$cmd);
						if(is_object($CmdValue))
							$options[$option]='#'.$CmdValue->getId().'#';
					}
						$cmd->setConfiguration('option',$options);
				}								
				$cmd->save();
				log::add('myhomebus','debug','Id : '.$cmd->getId());							
				log::add('myhomebus','debug',"save");			
				$cmd_order++;
				array_push($link_cmds,utils::o2a($cmd));

			} catch (Exception $exc) {
				error_log($exc->getMessage());
			}
		}
		return json_encode(['cmd' => $link_cmds]);
	}

/*     * **********************Getteur Setteur*************************** */
} 	

class myhomebusCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

	public static function getIdMyHome($id) {
		if ((substr($id,0,1)=="0") and (substr($id,3,1)=="0")){
			return substr($id,1,1).substr($id,4,1);
		}else{
			return substr($id,0,2).substr($id,3,2);
		}		
	}


    /*     * *********************Methode d'instance************************* */

	public function execute($_options = null) {
		if ($this->getType() == 'action') {
			$logicalId = $this->getEqlogic()->getLogicalId();
			$where = myhomebusCmd::getIdMyHome($logicalId);
			log::add ('myhomebus','debug','logicalId : '.$logicalId.' where : '.$where);
			$cde = $this->getLogicalId();
			$cde = trim(str_replace("#WHERE#", $where, $cde));
#			log::add('myhomebus','debug',$cde);

#			log::add ('myhomebus','debug',config::byKey('port', 'myhomebus', 'none'));
#            if (config::byKey('jeeNetwork::mode') == 'master') {
#                foreach (jeeNetwork::byPlugin('myhomebus') as $jeeNetwork) {
#                    #foreach ($values as $value) {
#						#log::add ('myhomebus','debug','Cde : '.$cde);
#						#log::add ('myhomebus','debug','IP : '.$jeeNetwork->getRealIp());
#						#log::add ('myhomebus','debug','Port : '.config::byKey('socketport', 'myhomebus', 55006));
#						log::add ('myhomebus','debug','TITI');
#                        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
#                        socket_connect($socket, $jeeNetwork->getRealIp(), config::byKey('socketport', 'myhomebus', 55006));
#                        //socket_write($socket, trim($cde), strlen(trim($cde)));
#						$msg = json_encode(['action' => 'sendcde', 'cde' => $cde]);
#						socket_write($socket, $msg, strlen($msg));
#                        socket_close($socket);
#						log::add ('myhomebus','event','Send from Jeedom : '.$cde);
#                    #}
#                }
#            }
#            if (config::byKey('port', 'myhomebus', 'none') != 'none') {
				#log::add ('myhomebus','debug','TEST TEST TEST');
                #foreach ($values as $value) {
                    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
                    socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'myhomebus', 55006));
                    //socket_write($socket, trim($cde), strlen(trim($cde)));
					$msg = json_encode(['action' => 'sendcde', 'cde' => $cde]);
					socket_write($socket, $msg, strlen($msg));
                    socket_close($socket);
					log::add ('myhomebus','event','Send from Jeedom : '.$cde);
                #}
#            }
        }
    }

	public function postSave() {	
		if($this->getType()=='action'){ //Seulement si une commande de type ACTION
			$cenp = $this->getConfiguration('cenp');
			if($cenp){ //Si on trouve une configuration CEN+
				log::add ('myhomebus','debug','POST SAVE CDE');
				$cmd = cmd::byId(str_replace('#', '', $cenp));
				if(is_object($cmd)){
					#$cmd->setValue('#'.$CmdValue->getId().'#');
					log::add ('myhomebus','debug','cmd cenp trouvé '.$cenp);
					#$eqLogic = eqLogic::byId($cmd->eqLogic_id);				
					$eqLogic = $cmd->getEqlogic();
					if(is_object($eqLogic)){
						log::add ('myhomebus','debug','objet cenp trouvé');
						$object = $eqLogic->getConfiguration('object');
						$button = $eqLogic->getConfiguration('button');
						$action = str_replace('press','',$cmd->getLogicalId());
	#					log::add ('myhomebus','debug',$object.' '.$button.' '.$action);
						// *25*action#pushbutton*object##
						$cdeCenP='*25*'.$action.'#'.$button.'*'.$object.'##';
						
						$logicalId = $this->getEqlogic()->getLogicalId();
	#					log::add ('myhomebus','debug',$logicalId);
						$where = myhomebusCmd::getIdMyHome($logicalId);
						$cde = $this->getLogicalId();
						$cde = trim(str_replace("#WHERE#", $where, $cde));

						log::add ('myhomebus','debug',$cdeCenP.' ->  '.$cde);

						$socket = socket_create(AF_INET, SOCK_STREAM, 0);
						socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'myhomebus', 55006));
						$msg = json_encode(['action' => 'setRaccourci', 'cdeCenP' => $cdeCenP, 'cde' => $cde, 'id' => $this->getID()]);
						socket_write($socket, $msg, strlen($msg));
						socket_close($socket);

					}
				}else{
					log::add ('myhomebus','debug','cmd cenp introuvable '.$cenp);
				}	
			} else { //On ne trouve pas de config CEN+
				//Peut être qu'il viens d'etre supprimé
				$socket = socket_create(AF_INET, SOCK_STREAM, 0);
				socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'myhomebus', 55006));
				$msg = json_encode(['action' => 'delRaccourci', 'id' => $this->getID()]);
				socket_write($socket, $msg, strlen($msg));
				socket_close($socket);										
			}	
		}	
	}
    /*     * **********************Getteur Setteur*************************** */

}


?>
