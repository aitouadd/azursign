<?php
/* Copyright (C) 2026 ITCAMELION SARL AU
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
dol_include_once('/azursign/class/azursignsignature.class.php');

/**
 * Hooks for AzurSign.
 */
class Actionsazursign
{
	/** @var DoliDB */
	public $db;

	/** @var mixed Output buffer for hooks using resPrint mechanism */
	public $resprints;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Check whether the current user may sign proposals.
	 * Admin users are always allowed. Other users need azursign write right.
	 *
	 * @param object $user Current user
	 * @return bool
	 */
	private function userCanSign($user)
	{
		if (!empty($user->admin)) {
			return true;
		}
		return !empty($user->rights->azursign->write);
	}

	/**
	 * Check whether the current user may read azursign data.
	 *
	 * @param object $user Current user
	 * @return bool
	 */
	private function userCanRead($user)
	{
		if (!empty($user->admin)) {
			return true;
		}
		return !empty($user->rights->azursign->read);
	}

	/**
	 * Inject sign button into the document (Fichiers joints) section.
	 * This is in the exact same table row area where azur/cyan/ultimate_propal models are listed.
	 */
	public function formBuilddocOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		// Only handle proposal module part
		if (empty($parameters['modulepart']) || $parameters['modulepart'] !== 'propal') {
			return 0;
		}

		if (!is_object($object) || empty($object->id) || $object->element !== 'propal') {
			return 0;
		}

		if (!$this->userCanRead($user)) {
			return 0;
		}

		$langs->loadLangs(array('azursign@azursign'));

		$isValidated = ((int) $object->statut === (int) Propal::STATUS_VALIDATED
			|| (property_exists($object, 'status') && (int) $object->status === (int) Propal::STATUS_VALIDATED));

		$canSign = $isValidated && $this->userCanSign($user);

		$colspan = !empty($parameters['colspan']) ? (int) $parameters['colspan'] : 5;

		$popupId = 'azursign-popup-'.((int) $object->id);
		$canvasId = 'azursign-canvas-'.((int) $object->id);
		$signerName = trim((string) $user->getFullName($langs));
		if ($signerName === '') {
			$signerName = (string) $user->login;
		}

		$out = '<tr class="oddeven" style="display:none;">';
		$out .= '<td colspan="'.$colspan.'"></td>';
		$out .= '</tr>';
		$out .= '<div id="'.$popupId.'" style="display:none; position:fixed; z-index:5000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.35);">';
		$out .= '<div style="position:relative; width:720px; max-width:95%; margin:70px auto; background:#fff; border:1px solid #d0d0d0; box-shadow:0 10px 22px rgba(0,0,0,.22);">';
		$out .= '<div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #e6e6e6; font-size:18px;">';
		$out .= '<span>Please sign in the box below</span>';
		$out .= '<button type="button" id="'.$popupId.'-close" style="border:0; background:transparent; font-size:32px; line-height:1; cursor:pointer;">&times;</button>';
		$out .= '</div>';
		$out .= '<div style="padding:14px 20px 8px 20px;">';
		$out .= '<canvas id="'.$canvasId.'" width="920" height="360" style="width:100%; height:300px; border:4px solid #22a6df; border-radius:6px; background:#fff;"></canvas>';
		$out .= '</div>';
		$out .= '<div style="display:flex; justify-content:flex-end; gap:10px; padding:12px 20px 20px 20px;">';
		$out .= '<button type="button" id="'.$popupId.'-erase" class="button">Erase</button>';
		$out .= '<button type="button" id="'.$popupId.'-validate" class="button button-edit" style="background:#7b4aa6; border-color:#5a3580; color:#fff;">Validate</button>';
		$out .= '<button type="button" id="'.$popupId.'-cancel" class="button button-cancel">Cancel</button>';
		$out .= '</div>';
		$out .= '</div>';
		$out .= '</div>';

