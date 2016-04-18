<?php

namespace Mindcrack\Site\Field;

use Silex\Application;
use Bolt\Field\FieldInterface;

class TimeZoneField implements FieldInterface {

	/**
	 * Returns the name of the field.
	 *
	 * @return string The field name
	 */
	public function getName() {
		return 'timezone';
	}

	/**
	 * Returns the path to the template.
	 *
	 * @return string The template name
	 */
	public function getTemplate() {
		return '@MindcrackSiteFields/_timezone.twig';
	}

	/**
	 * Returns the storage type.
	 *
	 * @return string A Valid Storage Type
	 */
	public function getStorageType() {
		return 'text';
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
?>