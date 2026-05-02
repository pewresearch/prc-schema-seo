/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { PanelBody } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { ShortlinkQrDisplay } from './shortlink-qr-display';
import { useShortlinkQr } from './use-shortlink-qr';

/**
 * Short link QR panel — encodes the WP short link for slides and print.
 *
 * @return {import('react').ReactElement | null} Panel body or null when site URL is unavailable.
 */
export default function ShortlinkQr() {
	const {
		canvasRef,
		setIsPanelOpen,
		shortlinkUrl,
		hasSavedPost,
		existingMedia,
		downloadUrl,
		dataUrlError,
		copyDone,
		isUploading,
		uploadError,
		qrImageUrl,
		downloadFilename,
		copyShortlink,
		homeUrl,
	} = useShortlinkQr();

	if (!homeUrl) {
		return null;
	}

	return (
		<PanelBody
			title={__('Short link & QR', 'prc-schema-seo')}
			initialOpen={false}
			onToggle={setIsPanelOpen}
		>
			{!hasSavedPost ? (
				<p
					style={{
						marginTop: 0,
						color: '#757575',
						fontSize: '13px',
					}}
				>
					{__(
						'Save a draft to generate the WordPress short link and QR code.',
						'prc-schema-seo'
					)}
				</p>
			) : (
				<ShortlinkQrDisplay
					canvasRef={canvasRef}
					existingMedia={existingMedia}
					downloadUrl={downloadUrl}
					dataUrlError={dataUrlError}
					isUploading={isUploading}
					uploadError={uploadError}
					copyDone={copyDone}
					shortlinkUrl={shortlinkUrl}
					qrImageUrl={qrImageUrl}
					downloadFilename={downloadFilename}
					copyShortlink={copyShortlink}
				/>
			)}
		</PanelBody>
	);
}
