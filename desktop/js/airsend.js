
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

$('#bt_import_airsend').on('click', function () {
    $('#md_modal').dialog({title: "{{Importer}}"});
    $('#md_modal').load('index.php?v=d&plugin=airsend&modal=import').dialog('open');
});

$('#bt_easyimport_airsend').on('click', function () {
    $('#md_modal').dialog({title: "{{Importer}}"});
    $('#md_modal').load('index.php?v=d&plugin=airsend&modal=easyimport').dialog('open');
});

$('#bt_purge').on('click', function () {
    $('#md_modal').dialog({title: "{{Purge}}"});
    $('#md_modal').load('index.php?v=d&plugin=airsend&modal=purge').dialog('open');
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=device_type]').on('change',function(){
    $('.asToggle').hide();
	if ($(this).value()=='0'){
		$('.asPassword').show();
		$('.asProtocols').hide();
		$('#localipBase').show();
		$('#localipSelect').hide();
	}else{
		$('.asPassword').hide();
		$('.asProtocols').show();
		$('#localipBase').hide();
		$('#localipSelect').show();
	}
    if($(this).value()=='4096'){
		$('.asToggle').show();
    }
});

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});


function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
    tr += '</td>';
    tr += '<td>';
    tr += '<p>'+init(_cmd.type)+'</p>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
    }
    if (_cmd.type == "action") {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
}

function changeVersion(value) {
    var version = $('#localipSelect option[value="' + value + '"]').data('version');
    if(version !== undefined){
        var ch = channels;
        if(version != 2){
            ch = channels_v1;
        }
        if(ch !== undefined){
            var $el = $("#protocol_select");
            $el.empty();
            $.each(ch, function(key,value) {
                $el.append($("<option></option>").attr("value", value).text(key));
            });
            if($("#protocol_id").val() > 0){
                $('#protocol_select option[value=' + $("#protocol_id").val() + ']').attr('selected',true);
            }
        }
    }
}

function autoSelectValue(sel, value) {
    changeVersion(value);
    for ( var i = 0, len = sel.options.length; i < len; i++ ) {
        opt = sel.options[i];
        if ( opt.value === value ) {
            opt.selected = true;
        }
    }
}

