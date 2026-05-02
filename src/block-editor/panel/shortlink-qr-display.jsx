/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, ExternalLink, Spinner } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { SHORTLINK_QR_SIZE } from './shortlink-qr-compose';

/**
 * QR preview, status messages, and actions for a saved post.
 *
 * @param {Object}                                       props
 * @param {import('react').RefObject<HTMLCanvasElement>} props.canvasRef
 * @param {Object|null}                                  props.existingMedia
 * @param {string}                                       props.downloadUrl
 * @param {boolean}                                      props.dataUrlError
 * @param {boolean}                                      props.isUploading
 * @param {boolean}                                      props.uploadError
 * @param {boolean}                                      props.copyDone
 * @param {string}                                       props.shortlinkUrl
 * @param {string}                                       props.qrImageUrl
 * @param {string}                                       props.downloadFilename
 * @param {() => void}                                   props.copyShortlink
 * @return {import('react').ReactElement} QR preview, status messages, and action buttons.
 */
export function ShortlinkQrDisplay({
	canvasRef,
	existingMedia,
	downloadUrl,
	dataUrlError,
	isUploading,
	uploadError,
	copyDone,
	shortlinkUrl,
	qrImageUrl,
	downloadFilename,
	copyShortlink,
}) {
	return (
		<>
			<p
				style={{
					marginTop: 0,
					marginBottom: '8px',
					color: '#757575',
					fontSize: '13px',
				}}
			>
				{__(
					'Scan or download for presentations. Uses the same ?p= short link as the admin Shortlink field.',
					'prc-schema-seo'
				)}
			</p>
			<p
				style={{
					wordBreak: 'break-all',
					fontSize: '12px',
					marginBottom: '12px',
				}}
			>
				<ExternalLink href={shortlinkUrl}>{shortlinkUrl}</ExternalLink>
			</p>
			<div
				style={{
					display: 'flex',
					flexDirection: 'column',
					alignItems: 'center',
					gap: '12px',
					marginBottom: '12px',
				}}
			>
				{existingMedia?.source_url ? (
					<img
						src={existingMedia.source_url}
						alt={__('QR code for this post', 'prc-schema-seo')}
						width={SHORTLINK_QR_SIZE}
						height={SHORTLINK_QR_SIZE}
						style={{ display: 'block' }}
					/>
				) : (
					<>
						<canvas
							ref={canvasRef}
							width={SHORTLINK_QR_SIZE}
							height={SHORTLINK_QR_SIZE}
						/>
						{!downloadUrl && !dataUrlError && <Spinner />}
					</>
				)}
				{dataUrlError ? (
					<p
						style={{
							margin: 0,
							color: '#757575',
							fontSize: '12px',
							textAlign: 'center',
						}}
					>
						{__(
							'QR download could not be prepared in this environment.',
							'prc-schema-seo'
						)}
					</p>
				) : null}
				{uploadError ? (
					<p
						style={{
							margin: 0,
							color: '#cc1818',
							fontSize: '12px',
							textAlign: 'center',
						}}
					>
						{__(
							'QR code could not be saved to the media library.',
							'prc-schema-seo'
						)}
					</p>
				) : null}
				{isUploading ? (
					<p
						style={{
							margin: 0,
							color: '#757575',
							fontSize: '12px',
							textAlign: 'center',
						}}
					>
						{__('Saving to media library…', 'prc-schema-seo')}
					</p>
				) : null}
			</div>
			<div
				style={{
					display: 'flex',
					flexWrap: 'wrap',
					gap: '8px',
				}}
			>
				<Button
					variant="secondary"
					onClick={copyShortlink}
					disabled={!shortlinkUrl}
				>
					{copyDone
						? __('Copied!', 'prc-schema-seo')
						: __('Copy short link', 'prc-schema-seo')}
				</Button>
				{qrImageUrl ? (
					<Button
						variant="secondary"
						href={qrImageUrl}
						download={downloadFilename}
					>
						{__('Download QR (PNG)', 'prc-schema-seo')}
					</Button>
				) : null}
			</div>
		</>
	);
}
