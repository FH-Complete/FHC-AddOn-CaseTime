<?php
/* Copyright (C) 2015 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 */
/**
 * Initialisierung des Addons
 */
require_once('../../../config/cis.config.inc.php');
?>
if(typeof addon =='undefined')
	var addon=Array();

addon.push(
{
	init: function(page, params)
	{
		// Diese Funktion wird nach dem Laden der Seite im CIS aufgerufen
		switch(page)
		{
			case 'cis/private/tools/zeitaufzeichnung.php':

				// Anzeige der Feiertage
				AddonCaseTimeLoadFeiertage(params.uid);

				// Anzeige der Zeitfehler
				AddonCaseTimeLoadErrors(params.uid);

				// Anzeige der Ueberstunden
				AddonCaseTimeLoadZeitsaldo(params.uid);
				break;

			case 'cis/private/profile/urlaubstool.php':
			case 'cis/private/profile/zeitsperre_resturlaub.php':
			case 'cis/private/profile/urlaubsfreigabe.php':
				if (params.uid)
				{
					AddonCaseTimeShowUrlaub(params.uid);
				}
				break;

			default:
				break;
		}
	}
});

/**
 * Urlaubsstand in urlaubstool.php anzeigen
 */
function AddonCaseTimeShowUrlaub(uid)
{
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/urlaub.php?uid='+uid,
		success: function (result)
		{
			var urlaubsanspruch = result.Urlaubsanspruch;
			var resturlaub = result.Resturlaub;
			var aktuellerstand = result.AktuellerStand;

			if(urlaubsanspruch!=0 || aktuellerstand!="0" || aktuellerstand!="0.0")
			{
				var content = AddonCaseTimeFormatUrlaub(urlaubsanspruch, resturlaub, aktuellerstand);
				$('#resturlaub').html(content);
			}
			else
			{
				$('#resturlaub').html('&nbsp;');
				//$('#resturlaub').html('Error Loading Data from CaseTime Server:'+result);
			}
        },
		error: function(){
			$('#resturlaub').html('&nbsp;');
			//alert("Error Casetime Load");
		}
    });
}

/**
 * Formatiert die Ausgabe des Urlaubs
 */
function AddonCaseTimeFormatUrlaub(urlaubsanspruch, resturlaub, aktuellerstand)
{
	var gebuchterurlaub = urlaubsanspruch+resturlaub-aktuellerstand;
	var content = '<table>';
	content+= '<tr><td>Urlaubsanspruch für das laufende Jahr</td><td align="right">'+urlaubsanspruch+' Tage</td></tr>';
	content+='<tr><td>+ Resturlaub Übertrag aus Vorjahr</td><td align="right">'+resturlaub+' Tage</td></tr>';
	content+='<tr><td>- bereits gebuchter Urlaub</td><td align="right">'+gebuchterurlaub+' Tage</td></tr>';
	content+='<tr><td style="border-top: 1px solid black;"><b>Aktueller Stand (noch verfügbar)</b></td>';
	content+='    <td align="right" style="border-top: 1px solid black;">'+aktuellerstand+' Tage</td></tr>';
	content+='</table>';
	return content;
}

/**
 * Anzeige der Feiertage aus CaseTime
 */
function AddonCaseTimeLoadFeiertage(uid)
{
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/feiertage.php?uid='+uid,
		success: function (result)
		{
        	for(i in result)
			{
				var tag = result[i][0];
				var message = result[i][1];
				tagid = 'tag_row_'+tag.split('.').join('_');
				tagid_span = 'tag_'+tag.split('.').join('_');

				// Fehlermeldungen direkt beim betreffenden Tag anzeigen
				if($('#'+tagid).length)
				{
					$('#'+tagid_span).append(' -- Feiertag --');
					var zellen = document.getElementById(tagid).getElementsByTagName("td");
					for (var z=0; z<zellen.length; z++)
						zellen[z].style.backgroundColor = '#eeeeee';
				}
			}
        },
		error: function(){
			alert("Error Casetime Load");
		}
    });
}

/**
 * Anzeige der Fehlermeldungen aus CaseTime
 */
function AddonCaseTimeLoadErrors(uid)
{
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/zeitfehler.php?uid='+uid,
		success: function (result)
		{
        	for(i in result)
			{
				var tag = result[i][0];
				var message = result[i][1];
				tagid = 'tag_'+tag.split('.').join('_');

				// Fehlermeldungen direkt beim betreffenden Tag anzeigen
				if($('#'+tagid).length)
				{
					$('#'+tagid).css('color','red');
					$('#'+tagid).append(' <img src="<?php echo APP_ROOT;?>/skin/images/exclamation.png"> '+tag+' '+message);
				}
				else
				{
					// Wenn der betroffene Tag nicht in der Liste vorhanden ist dann separat anzeigen
					$('#globalmessages').css('color','red');
					//$('#globalmessages').prepend('<img src="<?php echo APP_ROOT;?>/skin/images/exclamation.png">'+tag+' '+message+'<br/>');
				}
			}
        },
		error: function(){
			alert("Error Casetime Load");
		}
    });
}

/**
 * Anzeige der Gutstunden / Minusstunden
 */
