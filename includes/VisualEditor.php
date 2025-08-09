<?php
/**
 * Provide the category tree related to a given namespace to VisualEditor 
 *
 * @file
 * @ingroup API
 */

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use Wikimedia\ParamValidator\ParamValidator;

class SelectCategoryAPI extends ApiBase {
	public function execute() {
		$params = $this->extractRequestParams();
		$ns = $params['namespace'];
		$selectCategoryNamespaces = $this->getConfig()->get( 'SelectCategoryNamespaces' );

		if ( array_key_exists( $ns, $selectCategoryNamespaces ) && $selectCategoryNamespaces[$ns] ) {
			$tree = SelectCategory::getAllCategories( $ns );
			$cleaned = [];

			foreach ( $tree as $name => $deep ) {
				$cleaned[str_replace( '_', ' ', $name )] = $deep;
			}

			$this->getResult()->addValue( null, $this->getModuleName(), $cleaned );
		}
	}

	/**
	 * @return array[]
	 */
	protected function getAllowedParams() {
		return [
			'namespace' => [
				ParamValidator::PARAM_TYPE => MediaWikiServices::getInstance()->getNamespaceInfo()->getValidNamespaces(),
				ParamValidator::PARAM_DEFAULT => NS_MAIN
			]
		];
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$wgSelectCategoryNamespaces = $skin->getConfig()->get( 'SelectCategoryNamespaces' );
		$wgSelectCategoryEnableSubpages = $skin->getConfig()->get( 'SelectCategoryEnableSubpages' );
		$wgSelectCategoryToplevelAllowed = $skin->getConfig()->get( 'SelectCategoryToplevelAllowed' );

		$ns = $out->getTitle()->getNamespace();
		$isSubpage = $out->getTitle()->isSubpage();

		$wgSelectCategoryOn = array_key_exists( $ns, $wgSelectCategoryNamespaces ) &&
			$wgSelectCategoryNamespaces[$ns] &&
			// @phan-suppress-next-line PhanRedundantCondition
			( !$isSubpage || ( $isSubpage && $wgSelectCategoryEnableSubpages ) );

		$out->addJsConfigVars( 'wgSelectCategoryOn', $wgSelectCategoryOn );

		if ( $wgSelectCategoryOn ) {
			$out->addJsConfigVars( 'wgSelectCategoryToplevelAllowed', $wgSelectCategoryToplevelAllowed );
		}
	}
}
