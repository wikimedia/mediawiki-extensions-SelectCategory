<?php

/**
 * Implementation of the SelectCategory extension, an extension of the
 * edit box of MediaWiki to provide an easy way to add category links
 * to a specific page.
 *
 * @file
 * @ingroup Extensions
 * @author Leon Weber <leon@leonweber.de> & Manuel Schneider <manuel.schneider@wikimedia.ch> & Christian Boltz <mediawiki+SelectCategory@cboltz.de> & Daniel Centore <Daniel.Centore@gmail.com>
 * @copyright © 2006 by Leon Weber & Manuel Schneider
 * @copyright © 2013 by Christian Boltz
 * @copyright © 2021 by Daniel Centore
 * @licence GNU General Public Licence 2.0 or later
 */

use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Specials\SpecialUpload;
use MediaWiki\Title\Title;

class SelectCategory {

	/**
	 * @param EditPage $editPage
	 * @param OutputPage $output
	 */
	public static function onEditPage__showEditForm_initial( EditPage $editPage, OutputPage $output ) {
		self::showHook( false, $editPage );
	}

	/**
	 * @param SpecialUpload $uploadFormObj
	 */
	public static function onUploadForm_initial( SpecialUpload $uploadFormObj ) {
		self::showHook( true, $uploadFormObj );
	}

	/**
	 * @param EditPage $editPage
	 */
	public static function onEditPage__attemptSave( EditPage $editPage ) {
		self::saveHook( false, $editPage );
	}

	/**
	 * @param SpecialUpload $uploadFormObj
	 * @return bool
	 */
	public static function onUploadForm_BeforeProcessing( SpecialUpload $uploadFormObj ) {
		self::saveHook( true, $uploadFormObj );
		// Per the hook documentation (but really, consider using a different hook for this altogether)
		return true;
	}

	/**
	 * @param bool|false $isUpload
	 * @param EditPage|SpecialUpload $pageObj
	 * @return bool
	 */
	public static function showHook( $isUpload, $pageObj ) {
		# check if we should do anything or sleep
		if ( self::checkConditions( $isUpload, $pageObj ) ) {
			global $wgSelectCategoryMaxLevel;
			global $wgSelectCategoryToplevelAllowed;
			global $wgSelectCategoryChosenMode;

			$moduleName = $wgSelectCategoryChosenMode ? 'ext.SelectCategory.chosen' : 'ext.SelectCategory';
			# Output the necessary CSS and JS
			# @todo FIXME: the CSS should probably be split up to a separate module from the JS
			$pageObj->getOutput()->addModules( $moduleName );

			# Get all categories from wiki
			$allCats = self::getAllCategories( $isUpload ? NS_FILE : $pageObj->getTitle()->getNamespace() );

			# Get the right member variables, depending on if we're on an upload form or not
			if ( !$isUpload ) {
				# Extract all categorylinks from page
				$pageCats = self::getPageCategories( $pageObj );

				# Never ever use editFormTextTop here as it resides outside
				# the <form> so we will never get contents
				$place = 'editFormTextAfterWarn';
				# Print the localised title for the select box
				$textBefore = '<b>' . wfMessage( 'selectcategory-title' )->escaped() . '</b>:';
			} else {
				# No need to get categories
				$pageCats = [];

				# Place output at the right place
				$place = 'uploadFormTextAfterSummary';
				# Print the part of the table including the localised title for the select box
				$textBefore = "\n</td></tr><tr><td><label for='wpSelectCategory'>" .
					wfMessage( 'selectcategory-title' )->escaped() . ":</label></td><td align='left'>";
			}

			# Introduce the output
			$pageObj->$place .= "<!-- SelectCategory begin -->\n";
			# Print the select box
			$pageObj->$place .= "\n$textBefore";

			# Begin list output, use <div> to enable custom formatting
			$level = 0;
			$olddepth = -1;

			if ( !$wgSelectCategoryChosenMode ) {
				$pageObj->$place .= '<ul id="SelectCategoryList">';
			} else {
				$pageObj->$place .= '<select data-placeholder="' .
					wfMessage( 'selectcategory-placeholder' )->escaped() .
					'" name="SelectCategoryList[]" class="category-select" multiple="multiple" id="SelectCategoryList">';
			}

			# LinkRenderer object
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

			foreach ( $allCats as $cat => $depth ) {
				$checked = false;

				# See if the category was already added, so check it
				if ( isset( $pageCats[$cat] ) ) {
					$checked = true;
				}

				# Clean HTML Output
				$category = htmlspecialchars( $cat );

				# default for root category - otherwise it will always be closed
				$open = " class='open' ";

				# iterate through levels and adjust divs accordingly
				while ( $level < $depth ) {
					# Collapse subcategories after reaching the configured MaxLevel
					if ( $level >= ( $wgSelectCategoryMaxLevel - 1 ) ) {
						$class = 'display:none;';
						$open = " class='closed' ";
					} else {
						$class = 'display:block;';
						$open = " class='open' ";
					}

					if ( !$wgSelectCategoryChosenMode ) {
						$pageObj->$place .= '<ul style="' . $class . '">' . "\n";
					}
					$level++;
				}

				if ( $depth <= $olddepth && !$wgSelectCategoryChosenMode ) {
					$pageObj->$place .= '</li>' . "\n";
				}

				while ( $level > $depth ) {
					if ( !$wgSelectCategoryChosenMode ) {
						$pageObj->$place .= '</ul></li>' . "\n";
					}
					$level--;
				}

				# Clean names for text output
				$catName = str_replace( '_', ' ', $category );
				$title = Title::newFromText( $category, NS_CATEGORY );

				# Output the actual checkboxes, indented
				$pageObj->$place .= '<li' . $open . '>';
				if ( $level > 0 || $wgSelectCategoryToplevelAllowed ) {
					if ( !$wgSelectCategoryChosenMode ) {
						$pageObj->$place .= '<input type="checkbox" name="SelectCategoryList[]" value="' . $category
							. '" class="checkbox" ' . ( $checked ?: 'checked=checked' ) . ' />';
					} else {
						# Checking for !$isUpload because we do NOT want to have _all_ the categories chosen
						# by default on Special:Upload!
						if ( !$isUpload ) {
							$pageObj->$place .= '<option value="' . $category . '" ' . ( $checked ?: 'selected' ) . '>' . $catName . '</option>';
						} else {
							# $catName instead of $category because $category contains underscores,
							# not spaces, whereas $catName is the "pretty-printed" version, and that's also
							# the version we want to insert into the page
							$pageObj->$place .= '<option value="' . $catName . '">' . $catName . '</option>';
						}
					}
				}

				$pageObj->$place .= $linkRenderer->makeLink( $title, $catName ) . "\n";

				# set id for next level
				$level_id = 'sc_' . $cat;

				$olddepth = $depth;
			} # End walking through cats (foreach)

			# End of list output - close all remaining divs
			while ( $level > -1 ) {
				if ( !$wgSelectCategoryChosenMode ) {
					$pageObj->$place .= '</li></ul>' . "\n";
				} else {
					$pageObj->$place .= '</li></select><br />' . "\n";
				}
				$level--;
			}

			# Print localised help string
			$pageObj->$place .= "<!-- SelectCategory end -->\n";
		}

		# Return true to let the rest work
		return true;
	}

