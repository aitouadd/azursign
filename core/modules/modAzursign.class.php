<?php
/* Copyright (C) 2026 ITCAMELION SARL AU
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for AzurSign.
 */
class modAzursign extends DolibarrModules
{
	/** @var array */
	public $sql = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->numero = 510510;
		$this->rights_class = 'azursign';
		$this->family = 'crm';
		$this->module_position = 620;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Electronic signature workflow for proposals';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'signature';
		$this->editor_name = 'ITCAMELION SARL AU';
		$this->editor_url = 'mailto:Y.AITOUADDI@ITCAMELION.COM';
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(17, 0);

		$this->langfiles = array('azursign@azursign');
		$this->config_page_url = array('setup.php@azursign');

		$this->module_parts = array(
			'hooks' => array('propalcard')
		);

		$this->depends = array('modPropale');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->dirs = array('/azursign');

		$this->const = array(
			array('AZURSIGN_REQUIRE_LEGAL_ACK', 'yesno', '1', 'Require legal disclaimer acknowledgement', 0, 'current', 0),
			array('AZURSIGN_LEGAL_TEXT', 'chaine', 'Je confirme accepter les conditions de cette proposition et signer electroniquement ce document.', 'Legal text displayed before signature', 0, 'current', 0),
			array('AZURSIGN_LOG_IP', 'yesno', '1', 'Store IP address for signature traceability', 0, 'current', 0),
			array('AZURSIGN_SET_PROPOSAL_SIGNED', 'yesno', '1', 'Set proposal status to Signed after AzurSign flow', 0, 'current', 0),
		);

		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.'01';
		$this->rights[$r][1] = 'Read AzurSign information';
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.'02';
		$this->rights[$r][1] = 'Sign proposals with AzurSign';
		$this->rights[$r][4] = 'write';
		$r++;

		$this->sql = array(
			'azursign/sql/llx_azursign_signature.sql',
		);
	}

	/**
	 * Init module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/azursign/sql/');
		if ($result < 0) {
			return $result;
		}

		return $this->_init(array(), $options);
	}

	/**
	 * Remove module.
	 *
	 * @param string $options Options
	 * @return int
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
