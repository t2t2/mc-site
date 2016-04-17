<?php

namespace Mindcrack\Site\Extensions;


use Bolt\Application;
use Twig_Extension;
use Twig_SimpleFilter;

class AssetVersioningExtension extends Twig_Extension {

	/**
	 * @var string[] Array of manifests
	 */
	protected $manifest = [];

	/**
	 * @param Application $app
	 */
	public function __construct(Application $app) {
		// Load manifest
		$file_path = $app['resources']->getPath('web/manifest.json');
		if (file_exists($file_path)) {
			$this->manifest = json_decode(file_get_contents($file_path), true);
		}
	}

	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName() {
		return 'asset_versioning';
	}

	/**
	 * Available filters
	 *
	 * @return array
	 */
	public function getFilters() {
		return [
			new Twig_SimpleFilter('versioned', [$this, 'getVersionedAsset'])
		];
	}

	/**
	 * @param $asset
	 * @return string
	 */
	public function getVersionedAsset($asset) {
		if (isset($this->manifest[$asset])) {
			return $this->manifest[$asset];
		}
		// Fallback just give the asset as-is
		return $asset;
	}

}