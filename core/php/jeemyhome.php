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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'myhomebus')) {	
#	connection::failed();
	echo 'Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (jeemyhome)';
	log::add ('myhomebus', 'debug', "Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (jeemyhomebus)");
	die();
}

if (isset($_GET['test'])) {
	echo 'OK';
	die();
}

if (isset($_GET['statePL'])) {
	log::add ('myhomebus', 'debug', "recu changement de status d'un PL");
	$var = urldecode($_GET['statePL']);
	log::add ('myhomebus', 'debug',$var);
	myhomebus::updateStatusPL($var);
	echo 'OK';
}

if (isset($_GET['stateVR'])) {
	log::add ('myhomebus', 'debug', "recu changement de status d'un VR");
	$var = urldecode($_GET['stateVR']);
	log::add ('myhomebus', 'debug',$var);
	myhomebus::updateStatusVR($var);
	myhomebus::updateStatusVRadv($var);
	echo 'OK';
}

if (isset($_GET['cenplus'])) {
	log::add ('myhomebus', 'debug', "recu commande CEN+");
	$var = urldecode($_GET['cenplus']);
	log::add ('myhomebus', 'debug',$var);
	myhomebus::updateStatusCENP($var);
	echo 'OK';
}

if (isset($_GET['cen'])) {
	log::add ('myhomebus', 'debug', "recu commande CEN");
	$var = urldecode($_GET['cen']);
	log::add ('myhomebus', 'debug',$var);
	myhomebus::updateStatusCEN($var);
	echo 'OK';
}
if (isset($_GET['drycontact'])) {
	log::add ('myhomebus', 'debug', "recu commande DRYCONTACT");
	$var = urldecode($_GET['cen']);
	log::add ('myhomebus', 'debug',$var);
	myhomebus::updateStatusDRYCONTACT($var);
	echo 'OK';
}