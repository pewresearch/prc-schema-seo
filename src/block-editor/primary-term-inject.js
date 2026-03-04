/* eslint-disable */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';

// Taxonomies eligible for primary term selection (localized or defaults to category).
const getEligibleTaxonomies = () => ( window.PRCSchemaSEO && window.PRCSchemaSEO.primaryTermTaxonomies ) || [ 'category' ];

const withPrimaryTermControl = createHigherOrderComponent( ( OriginalComponent ) => {
	return function PrimaryTermEnhanced( props ) {
		const { slug } = props; // taxonomy slug provided by editor.PostTaxonomyType filter context
		const enabled = getEligibleTaxonomies().includes( slug );
		const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
		const post = useSelect( ( select ) => select( 'core/editor' ).getCurrentPost(), [] );
		const [ seoData, setSeoData ] = useEntityProp( 'postType', postType, 'prc_seo_data' );

		// Assigned term IDs for this taxonomy
		const termIds = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			const attr = editor.getEditedPostAttribute( slug === 'category' ? 'categories' : slug );
			return Array.isArray( attr ) ? attr : [];
		}, [ slug, post?.id ] );

		// Fetch term records
		const terms = useSelect( ( select ) => {
			const core = select( 'core' );
			return termIds
				.map( ( id ) => core.getEntityRecord( 'taxonomy', slug, id ) )
				.filter( ( t ) => !! t );
		}, [ termIds.join( ',' ), slug ] );

		const primaryMap = seoData?.primary_terms || {};
		// Fall back to first term ID if no explicit primary is set.
		const primaryId = primaryMap[ slug ] || ( terms.length > 0 ? terms[0].id : null );
		const currentPrimary = primaryId ? String( primaryId ) : '';

		const updatePrimary = ( value ) => {
			const newMap = { ...primaryMap, [ slug ]: value ? parseInt( value, 10 ) : 0 };
			setSeoData( { ...( seoData || {} ), primary_terms: newMap } );
		};

		return (
			<>
				<OriginalComponent { ...props } />
				{ enabled && terms.length > 0 && (
					<div className="prc-primary-term-control">
						<SelectControl
							label={ slug === 'category' ? __( 'Primary Category', 'prc-schema-seo' ) : sprintf( __( 'Primary %s', 'prc-schema-seo' ), decodeEntities( slug ) ) }
							value={ currentPrimary }
							onChange={ ( v ) => updatePrimary( v ) }
							options={ [ { label: __( 'None', 'prc-schema-seo' ), value: '' }, ...terms.map( ( t ) => ( { label: decodeEntities( t.name ), value: String( t.id ) } ) ) ] }
						/>
					</div>
				) }
			</>
		);
	};
}, 'withPrimaryTermControl' );

export default function registerPrimaryTermInject() {
	addFilter( 'editor.PostTaxonomyType', 'prc/schema-seo/primary-term', withPrimaryTermControl );
}