function AddonCaseTimeLoadZeitsaldo(uid)
{
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/zeitsaldo.php?uid='+uid,
		success: function (result)
		{
			if (result===false)
			{
				//$('#zeitsaldo').css('margin-left','50px');
				$('#zeitsaldo').html('<span style="color:red">Noch kein Zeitmodell eingetragen!<br>Bitte geben Sie Ihre fiktive Normalarbeitszeit beim Personalservice ab.</span>');
			}
			else
			{
				if(result>=0)
					color='green';
				else
					color='red';
				var DatumAktuell = new Date();
				//var DatumAktuell = new Date(2016,0,11);
				var MonatAktuell = DatumAktuell.getMonth()+1;
				var MonatLetztes = MonatAktuell - 1;
				var JahrAktuell = DatumAktuell.getFullYear();
				var JahrLetztes = DatumAktuell.getFullYear();
				var VorJahr = JahrAktuell -1;
				if (MonatLetztes == 0)
				{
					MonatLetztes = 12;
					JahrLetztes = JahrAktuell - 1;
				}
				var MonatVorLetztes = MonatLetztes -1;
				var JahrVorLetztes = JahrLetztes;
				if (MonatVorLetztes == 0)
				{
					MonatVorLetztes = 12;
					JahrVorLetztes = JahrLetztes -1;
				}


				var zahl = parseFloat(result);
				if (zahl > 0)
					var faktor = 1;
				else
					var faktor = -1;
				zahl = zahl * faktor;
				var std = Math.floor(zahl);
				var min = (zahl-Math.floor(zahl))*60;
				min = Math.round(min);
				var std_anzeigealt = std+'h:'+min+'m';



				$('#zeitsaldo').css('margin-left','50px');
				$('#zeitsaldo').html('Aktueller Zeitsaldo: <span style="color:'+color+'">'+result+'</span> Stunden ('+std_anzeigealt+')');
				$('#monatsliste').css('margin-left','50px');
				moli_str = '<a href="javascript:void(0)" onclick="AddonCaseTimeGenerateMonatsliste('+MonatAktuell+','+JahrAktuell+')">Monatsliste '+MonatAktuell+'.'+JahrAktuell+'</a>';
				moli_str += '<br><a href="javascript:void(0)" onclick="AddonCaseTimeGenerateMonatsliste('+MonatLetztes+','+JahrLetztes+')">Monatsliste '+MonatLetztes+'.'+JahrLetztes+'</a>';
				moli_str += '<br><a href="javascript:void(0)" onclick="AddonCaseTimeGenerateMonatsliste('+MonatVorLetztes+','+JahrVorLetztes+')">Monatsliste '+MonatVorLetztes+'.'+JahrVorLetztes+'</a>';
				moli_dd = 'Monatsliste: <br>';
				moli_dd += '<select name="monat" id="monat">';
				moli_dd += '<option value="1">Jänner</option>';
				moli_dd += '<option value="2">Februar</option>';
				moli_dd += '<option value="3">März</option>';
				moli_dd += '<option value="4">April</option>';
				moli_dd += '<option value="5">Mai</option>';
				moli_dd += '<option value="6">Juni</option>';
				moli_dd += '<option value="7">Juli</option>';
				moli_dd += '<option value="8">August</option>';
				moli_dd += '<option value="9">September</option>';
				moli_dd += '<option value="10">Oktober</option>';
				moli_dd += '<option value="11">November</option>';
				moli_dd += '<option value="12">Dezember</option></select>';
				moli_dd += '<select name="jahr" id="jahr"><option value="'+JahrAktuell+'">'+JahrAktuell+'</option><option value="'+VorJahr+'">'+VorJahr+'</option></select>';
				moli_dd += '<input type="button" onclick="AddonCaseTimeGenerateMonatslisteDD()" value="generieren">';
				$('#monatsliste').html(moli_dd);
				document.getElementById('monat').selectedIndex=MonatAktuell-1;
			}
      },
		error: function(){
			alert("Error Casetime Load");
		}
    });
}

/**
 * Generieren der Monatsliste
 */
function AddonCaseTimeGenerateMonatsliste(monat, jahr)
{
	if (monat<10)
		monat = '0'+monat;

	$('#monatsliste').html('Monatsliste wurde generiert und per Email an Sie geschickt');
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/monatsliste.php?monat='+monat+'&jahr='+jahr,
		success: function (result)
		{
			$('#monatsliste').html(result);
        },
		error: function(){

		}
    });
}

/**
 * Generieren der Monatsliste
 */
function AddonCaseTimeGenerateMonatslisteDD()
{
	monat = document.getElementById('monat').options[document.getElementById('monat').selectedIndex].value;
	jahr = document.getElementById('jahr').options[document.getElementById('jahr').selectedIndex].value;
	if (monat<10)
		monat = '0'+monat;
	$('#monatsliste').html('Monatsliste wird generiert und per Email an Sie geschickt.<br>Falls für den gewählten Monat keine Daten verfügbar sind, wird kein Email verschickt.');
	$.ajax({
		type: "GET",
		dataType: 'json',
		url: '<?php echo APP_ROOT;?>/addons/casetime/vilesci/monatsliste.php?monat='+monat+'&jahr='+jahr,
		success: function (result)
		{
			$('#monatsliste').html(result);
        },
		error: function(){

		}
    });

}
