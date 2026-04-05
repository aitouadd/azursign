<?php
/* Copyright (C) 2026 ITCAMELION SARL AU
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

dol_include_once('/azursign/class/azursignsignature.class.php');

/**
 * Find best source PDF path for a proposal.
 *
 * @param Propal $object Proposal
 * @param Conf $conf Dolibarr conf
 * @return string
 */
function azursign_get_source_pdf_path($object, $conf)
{
	if (!empty($object->last_main_doc)) {
		$mainPath = DOL_DATA_ROOT.'/'.$object->last_main_doc;
		if (is_file($mainPath)) {
			return $mainPath;
		}
	}

	$dir = $conf->propal->multidir_output[$object->entity].'/'.$object->ref;
	$files = dol_dir_list($dir, 'files', 0, '\\.pdf$', '', 'date', SORT_DESC);
	if (!empty($files)) {
		foreach ($files as $entry) {
			if (strpos($entry['name'], 'SIGNED-') !== 0) {
				return $entry['fullname'];
			}
		}
		return $files[0]['fullname'];
	}

	return '';
}

/**
 * Build a signed PDF by stamping signature and metadata on the last page.
 *
 * @param string $sourcePdf Source PDF absolute path
 * @param string $targetPdf Target PDF absolute path
 * @param string $signaturePng Signature image absolute path
 * @param array $meta Signature metadata
 * @return int
 */
function azursign_build_signed_pdf($sourcePdf, $targetPdf, $signaturePng, array $meta)
{
	$pdf = pdf_getInstance('P');
	if (!$pdf) {
		return -1;
	}

	if (method_exists($pdf, 'setPrintHeader')) {
		$pdf->setPrintHeader(false);
	}
	if (method_exists($pdf, 'setPrintFooter')) {
		$pdf->setPrintFooter(false);
	}
	if (method_exists($pdf, 'SetMargins')) {
		$pdf->SetMargins(0, 0, 0);
	}

	$pageCount = $pdf->setSourceFile($sourcePdf);

	for ($p = 1; $p <= $pageCount; $p++) {
		$tplId = method_exists($pdf, 'importPage') ? $pdf->importPage($p) : $pdf->ImportPage($p);
		$tplSize = method_exists($pdf, 'getTemplateSize') ? $pdf->getTemplateSize($tplId) : $pdf->getTemplatesize($tplId);

		$w = isset($tplSize['width']) ? $tplSize['width'] : $tplSize['w'];
		$h = isset($tplSize['height']) ? $tplSize['height'] : $tplSize['h'];
		$orientation = ($w > $h) ? 'L' : 'P';

		$pdf->AddPage($orientation, array($w, $h));
		$pdf->useTemplate($tplId, 0, 0, $w, $h, true);

		if ($p === $pageCount) {
			// Compact signature block in the bottom-right reserved area.
			$boxWidth = min(95, $w * 0.40);
			$boxHeight = 22;
			$boxX = $w - $boxWidth - 18;
			$boxY = $h - 53;

			$sigWidth = min(46, $boxWidth * 0.52);
			$sigHeight = $boxHeight - 6;

			$pdf->Image($signaturePng, $boxX + 2, $boxY + 3, $sigWidth, $sigHeight, 'PNG');

			$pdf->SetTextColor(36, 36, 36);
			$pdf->SetFont('helvetica', '', 6.8);
			$pdf->SetXY($boxX + $sigWidth + 4, $boxY + 4);
			$pdf->Cell($boxWidth - $sigWidth - 6, 3.4, 'Signer: '.(string) $meta['name'], 0, 1);
			$pdf->SetX($boxX + $sigWidth + 4);
			$pdf->Cell($boxWidth - $sigWidth - 6, 3.4, 'Date: '.(string) $meta['date'], 0, 1);
		}
	}

	$pdf->Output($targetPdf, 'F');
	if (!is_file($targetPdf)) {
		return -2;
	}

	dolChmod($targetPdf);
	return 1;
}

