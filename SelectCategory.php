<?php

# Setup and Hooks for the SelectCategory extension, an extension of the
# edit box of MediaWiki to provide an easy way to add category links
# to a specific page.

# @package MediaWiki
# @subpackage Extensions
# @author Leon Weber <leon.weber@leonweber.de> & Manuel Schneider <manuel.schneider@wikimedia.ch>
# @copyright © 2006 by Leon Weber & Manuel Schneider
# @licence GNU General Public Licence 2.0 or later

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die();
}

## Options:
# $wgSelectCategoryNamespaces	- list of namespaces in which this extension should be active
$wgSelectCategoryNamespaces	= array(
	NS_MEDIA		=> true,
	NS_MAIN			=> true,
	NS_TALK			=> false,
	NS_USER			=> false,
	NS_USER_TALK		=> false,
	NS_PROJECT		=> true,
	NS_PROJECT_TALK		=> false,
	NS_IMAGE		=> true,
	NS_IMAGE_TALK		=> false,
	NS_MEDIAWIKI		=> false,
	NS_MEDIAWIKI_TALK	=> false,
	NS_TEMPLATE		=> false,
	NS_TEMPLATE_TALK	=> false,
	NS_HELP			=> true,
	NS_HELP_TALK		=> false,
	NS_CATEGORY		=> true,
	NS_CATEGORY_TALK	=> false
);
# $wgSelectCategoryRoot		- root category to use, otherwise self detection (expensive)
$wgSelectCategoryRoot = false;

## Register extension setup hook and credits:
$wgExtensionFunctions[]	= 'fnSelectCategory';
$wgExtensionCredits['parserhook'][] = array(
	'name'		=> 'SelectCategory',
	'author'	=> 'Leon Weber & Manuel Schneider',
	'url'		=> 'http://www.mediawiki.org/wiki/SelectCategory',
	'description'	=> 'Allows the user to select from existing categories when editing a page'
);

## Set Hook:
function fnSelectCategory() {
	global $wgHooks;
	
	# Hook when starting editing:
	$wgHooks['EditPage::showEditForm:initial'][] = 'fnSelectCategoryEditHook';
	# Hook when saving page:
	$wgHooks['ArticleSave'][] = 'fnSelectCategorySaveHook';
	# Hook for the upload page:
	$wgHooks['UploadForm:initial'][] = 'fnSelectCategoryUploadHook';
	# Hook when saving the upload:
	$wgHooks['UploadForm:BeforeProcessing'][] = 'fnSelectCategoryUplSaveHook';
	# Hook our own CSS:
	$wgHooks['OutputPageParserOutput'][] = 'fnSelectCategoryOutputHook';
	# Hook up local messages:
	$wgHooks['LoadAllMessages'][] = 'fnSelectCategoryMessageHook';
}

## Load the file containing the hook functions:
require_once( 'SelectCategoryFunctions.php' );
?>