	/**
	 * Entry point for the hook and main function for saving the page
	 *
	 * @param bool $isUpload
	 * @param EditPage|SpecialUpload $pageObj
	 */
	public static function saveHook( $isUpload, $pageObj ) {
		# check if we should do anything or sleep
		if ( self::checkConditions( $isUpload, $pageObj ) ) {
			# Get localised namespace string
			$catString = MediaWikiServices::getInstance()->getContentLanguage()->getNsText( NS_CATEGORY );

			# Get some distance from the rest of the content
			$text = "\n";

			# Iterate through all selected category entries
			if ( array_key_exists( 'SelectCategoryList', $_POST ) ) {
				foreach ( $_POST['SelectCategoryList'] as $cat ) {
					$text .= "\n[[$catString:$cat]]";
				}
			}

			# If it is an upload we have to call a different method
			if ( $isUpload ) {
				$pageObj->mComment .= $text;
			} else {
				$pageObj->textbox1 .= $text;
			}
		}

		# Return to the let MediaWiki do the rest of the work
		return true;
	}

	/**
	 * Get all categories from the wiki - starting with a given root or otherwise detect root
	 * automagically (expensive).
	 *
	 * Returns an array like this:
	 * array (
	 *   'Name' => (int) Depth,
	 *   ...
	 * )
	 *
	 * @param int $namespace Namespace number or constant like NS_FILE
	 * @return array
	 */
	public static function getAllCategories( $namespace ) {
		global $wgSelectCategoryRoot;

		# Get current namespace (save duplicate call of method)
		if (
			$namespace >= 0 &&
			array_key_exists( $namespace, $wgSelectCategoryRoot ) &&
			$wgSelectCategoryRoot[$namespace]
		) {
			# Include root and step into the recursion
			$allCats = array_merge(
				array( $wgSelectCategoryRoot[$namespace] => 0 ),
				self::getChildren( $wgSelectCategoryRoot[$namespace] )
			);
		} else {
			# Initialize return value
			$allCats = [];

			# Get a database object
			$dbObj = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

			# Get table names to access them in SQL query
			$tblCatLink = $dbObj->tableName( 'categorylinks' );
			$tblPage = $dbObj->tableName( 'page' );

			# Automagically detect root categories
			$sql = "SELECT tmpSelectCat1.cl_to AS title
FROM $tblCatLink AS tmpSelectCat1
LEFT JOIN $tblPage AS tmpSelectCatPage ON (tmpSelectCat1.cl_to = tmpSelectCatPage.page_title AND tmpSelectCatPage.page_namespace = 14)
LEFT JOIN $tblCatLink AS tmpSelectCat2 ON tmpSelectCatPage.page_id = tmpSelectCat2.cl_from
WHERE tmpSelectCat2.cl_from IS NULL GROUP BY tmpSelectCat1.cl_to";

			# Run the query
			$res = $dbObj->query( $sql, __METHOD__ );

			# Process the resulting rows
			foreach ( $res as $row ) {
				$allCats += array( $row->title => 0 );
				$allCats += self::getChildren( $row->title );
			}
		}

		# Afterwards return the array to the caller
		return $allCats;
	}

