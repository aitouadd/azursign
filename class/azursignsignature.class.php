<?php
/* Copyright (C) 2026 ITCAMELION SARL AU
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Persistence model for AzurSign signature traces.
 */
class AzursignSignature
{
	/** @var DoliDB */
	public $db;

	public $entity;
	public $fk_propal;
	public $fk_soc;
	public $propal_ref;
	public $signer_name;
	public $signer_ip;
	public $user_agent;
	public $signature_hash;
	public $signature_image;
	public $signed_pdf;
	public $legal_text;
	public $legal_accepted;
	public $date_sign;
	public $fk_user_sign;

	/** @var string */
	public $error = '';

	/** @var array */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Insert a signature trace row.
	 *
	 * @return int < 0 if KO, > 0 inserted row id if OK
	 */
	public function create()
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'azursign_signature (';
		$sql .= 'entity, fk_propal, fk_soc, propal_ref, signer_name, signer_ip, user_agent, signature_hash, signature_image, signed_pdf, legal_text, legal_accepted, date_sign, fk_user_sign, datec';
		$sql .= ') VALUES (';
		$sql .= (int) $this->entity;
		$sql .= ', '.(int) $this->fk_propal;
		$sql .= ', '.(!empty($this->fk_soc) ? (int) $this->fk_soc : 'NULL');
		$sql .= ", '".$this->db->escape((string) $this->propal_ref)."'";
		$sql .= ", '".$this->db->escape((string) $this->signer_name)."'";
		$sql .= ', '.($this->signer_ip !== null ? "'".$this->db->escape((string) $this->signer_ip)."'" : 'NULL');
		$sql .= ', '.($this->user_agent !== null ? "'".$this->db->escape((string) $this->user_agent)."'" : 'NULL');
		$sql .= ", '".$this->db->escape((string) $this->signature_hash)."'";
		$sql .= ", '".$this->db->escape((string) $this->signature_image)."'";
		$sql .= ", '".$this->db->escape((string) $this->signed_pdf)."'";
		$sql .= ', '.($this->legal_text !== null ? "'".$this->db->escape((string) $this->legal_text)."'" : 'NULL');
		$sql .= ', '.(int) $this->legal_accepted;
		$sql .= ", '".$this->db->idate($this->date_sign)."'";
		$sql .= ', '.(!empty($this->fk_user_sign) ? (int) $this->fk_user_sign : 'NULL');
		$sql .= ", '".$this->db->idate(dol_now())."'";
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'azursign_signature');
	}

	/**
	 * Fetch latest signature trace for a proposal.
	 *
	 * @param int $fkPropal Proposal id
	 * @return object|null
	 */
	public function fetchLatestByPropal($fkPropal)
	{
		$sql = 'SELECT rowid, signer_name, signer_ip, signature_image, signed_pdf, date_sign, legal_accepted';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'azursign_signature';
		global $conf;

		$sql .= ' WHERE fk_propal = '.((int) $fkPropal);
		$sql .= ' AND entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY date_sign DESC, rowid DESC';
		$sql .= ' LIMIT 1';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		return $obj ?: null;
	}
}
