<?php

namespace Mindcrack\Site\Field;

use Bolt\Field\FieldInterface;

class BigIntegerField implements FieldInterface {

	/**
	 * Returns the name of the field.
	 *
	 * @return string The field name
	 */
	public function getName() {
		return 'biginteger';
	}

	/**
	 * Returns the path to the template.
	 *
	 * @return string The template name
	 */
	public function getTemplate() {
		return '@MindcrackSiteFields/_biginteger.twig';
	}

	/**
	 * Returns the storage type.
	 *
	 * @return string A Valid Storage Type
	 */
	public function getStorageType() {
		return 'bigint';
	}

	/**
	 * Returns additional options to be passed to the storage field.
	 *
	 * @return array An array of options
	 */
	public function getStorageOptions() {
		return array();
	}
}