	/**
	 * @param string $root
	 * @param int $depth
	 * @return array|int[]
	 */
	public static function getChildren( $root, $depth = 1 ) {
		# Initialize return value
		$allCats = [];

		# Get a database object
		$dbObj = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA );

		# Get table names to access them in SQL query
		$tblCatLink = $dbObj->tableName( 'categorylinks' );
		$tblPage = $dbObj->tableName( 'page' );

		# The normal query to get all children of a given root category
		$sql = 'SELECT tmpSelectCatPage.page_title AS title
FROM ' . $tblCatLink . ' AS tmpSelectCat
LEFT JOIN ' . $tblPage . ' AS tmpSelectCatPage
  ON tmpSelectCat.cl_from = tmpSelectCatPage.page_id
WHERE tmpSelectCat.cl_to LIKE ' . $dbObj->addQuotes( $root ) . '
  AND tmpSelectCatPage.page_namespace = 14
ORDER BY tmpSelectCatPage.page_title ASC;';

		# Run the query
		$res = $dbObj->query( $sql, __METHOD__ );

		# Process the resulting rows
		foreach ( $res as $row ) {
			# Survive category link loops
			if ( $root == $row->title ) {
				continue;
			}

			# Add current entry to array
			$allCats += array( $row->title => $depth );
			$allCats += self::getChildren( $row->title, $depth + 1 );
		}

		# Afterwards return the array to the upper recursion level
		return $allCats;
	}

	/**
	 * Returns an array with the categories the articles is in.
	 * Also removes them from the text the user views in the editbox.
	 *
	 * @param EditPage $pageObj
	 * @return array
	 */
	public static function getPageCategories( $pageObj ) {
		if ( array_key_exists( 'SelectCategoryList', $_POST ) ) {
			# We have already extracted the categories, return them instead
			# of extracting zero categories from the page text.
			$catLinks = [];
			foreach ( $_POST['SelectCategoryList'] as $cat ) {
				$catLinks[$cat] = true;
			}
			return $catLinks;
		}

		# Get page contents
		$pageText = $pageObj->textbox1;

		# Get localised namespace string
		$catString = strtolower( MediaWikiServices::getInstance()->getContentLanguage()->getNsText( NS_CATEGORY ) );

		# The regular expression to find the category links
		$pattern = "\[\[({$catString}|category):([^\|\]]*)(\|{{PAGENAME}}|)\]\]";
		$replace = "$2";

		# The container to store all found category links
		$catLinks = [];

		# The container to store the processed text
		$cleanText = '';

		# Check linewise for category links
		foreach ( explode( "\n", $pageText ) as $textLine ) {
			# Filter line through pattern and store the result
			$cleanText .= preg_replace( "/{$pattern}/i", "", $textLine ) . "\n";

			# Check if we have found a category, else proceed with next line
			if ( !preg_match( "/{$pattern}/i", $textLine) ) {
				continue;
			}

			# Get the category link from the original text and store it in our list
			$catLinks[str_replace( ' ', '_', preg_replace( "/.*{$pattern}/i", $replace, $textLine ) )] = true;
		}

		# Place the cleaned text into the text box
		$pageObj->textbox1 = trim( $cleanText );

		# Return the list of categories as an array
		return $catLinks;
	}

	/**
	 * Function that checks if we meet the run conditions of the extension
	 *
	 * @param bool $isUpload
	 * @param EditPage|SpecialUpload $pageObj
	 * @return bool
	 */
	public static function checkConditions( $isUpload, $pageObj ) {
		global $wgSelectCategoryNamespaces, $wgSelectCategoryEnableSubpages;
		global $wgSelectCategoryEnableOnEditPage, $wgSelectCategoryEnableOnUploadForm;

		if ( $pageObj instanceof EditPage && !$wgSelectCategoryEnableOnEditPage ) {
			return false;
		}

		if ( $pageObj instanceof SpecialUpload && !$wgSelectCategoryEnableOnUploadForm ) {
			return false;
		}

		# Run only if we are in an upload, an activated namespace or if page is
		# a subpage and subpages are enabled (unfortunately we can't use
		# implication in PHP) but not if we do a sectionedit
		if ( $isUpload == true ) {
			return true;
		}

		$ns = $pageObj->getTitle()->getNamespace();
		if ( array_key_exists( $ns, $wgSelectCategoryNamespaces ) ) {
			$enabledForNamespace = $wgSelectCategoryNamespaces[$ns];
		} else {
			$enabledForNamespace = false;
		}

		# Check if page is subpage once to save method calls below
		$isSubpage = $pageObj->getTitle()->isSubpage();

		if (
			$enabledForNamespace &&
			( !$isSubpage || $isSubpage && $wgSelectCategoryEnableSubpages ) &&
			$pageObj->section == false
		) {
			return true;
		}
	}

}
