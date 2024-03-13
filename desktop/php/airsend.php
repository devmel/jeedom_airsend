<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$channels = airsend::getChannelsInformation();

$plugin = plugin::byId('airsend');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

$others = array();
$ips = array();
foreach ($eqLogics as $eqLogic) {
    $deviceType = intval($eqLogic->getConfiguration('device_type'));
    if($deviceType == 0){
        $localip = $eqLogic->getConfiguration('localip');
        if (filter_var($localip, FILTER_VALIDATE_IP)) {
            $ips[] = $localip;
        }
    }else{
        $eq = array();
        $eq['id'] = $eqLogic->getId();
        $eq['name'] = $eqLogic->getName();
        $others[] = $eq;
    }
}
$ips = array_unique($ips);
?>

<div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un template}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity .'"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
}
		    ?>
           </ul>
       </div>
   </div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend><i class="fa fa-cog"></i> {{Gestion}}</legend>
    <div class="eqLogicThumbnailContainer">
        <div class="cursor eqLogicAction" data-action="add" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 140px;margin-left : 10px;" >
            <i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
            <br>
            <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Ajouter}}</span>
        </div>
        <div class="cursor" id="bt_easyimport_airsend" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 140px;margin-left : 10px;" >
            <i class="fa fa-mobile" style="font-size : 6em;color:#94ca02;"></i>
            <br>
            <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Import mobile}}</span>
        </div>
        <div class="cursor" id="bt_import_airsend" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 140px;margin-left : 10px;" >
            <i class="fa fa-file" style="font-size : 6em;color:#94ca02;"></i>
            <br>
            <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Import fichier}}</span>
        </div>
        <div class="cursor" id="bt_purge" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 140px;margin-left : 10px;" >
            <i class="fa fa-trash" style="font-size : 6em;color:red;"></i>
            <br>
            <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:red">{{Purger}}</span>
        </div>
        <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 140px;margin-left : 10px;">
            <i class="fa fa-wrench" style="font-size : 6em;color:#767676;"></i>
            <br>
            <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676">{{Configuration}}</span>
        </div>
    </div>
    
    <legend><i class="fa fa-table"></i>  {{Mes appareils}}</legend>
    <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
    <div class="eqLogicThumbnailContainer">
        <?php
        foreach ($eqLogics as $eqLogic) {
            $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
            echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
            if (file_exists(airsend::getPluginPath() . 'desktop/img/device_' . $eqLogic->getConfiguration('device_type') . '.png')) {
                echo '<img class="lazy" src="plugins/'. airsend::getPluginId() .'/desktop/img/device_' . $eqLogic->getConfiguration('device_type') . '.png" height="95" width="95" />';
            } else {
                echo '<img src="' . $plugin->getPathImgIcon() . '" height="95" width="95" />';
            }
            echo "<br>";
            echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
<a class="btn btn-success eqLogicAction pull-right" data-action="save" title="{{Sauver et/ou Générer les commandes automatiquement}}"><i class="fa fa-check-circle"></i> {{Sauver / Générer}}</a>
  <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
  <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
  </ul>
  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
    <div role="tabpanel" class="tab-pane active" id="eqlogictab">
      <br/>
    <form class="form-horizontal">
        <fieldset>
            <div class="form-group">
                <label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
                <div class="col-sm-3">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                <div class="col-sm-3">
                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                        <option value="">{{Aucun}}</option>
                        <?php
                        foreach (jeeObject::all() as $object) {
                            echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                        }
                        ?>
                   </select>
               </div>
           </div>
	   <div class="form-group">
                <label class="col-sm-3 control-label">{{Catégorie}}</label>
                <div class="col-sm-9">
                 <?php
                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                        echo '<label class="checkbox-inline">';
                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                        echo '</label>';
                    }
                  ?>
               </div>
           </div>
	<div class="form-group">
		<label class="col-sm-3 control-label"></label>
		<div class="col-sm-9">
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" id="isEnable" data-l1key="isEnable" checked/>{{Activer}}</label>
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" id="isVisible" data-l1key="isVisible" checked/>{{Visible}}</label>
		</div>
	</div>
    <div class="form-group">
        <label class="col-sm-3 control-label">Type d'appareil </label>
        <div class="col-sm-3">
            <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device_type">
                <option value="0">{{Boitier AirSend}}</option>
                <option value="4096">{{Télécommande 1-Bouton}}</option>
                <option value="4097">{{Télécommande On-Off}}</option>
                <option value="4098">{{Télécommande Volet roulant}}</option>
                <option value="4099">{{Télécommande Niveau}}</option>
                <option value="1">Capteurs</option>
            </select>
        </div>
        <div class="col-sm-1 asPassword">
            <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="airsend_version" placeholder="airsend_version" />{{AirSend Duo}}</label>
        </div>
    </div>
    <div class="form-group">
        <label class="col-sm-3 control-label">{{AirSend LocalIP}}</label>
        <div class="col-sm-3">
            <input type="text" class="eqLogicAttr form-control" id="localipBase" data-l1key="configuration" data-l2key="localip" placeholder="localip" onChange="autoSelectValue(document.getElementById('localipSelect'),this.value);"/>
            <select class="eqLogicAttr form-control" id="localipSelect" onChange="document.getElementById('localipBase').value = this.value;">
            <option value=""></option>
            <?php
            foreach ($ips as $ip) {
                echo '<option value="'.$ip.'" id="select_device_type">'.$ip.'</option>';
            }
            ?>
            </select>
        </div>
    </div>
    <div class="form-group asPassword">
        <label class="col-sm-3 control-label">{{AirSend Password}}</label>
        <div class="col-sm-3">
            <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dev_passwd" placeholder="dev_passwd" autocomplete="0" />
        </div>
    </div>
    <div class="form-group asPassword">
        <label class="col-sm-3 control-label">{{Passerelle Internet}}</label>
        <div class="col-sm-3">
            <input type="checkbox" style="margin: 5px;" class="eqLogicAttr" data-l1key="configuration" data-l2key="gateway" placeholder="gateway" />
        </div>
    </div>
    <div class="form-group asPassword">
        <label class="col-sm-3 control-label">{{Adresse Secondaire}}</label>
        <div class="col-sm-3">
            <input type="input" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="failover" placeholder="failover" autocomplete="0" />
        </div>
    </div>
    <div class="form-group asPassword">
        <label class="col-sm-3 control-label">{{Ecoute permanente}}</label>
        <div class="col-sm-3">
            <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="listenmode">
                <option value="">{{Non}}</option>
                <option value="1">{{Generique 433Mhz}}</option>
                <?php
                foreach ($channels as $c) {
                    $decoder = $c->id;
                    if(property_exists($c,"getDecoder")){
                        $decoder = intval($c->getDecoder);
                    }
                    if($decoder == $c->id){
                        echo '<option value="'.$c->id.'">';
                        echo $c->name;
                        echo '</option>';
                    }
                }
                ?>
                <option value="-" disabled>--- --- --- --- --- ---</option>
                <option value="-" disabled>&#x2193; {{Decodage partiel}} &#x2193;</option>
                <option value="-" disabled>--- --- --- --- --- ---</option>
                <?php
                foreach ($channels as $c) {
                    $decoder = $c->id;
                    if(property_exists($c,"getDecoder")){
                        $decoder = intval($c->getDecoder);
                    }
                    if($decoder == 0){
                        echo '<option value="'.$c->id.'">';
                        echo $c->name;
                        echo '</option>';
                    }
                }
                ?>
                <option value="-" disabled>--- --- --- --- --- ---</option>
                <option value="-" disabled>&#x2193; {{Inclus dans generique}} &#x2193;</option>
                <option value="-" disabled>--- --- --- --- --- ---</option>
                <?php
                foreach ($channels as $c) {
                    $decoder = $c->id;
                    if(property_exists($c,"getDecoder")){
                        $decoder = intval($c->getDecoder);
                    }
                    if($decoder == 1){
                        echo '<option value="'.$c->id.'">';
                        echo $c->name;
                        echo '</option>';
                    }
                }
                ?>
            </select>
        </div>
    </div>
    <div class="form-group asPassword">
        <label class="col-sm-3 control-label">{{Inclusion automatique}}</label>
        <div class="col-sm-3">
            <input type="checkbox" style="margin: 5px;" class="eqLogicAttr" data-l1key="configuration" data-l2key="autoinclude" placeholder="autoinclude" />
        </div>
    </div>
    <div class="form-group asProtocols">
        <label class="col-sm-3 control-label">{{Protocole}}</label>
        <div class="col-sm-2">
            <select id="protocol_select" onchange="document.getElementById('protocol_id').value = this.value"  class="eqLogicAttr form-control">
                <option value=""></option>
                <?php
                foreach ($channels as $proto) {
                    echo '<option value="'.$proto->id.'">'.$proto->name.'</option>';
                }
                ?>
            </select>
        </div>
        <div class="col-sm-1">
            <input type="text" id="protocol_id" onchange="document.getElementById('protocol_select').value = this.value" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="protocol"/>
        </div>
    </div>
    <div class="form-group asProtocols">
        <label class="col-sm-3 control-label">{{Adresse}}</label>
        <div class="col-sm-3">
            <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="address"/>
        </div>
    </div>
    <div class="form-group asToggle">
        <label class="col-sm-3 control-label">{{Données}}</label>
        <div class="col-sm-3">
            <input type="text" class="eqLogicAttr" data-l1key="configuration" data-l2key="opt" placeholder="" />
        </div>
    </div>
    <div class="form-group asProtocols">
        <label class="col-sm-3 control-label">{{Envoi répété}}</label>
        <div class="col-sm-3">
            <input type="checkbox" style="margin: 5px;" class="eqLogicAttr" data-l1key="configuration" data-l2key="presstype" placeholder="presstype" />
        </div>
    </div>
    <div class="form-group asProtocols">
        <label class="col-sm-3 control-label">{{Propager l'état vers}}</label>
        <div class="col-sm-3">
            <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="forwardstate">
                <option value=""></option>
                <?php
                foreach ($others as $eq) {
                    echo '<option value="'.$eq['id'].'">'.$eq['name'].'</option>';
                }
                ?>
            </select>
        </div>
    </div>
    <div class="form-group asToggle">
        <label class="col-sm-3 control-label">{{Valeur de l'état à propager}}</label>
        <div class="col-sm-3">
        <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="forwardstatevalue">
                <option value="auto">{{Basculer}}</option>
                <option value="0">  0 %</option>
                <option value="50"> 50 %</option>
                <option value="100">100 %</option>
            </select>
        </div>
    </div>
</fieldset>
</form>
</div>
      <div role="tabpanel" class="tab-pane" id="commandtab">
<table id="table_cmd" class="table table-bordered table-condensed">
    <thead>
        <tr>
            <th>{{Nom}}</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>
</div>
</div>

</div>
</div>

<?php include_file('desktop', 'airsend', 'js', 'airsend');?>
<?php include_file('core', 'plugin.template', 'js');?>
