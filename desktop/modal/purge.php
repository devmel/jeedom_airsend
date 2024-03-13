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
echo "<ul>";
foreach ($eqLogics as $eqLogic) {
    $deviceType = intval($eqLogic->getConfiguration('device_type'));
    if($deviceType != 0){
        if(empty($eqLogic->getObject_id())){
            echo "<li>".$eqLogic->getName()."</li>";
            if(isset($_GET['confirm'])){
                $eqLogic->remove();
            }
        }
    }
}
echo "</ul>";
?>

<div style="display: none;width : 100%" id="div_modal">
</div>
<?php
if(empty($_GET['confirm'])){
?>
<script type="text/javascript">
    var success = false
</script>
<div>
    <p>
        <a class="btn btn-success" id="bt_purge_confirm"><i class="fa fa-check-circle"></i> {{Purger}}</a>
    </p>
</div>
<?php
}else{
?>
<script type="text/javascript">
    var success = true
</script>
<?php
}
?>

<script type="text/javascript">
function onCloseRefresh() {
    $('#md_modal').off('dialogclose', onCloseRefresh);
    if(success){
        location.reload();
    }
}
$('#md_modal').on('dialogclose', onCloseRefresh);

$('#bt_purge_confirm').on('click', function () {
    $('#md_modal').load('index.php?v=d&plugin=airsend&modal=purge&confirm=yes').dialog('open');
});
</script>
