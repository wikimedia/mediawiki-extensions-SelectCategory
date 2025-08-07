<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SelectCategory' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['SelectCategory'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for the SelectCategory extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the SelectCategory extension requires MediaWiki 1.43+' );
}