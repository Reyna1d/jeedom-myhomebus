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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fa fa-list-alt"></i> {{Général}}</legend>
		    <div class="form-group">
				<label class="col-sm-4 control-label">{{IP Gateway}}</label>
				<div class="col-sm-2">
                    <input class="configKey form-control" data-l1key="ipGateway" value='192.168.1.35' />
                </div>
			</div>
            <div class="form-group">
				<label class="col-sm-4 control-label">{{Port Gateway}}</label>
				<div class="col-sm-2">
                    <input class="configKey form-control" data-l1key="portGateway" value='20000' />
                </div>
			</div>
            <div class="form-group">
				<label class="col-sm-4 control-label">{{Mot de passe Gateway}}</label>
				<div class="col-sm-2">
                    <input class="configKey form-control" data-l1key="mdpGateway" value='12345' />
                </div>
			</div>            
            <div class="form-group">
               <label class="col-sm-4 control-label">{{Enregistrer tous les messages (cela peut ralentir le système)}}</label>
               <div class="col-sm-1">
                <input type="checkbox" class="configKey" data-l1key="enableLogging" />
            </div>
            <div class="col-sm-7">
                <a class="btn btn-default" id="bt_logMyhomeMessage"><i class="fa fa-file-o"></i> {{Voir les messages}}</a>
            </div>
        </div>
		
        <div class="form-group">
            <label class="col-sm-4 control-label">{{Port socket interne (modification dangereuse}}</label>
            <div class="col-sm-2">
                <input class="configKey form-control" data-l1key="socketport" value='55006' />
            </div>
        </div>
    </fieldset>
</form>


<script>
    $('#bt_logMyhomeMessage').on('click', function () {
        $('#md_modal').dialog({title: "{{Log des messages Myhome}}"});
        $('#md_modal').load('index.php?v=d&plugin=myhomebus&modal=show.log').dialog('open');
    });
</script>