		$out .= '<script>';
		$out .= '(function(){';
		$out .= 'var popup=document.getElementById('.json_encode($popupId).');';
		$out .= 'var canvas=document.getElementById('.json_encode($canvasId).');';
		$out .= 'if(!popup||!canvas){return;}';
		$out .= 'var ctx=canvas.getContext("2d");';
		$out .= 'var drawing=false; var hasDrawn=false; var sourceForm=null;';
		$out .= 'function pos(e){var r=canvas.getBoundingClientRect(); var cx=(e.clientX!==undefined?e.clientX:e.touches[0].clientX); var cy=(e.clientY!==undefined?e.clientY:e.touches[0].clientY); return {x:(cx-r.left)*(canvas.width/r.width), y:(cy-r.top)*(canvas.height/r.height)};}';
		$out .= 'function start(e){e.preventDefault(); drawing=true; var p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y);}';
		$out .= 'function move(e){if(!drawing){return;} e.preventDefault(); var p=pos(e); ctx.lineTo(p.x,p.y); ctx.strokeStyle="#1f2933"; ctx.lineWidth=2.4; ctx.lineCap="round"; ctx.lineJoin="round"; ctx.stroke(); hasDrawn=true;}';
		$out .= 'function end(e){if(!drawing){return;} e.preventDefault(); drawing=false;}';
		$out .= 'canvas.addEventListener("mousedown",start); canvas.addEventListener("mousemove",move); window.addEventListener("mouseup",end);';
		$out .= 'canvas.addEventListener("touchstart",start,{passive:false}); canvas.addEventListener("touchmove",move,{passive:false}); canvas.addEventListener("touchend",end,{passive:false});';
		$out .= 'function closePopup(){popup.style.display="none";}';
		$out .= 'document.getElementById('.json_encode($popupId.'-close').').addEventListener("click",closePopup);';
		$out .= 'document.getElementById('.json_encode($popupId.'-cancel').').addEventListener("click",closePopup);';
		$out .= 'document.getElementById('.json_encode($popupId.'-erase').').addEventListener("click",function(){ctx.clearRect(0,0,canvas.width,canvas.height); hasDrawn=false;});';
		$out .= 'var modelSelect=document.querySelector("select[name=\"model\"]");';
		$out .= 'if(modelSelect){ var exists=false; for(var i=0;i<modelSelect.options.length;i++){ if(modelSelect.options[i].value==="azursign"){ exists=true; break; } } if(!exists){ var o=document.createElement("option"); o.value="azursign"; o.text="azursign"; modelSelect.appendChild(o);} }';
		$out .= 'document.querySelectorAll("form[id$=\"_form\"]").forEach(function(f){ var act=f.querySelector("input[name=\"action\"]"); var mdl=f.querySelector("select[name=\"model\"]"); if(!act||!mdl||act.value!=="builddoc"){ return; } f.addEventListener("submit", function(ev){ if(mdl.value!=="azursign"){ return; } ev.preventDefault(); if('.($canSign ? 'true' : 'false').'){ sourceForm=f; popup.style.display="block"; } else { alert('.json_encode($langs->transnoentities('AzurSignSignNowUnavailable')).'); } }); });';
		$out .= 'document.getElementById('.json_encode($popupId.'-validate').').addEventListener("click", function(){ if(!hasDrawn){ alert("Please draw your signature first."); return; } if(!sourceForm){ return; } var tokenInput=sourceForm.querySelector("input[name=\"token\"]"); var token=tokenInput?tokenInput.value:""; var frm=document.createElement("form"); frm.method="POST"; frm.action='.json_encode(dol_buildpath('/custom/azursign/sign.php?id='.(int) $object->id, 1)).';';
		$out .= 'function add(name,val){ var i=document.createElement("input"); i.type="hidden"; i.name=name; i.value=val; frm.appendChild(i);}';
		$out .= 'add("token", token); add("action", "save"); add("signature_data", canvas.toDataURL("image/png")); add("signer_name", '.json_encode($signerName).'); add("legal_accepted", "1"); add("azursign_popup", "1"); document.body.appendChild(frm); frm.submit(); });';
		$out .= '})();';
		$out .= '</script>';
		$out .= '</td>';
		$out .= '</tr>';

		$this->resprints = $out;
		return 0;
	}

	/**
	 * Do not show any extra action button outside the document module area.
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		return 0;
	}
}
