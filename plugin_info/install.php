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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function myhome_install() {
	log::add('myhomebus', 'debug', 'Installation du Plugin Myhome BUS');
	/*exec('sudo chmod 777 '.dirname(__FILE__) . '/install.sql');
	$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
    DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);*/
}

function myhome_update() {
	log::add('myhomebus', 'debug', 'Update du Plugin Myhome BUS');
	/*try {
		exec('sudo chmod 777 '.dirname(__FILE__) . '/install.sql');
		$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
		DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
	} catch (Exception $e) {

	}*/
}

function myhome_remove() {
	log::add('myhomebus', 'debug', 'Suppression du Plugin Myhome BUS');
	//$sql = "DROP TABLE IF EXISTS myhome_scenarios;";
    //DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
}
?>
