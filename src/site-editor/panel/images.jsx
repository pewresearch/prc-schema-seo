/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, PanelBody } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';

/**
 * ImageUploadControl - reusable image upload control with preview.
 * @param {{label: string, help?: string, imageId: number, onChange: (id: number) => void}} props
 */
function ImageUploadControl({ label, help, imageId, onChange }) {
	const media = useSelect(
		(select) => {
			if (!imageId) return null;
			return select('core').getMedia(imageId);
		},
		[imageId]
	);

	return (
		<div className="prc-schema-seo-image-upload">
			<MediaUploadCheck>
				<MediaUpload
					onSelect={(image) => onChange(image.id)}
					allowedTypes={['image']}
					value={imageId}
					render={({ open }) => (
						<>
							<div style={{ marginBottom: '8px' }}>
								<strong>{label}</strong>
								{help && (
									<p
										style={{
											fontSize: '12px',
											color: '#757575',
										}}
									>
										{help}
									</p>
								)}
							</div>
							{media && media.source_url && (
								<div style={{ marginBottom: '8px' }}>
									<img
										src={media.source_url}
										alt={media.alt_text || ''}
										style={{
											maxWidth: '100%',
											height: 'auto',
											border: '1px solid #ddd',
										}}
									/>
								</div>
							)}
							<div style={{ display: 'flex', gap: '8px' }}>
								<Button variant="secondary" onClick={open}>
									{imageId
										? __('Change Image', 'prc-schema-seo')
										: __('Select Image', 'prc-schema-seo')}
								</Button>
								{imageId && (
									<Button
										isDestructive
										onClick={() => onChange(0)}
									>
										{__('Remove', 'prc-schema-seo')}
									</Button>
								)}
							</div>
						</>
					)}
				/>
			</MediaUploadCheck>
		</div>
	);
}

export default function ImagePanel({ templateData, update }) {
	return (
		<PanelBody
			title={__('Default Images', 'prc-schema-seo')}
			initialOpen={false}
		>
			<ImageUploadControl
				label={__('Open Graph Image', 'prc-schema-seo')}
				help={__(
					'Default OG image for this template when posts have no featured image.',
					'prc-schema-seo'
				)}
				imageId={templateData.og_image}
				onChange={(v) => update('og_image', v)}
			/>
			<div style={{ marginTop: '16px' }} />
			<ImageUploadControl
				label={__('Twitter Card Image', 'prc-schema-seo')}
				help={__(
					'Default Twitter image for this template. If empty, uses OG image.',
					'prc-schema-seo'
				)}
				imageId={templateData.twitter_image}
				onChange={(v) => update('twitter_image', v)}
			/>
		</PanelBody>
	);
}