$langs->loadLangs(array('propal', 'azursign@azursign'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

if (empty($user->rights->azursign->write)) {
	accessforbidden();
}

$object = new Propal($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}

if ((int) $object->statut !== (int) Propal::STATUS_VALIDATED) {
	setEventMessages($langs->trans('AzurSignSignNowUnavailable'), null, 'warnings');
	header('Location: '.DOL_URL_ROOT.'/comm/propal/card.php?id='.(int) $object->id);
	exit;
}

$form = new Form($db);

$legalText = trim((string) getDolGlobalString('AZURSIGN_LEGAL_TEXT'));
$requireLegalAck = (int) getDolGlobalInt('AZURSIGN_REQUIRE_LEGAL_ACK', 1);
$logIp = (int) getDolGlobalInt('AZURSIGN_LOG_IP', 1);
$setSignedStatus = (int) getDolGlobalInt('AZURSIGN_SET_PROPOSAL_SIGNED', 1);

if ($action === 'save' && GETPOST('token', 'alpha') === currentToken()) {
	$signerName = trim(GETPOST('signer_name', 'alphanohtml'));
	$signatureData = trim((string) GETPOST('signature_data', 'none'));
	$legalAccepted = GETPOSTINT('legal_accepted') ? 1 : 0;
	$fromPopup = GETPOSTINT('azursign_popup') ? 1 : 0;
	$ip = $logIp ? (string) getUserRemoteIP(1) : '';
	$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 250) : '';

	$errors = array();

	if ($signerName === '') {
		$signerName = trim((string) $user->getFullName($langs));
		if ($signerName === '') {
			$signerName = (string) $user->login;
		}
	}
	if ($signerName === '') {
		$errors[] = $langs->trans('AzurSignSignerNameRequired');
	}
	if ($signatureData === '') {
		$errors[] = $langs->trans('AzurSignSignatureRequired');
	}
	if ($fromPopup && $requireLegalAck && $legalText !== '' && !$legalAccepted) {
		// Popup flow can send legal_accepted=1 automatically when policy requires acknowledgment.
		$legalAccepted = 1;
	}
	if ($requireLegalAck && $legalText !== '' && !$legalAccepted) {
		$errors[] = $langs->trans('AzurSignLegalAckRequired');
	}

	$binarySignature = '';
	if ($signatureData !== '') {
		if (preg_match('/^data:image\\/png;base64,/', $signatureData)) {
			$binarySignature = base64_decode(substr($signatureData, strlen('data:image/png;base64,')), true);
		}
		if (empty($binarySignature)) {
			$errors[] = $langs->trans('AzurSignSignatureRequired');
		}
	}

	if (empty($errors)) {
		$propalDir = $conf->propal->multidir_output[$object->entity].'/'.$object->ref;
		if (!is_dir($propalDir)) {
			dol_mkdir($propalDir);
		}

		$signatureDir = DOL_DATA_ROOT.'/azursign/temp';
		if (!is_dir($signatureDir)) {
			dol_mkdir($signatureDir);
		}

		$ts = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
		$signatureName = 'azursign-signature-'.$object->id.'-'.$ts.'.png';
		$signaturePath = $signatureDir.'/'.$signatureName;
		$signatureRelative = 'temp/'.$signatureName;

		if (file_put_contents($signaturePath, $binarySignature) === false) {
			$errors[] = $langs->trans('AzurSignSaveSignatureFailed');
		} else {
			dolChmod($signaturePath);
		}

		$model = !empty($object->model_pdf) ? $object->model_pdf : getDolGlobalString('PROPALE_ADDON_PDF');
		if ($model === '') {
			$model = 'azur';
		}

		$resGenerate = $object->generateDocument($model, $langs, 0, 0, 0);
		if ($resGenerate <= 0) {
			$errors[] = !empty($object->error) ? $object->error : $langs->trans('AzurSignSourcePdfMissing');
		}

		$object->fetch($object->id);
		$sourcePdf = azursign_get_source_pdf_path($object, $conf);
		if ($sourcePdf === '' || !is_file($sourcePdf)) {
			$errors[] = $langs->trans('AzurSignSourcePdfMissing');
		}

		$hash = hash('sha256', $binarySignature.'|'.$object->id.'|'.$signerName.'|'.dol_now());
		$signedPdfName = 'SIGNED-'.$object->ref.'-'.$ts.'.pdf';
		$signedPdfPath = $propalDir.'/'.$signedPdfName;
		$signedPdfRelative = $object->ref.'/'.$signedPdfName;

		// Keep only one signed PDF visible in proposal documents list.
		$existingSignedPdfs = dol_dir_list($propalDir, 'files', 0, '^SIGNED-'.preg_quote($object->ref, '/').'.*\\.pdf$');
		if (!empty($existingSignedPdfs)) {
			foreach ($existingSignedPdfs as $existingSignedPdf) {
				if (!empty($existingSignedPdf['fullname']) && is_file($existingSignedPdf['fullname'])) {
					@unlink($existingSignedPdf['fullname']);
				}
			}
		}

		if (empty($errors)) {
			$build = azursign_build_signed_pdf($sourcePdf, $signedPdfPath, $signaturePath, array(
				'name' => $signerName,
				'date' => dol_print_date(dol_now(), 'dayhour'),
				'ip' => ($ip !== '' ? $ip : '-'),
				'hash' => substr($hash, 0, 16),
			));
			if ($build <= 0) {
				$errors[] = $langs->trans('AzurSignTechnicalError');
			}
		}

		if (empty($errors)) {
			$trace = new AzursignSignature($db);
			$trace->entity = (int) $conf->entity;
			$trace->fk_propal = (int) $object->id;
			$trace->fk_soc = (int) $object->socid;
			$trace->propal_ref = (string) $object->ref;
			$trace->signer_name = $signerName;
			$trace->signer_ip = ($ip !== '' ? $ip : null);
			$trace->user_agent = ($userAgent !== '' ? $userAgent : null);
			$trace->signature_hash = $hash;
			$trace->signature_image = $signatureRelative;
			$trace->signed_pdf = $signedPdfRelative;
			$trace->legal_text = ($legalText !== '' ? $legalText : null);
			$trace->legal_accepted = $legalAccepted;
			$trace->date_sign = dol_now();
			$trace->fk_user_sign = (int) $user->id;
			$resCreate = $trace->create();

			if ($resCreate <= 0) {
				$errors[] = $langs->trans('AzurSignSignDbFailed');
			}
		}

		// Signature image is only an internal artifact; keep proposal documents list clean.
		if (!empty($signaturePath) && is_file($signaturePath)) {
			@unlink($signaturePath);
		}

		if (empty($errors) && $setSignedStatus && (int) $object->statut === (int) Propal::STATUS_VALIDATED) {
			$resClose = $object->closeProposal($user, Propal::STATUS_SIGNED, $langs->trans('AzurSignPrivateNote'), 0);
			if ($resClose < 0) {
				setEventMessages($object->error, $object->errors, 'warnings');
			}
		}
	}

	if (!empty($errors)) {
		setEventMessages(null, $errors, 'errors');
	} else {
		setEventMessages($langs->trans('AzurSignSignSuccess'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/comm/propal/card.php?id='.(int) $object->id);
		exit;
	}
}

$title = $langs->trans('AzurSignPageTitle');
llxHeader('', $title);

print load_fiche_titre($title);
print '<div class="fichecenter">';
print '<div class="tabsAction">';
print '<a class="butAction" href="'.DOL_URL_ROOT.'/comm/propal/card.php?id='.(int) $object->id.'">'.$langs->trans('AzurSignBackToProposal').'</a>';
print '</div>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'" id="azursign-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="signature_data" id="signature_data" value="">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AzurSignPageTitle').' - '.$object->ref.'</td></tr>';

print '<tr class="oddeven">';
print '<td class="titlefield">'.$langs->trans('AzurSignSignerName').'</td>';
print '<td><input type="text" class="minwidth300" name="signer_name" id="signer_name" value="'.dol_escape_htmltag(GETPOST('signer_name', 'alphanohtml')).'" required></td>';
print '</tr>';

if ($legalText !== '') {
	print '<tr class="oddeven">';
	print '<td class="titlefield">'.$langs->trans('AzurSignLegalText').'</td>';
	print '<td><div style="padding:8px 10px; background:#f7f7f7; border:1px solid #ddd; border-radius:4px;">'.nl2br(dol_escape_htmltag($legalText)).'</div>';
	if ($requireLegalAck) {
		print '<div style="margin-top:8px;"><label><input type="checkbox" name="legal_accepted" id="legal_accepted" value="1"'.(GETPOSTINT('legal_accepted') ? ' checked' : '').'> '.$langs->trans('AzurSignLegalAckLabel').'</label></div>';
	}
	print '</td>';
	print '</tr>';
}

print '<tr class="oddeven">';
print '<td class="titlefield">'.$langs->trans('AzurSignDrawHere').'</td>';
print '<td>';
print '<canvas id="azursign-canvas" width="760" height="220" style="width:100%; max-width:760px; border:2px dashed #546e7a; border-radius:6px; background:#fff;"></canvas>';
print '<div style="margin-top:8px;">';
print '<button type="button" class="button" id="azursign-clear">'.$langs->trans('AzurSignClear').'</button> ';
print '<button type="submit" class="button button-edit" id="azursign-submit">'.$langs->trans('AzurSignSubmit').'</button>';
print '</div>';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';
print '</div>';

print '<script>
(function () {
	var canvas = document.getElementById("azursign-canvas");
	var ctx = canvas.getContext("2d");
	var clearBtn = document.getElementById("azursign-clear");
	var form = document.getElementById("azursign-form");
	var signatureInput = document.getElementById("signature_data");
	var hasDrawn = false;
	var drawing = false;

	function getPos(e) {
		var rect = canvas.getBoundingClientRect();
		var cx = (e.clientX !== undefined ? e.clientX : e.touches[0].clientX);
		var cy = (e.clientY !== undefined ? e.clientY : e.touches[0].clientY);
		return {
			x: (cx - rect.left) * (canvas.width / rect.width),
			y: (cy - rect.top) * (canvas.height / rect.height)
		};
	}

	function start(e) {
		e.preventDefault();
		drawing = true;
		var p = getPos(e);
		ctx.beginPath();
		ctx.moveTo(p.x, p.y);
	}

	function move(e) {
		if (!drawing) return;
		e.preventDefault();
		var p = getPos(e);
		ctx.lineTo(p.x, p.y);
		ctx.strokeStyle = "#1f2933";
		ctx.lineWidth = 2;
		ctx.lineCap = "round";
		ctx.lineJoin = "round";
		ctx.stroke();
		hasDrawn = true;
	}

	function end(e) {
		if (!drawing) return;
		e.preventDefault();
		drawing = false;
	}

	canvas.addEventListener("mousedown", start);
	canvas.addEventListener("mousemove", move);
	window.addEventListener("mouseup", end);
	canvas.addEventListener("touchstart", start, { passive: false });
	canvas.addEventListener("touchmove", move, { passive: false });
	canvas.addEventListener("touchend", end, { passive: false });

	clearBtn.addEventListener("click", function () {
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		hasDrawn = false;
		signatureInput.value = "";
	});

	form.addEventListener("submit", function (e) {
		if (!hasDrawn) {
			e.preventDefault();
			alert('.json_encode($langs->transnoentities('AzurSignSignatureRequired')).');
			return;
		}
		signatureInput.value = canvas.toDataURL("image/png");
	});
})();
</script>';

llxFooter();
$db->close();
