if ( mw.config.get( 'wgSelectCategoryOn' ) ) {

	mw.hook( 've.activate' ).add( function () {
		// Get Category tree by API
		new mw.Api().get( {
			action: 'selectcategory',
			namespace: mw.config.get( 'wgNamespaceNumber' )
		} ).done( function ( data ) {
			mw.config.set( 'SelectCategory_Tree', data[ 'selectcategory' ] );
		} );
	} );

	mw.hook( 've.activationComplete' ).add( function () {
		// Extend MWCategoriesPage setup() function
		ve.ui.MWCategoriesPage.prototype.setup2 = ve.ui.MWCategoriesPage.prototype.setup;
		ve.ui.MWCategoriesPage.prototype.setup = function ( metaList ) {
			this.SelectCategory = new OO.ui.FieldsetLayout( {
				label: mw.msg( 'selectcategory-title' ),
				icon: 'tag'
			} );

			this.freeze = false;

			// Insert the checkboxes based on API result
			$.each( mw.config.get( 'SelectCategory_Tree' ), function( k, v ) {
				var fieldlayout = new OO.ui.FieldLayout(
					new OO.ui.CheckboxInputWidget( {
						value: k,
						disabled: ( v === 0 && !mw.config.get( 'wgSelectCategoryToplevelAllowed' ) )
					} ).on( 'change', function ( value ) {
						if ( this.freeze ) {
							return;
						}
						this.freeze = true;
						if ( value ) {
							this.onNewCategory( this.categoryWidget.getCategoryItemFromValue( k ), null );
						} else {
							this.categoryWidget.onRemoveCategory( k );
						}
						this.freeze = false;
					}.bind( this ) ),
					{ label: k, align: 'inline' }
				);
				if ( v > 0 ) {
					fieldlayout.$element.css( 'padding-left', ( 1.42857 * v ).toString() + 'em' );
				}
				this.SelectCategory.addItems( [ fieldlayout ] );
			}.bind( this ) );

			this.$element.prepend( this.SelectCategory.$element );

			// Execute original setup() instructions and restore them
			this.setup2( metaList );
			ve.ui.MWCategoriesPage.prototype.setup = ve.ui.MWCategoriesPage.prototype.setup2;

			// At each opening of MWMetaDialog, check the checkboxes with current categories
			this.changer = function ( groupe ) {
				var items = [];
				$.each( groupe, function ( i, c ) {
					if ( c.label in mw.config.get( 'SelectCategory_Tree' ) ) {
						items.push( c.label );
					}
				} );
				this.freeze = true;
				$.each( this.SelectCategory.items, function ( j, d ) {
					d.getField().setSelected( this.indexOf( d.label ) >= 0 );
				}.bind( items ) );
				this.freeze = false;
			}.bind( this );

			this.categoryWidget.connect( this, { change: 'changer' } );

		};
	} );
}
