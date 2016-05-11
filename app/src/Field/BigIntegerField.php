<?php

namespace Mindcrack\Site\Field;

use Bolt\Storage\EntityManager;
use Bolt\Storage\Field\Type\FieldTypeBase;
use Bolt\Storage\QuerySet;

class BigIntegerField extends FieldTypeBase {
	public function persist(QuerySet $queries, $entity, EntityManager $em = null) {
		$key = $this->mapping['fieldname'];
		$qb = $queries->getPrimary();
		$value = $entity->get($key);

		$qb->setValue($key, ':' . $key);
		$qb->set($key, ':' . $key);
		$qb->setParameter($key, $value);
	}

	public function hydrate($data, $entity) {
		$key = $this->mapping['fieldname'];

		$value = isset($data[$key]) ? $data[$key] : null;
		if ($value !== null) {
			$this->set($entity, $value);
		}
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'biginteger';
	}

	/**
	 * @return string
	 */
	public function getStorageType() {
		return 'bigint';
	}

	/**
	 * @return array
	 */
	public function getStorageOptions() {
		return [
			'default' => 0
		];
	}
}