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

if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

$eqLogics = airsend::byType('airsend');
$ips = array();
foreach ($eqLogics as $eqLogic) {
    $deviceType = intval($eqLogic->getConfiguration('device_type'));
    if($deviceType == 0){
        $localip = $eqLogic->getConfiguration('localip');
        if (filter_var($localip, FILTER_VALIDATE_IP)) {
            $ips[] = $localip;
        }
    }
}
$ips = array_unique($ips);
?>

<div style="display: none;width : 100%" id="div_modal"></div>
<div>
    <p>
    <label>{{Veuillez choisir un fichier ou entrer son contenu :}}</label>
    <br />
    <input type="file" id="import_file">
    <br />
    <textarea id="import_text" style="min-width:250px;min-height:150px"></textarea>
    </p>
    <p>
	<a class="btn btn-success" id="bt_import"><i class="fa fa-check-circle"></i> {{Importer}}</a>
    </p>
    <ul id="list_import">
    </ul>
</div>

<script type="text/javascript">
<?php
$i = 0;
echo 'var interfaces = [';
foreach ($ips as $ip) {
    if($i > 0)
    	echo ',';
    echo '\''. $ip . '\'';
    $i++;
}
echo '];';
$i = 0;
echo 'var devices_name = [';
foreach ($eqLogics as $eqLogic) {
    if($i > 0)
    	echo ',';
    echo '\''. $eqLogic->getName() . '\'';
    $i++;
}
echo '];';
?>

var success = false;

function onCloseRefresh() {
    if(success){
        alert('{{Veuillez redémarrer le Démon}}')
        location.reload();
    }
    $('#md_modal').off('dialogclose', onCloseRefresh);
}

$('#md_modal').on('dialogclose', onCloseRefresh);
$('#import_file').on('change', function () {
    try{
        var fileToLoad = document.getElementById("import_file").files[0];
        var fileReader = new FileReader();
        fileReader.onload = function(fileLoadedEvent) 
        {
            try{
                var textFromFileLoaded = fileLoadedEvent.target.result;
                var jfile = JSON.parse(textFromFileLoaded);
                document.getElementById("import_text").value = JSON.stringify(jfile, null, 4);
            }catch (e) {}
        };
	    fileReader.readAsText(fileToLoad, "UTF-8");
    }catch (e) {}
});

$('#bt_import').on('click', function () {
    $("#list_import").empty();
    var data = null;
    try{
        var txt = document.getElementById("import_text").value;
        data = JSON.parse(txt);
    }catch (e) {}
    if(data && data.devices){
        for(var i=0;i<data.devices.length;i++){
            var name = data.devices[i].name;
            var localip = data.devices[i].localip;
            if(!localip){
                $('#list_import').append($("<li style=\"color:red;\">").text(name+' : {{Erreur}}, {{localip invalide}}'));
            }else if (interfaces.indexOf(localip) < 0) {
                $('#list_import').append($("<li style=\"color:red;\">").text(name+' : {{Erreur}}, {{veuillez d\'abord ajouter le boitier}} '+localip));
            }else if (!name) {
                $('#list_import').append($("<li style=\"color:red;\">").text(name+' : {{Erreur}}, {{nom invalide}}'));
            }else if (devices_name.indexOf(name) >= 0) {
                $('#list_import').append($("<li style=\"color:red;\">").text(name+' : {{Erreur}}, {{ce nom existe déjà}}'));
            }else{
                devices_name.push(name);
                configuration = data.devices[i];
                configuration['localip'] = localip;
                configuration['device_type'] = data.devices[i].type;
                configuration['protocol'] = data.devices[i].pid;
                configuration['address'] = data.devices[i].addr;
                jeedom.eqLogic.save({"type":"airsend", "eqLogics":[{"name":name, "configuration":configuration}]})
                $('#list_import').append($("<li style=\"color:green;\">").text(name+' : {{Ajouté}} '));
            }
        }
        success = true;
        $('#div_modal').showAlert({message: '{{Import terminé avec succès}}', level: 'success'});
    }else{
        $('#div_modal').showAlert({message: '{{Fichier invalide !}}', level: 'danger'});
    }
});
</script>

