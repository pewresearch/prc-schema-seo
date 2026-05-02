/**
 * WordPress Dependencies
 */
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import {
	useLayoutEffect,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies
 */
import { renderShortlinkQrWithLogo } from './shortlink-qr-compose';

/**
 * Build the default WordPress short link (?p=) matching `wp_get_shortlink()`.
 *
 * @param {string} homeUrl Site home URL from PHP `home_url()`.
 * @param {number} postId  Post ID.
 * @return {string} Short link URL or empty string when inputs are missing.
 */
function getWpShortlinkUrl(homeUrl, postId) {
	if (!homeUrl || !postId) {
		return '';
	}
	const base = homeUrl.replace(/\/$/, '');
	return `${base}/?p=${postId}`;
}

/**
 * State and side effects for the Short link & QR panel.
 *
 * On first open, renders the QR to a canvas and automatically uploads the
 * result to the WordPress media library. The attachment ID is stored as post
 * meta (`_prc_seo_qr_attachment_id`). On subsequent opens the saved image is
 * displayed directly without re-rendering the canvas.
 *
 * @return {Object} Hook API for ShortlinkQrDisplay.
 */
export function useShortlinkQr() {
	const canvasRef = useRef(null);
	/** Synced with PanelBody via onToggle — canvas only mounts when the panel is open. */
	const [isPanelOpen, setIsPanelOpen] = useState(false);
	/** Local canvas data URL — used for download before the attachment is saved. */
	const [downloadUrl, setDownloadUrl] = useState('');
	const [dataUrlError, setDataUrlError] = useState(false);
	const [copyDone, setCopyDone] = useState(false);
	const [isUploading, setIsUploading] = useState(false);
	const [uploadError, setUploadError] = useState(false);

	const homeUrl =
		typeof window !== 'undefined' && window.PRCSchemaSEO?.homeUrl
			? window.PRCSchemaSEO.homeUrl
			: '';

	const symbolSvgUrl =
		typeof window !== 'undefined' && window.PRCSchemaSEO?.symbolSvgUrl
			? window.PRCSchemaSEO.symbolSvgUrl
			: '';

	const { postId, status, postType } = useSelect((select) => {
		const editor = select('core/editor');
		return {
			postId: editor.getCurrentPostId(),
			status: editor.getCurrentPostAttribute('status'),
			postType: editor.getCurrentPostType(),
		};
	}, []);

	// Read/write the QR attachment ID from the registered post meta field.
	const [meta, setMeta] = useEntityProp('postType', postType, 'meta');
	const qrAttachmentId = meta?._prc_seo_qr_attachment_id || 0;

	// Fetch the media object when an attachment ID already exists.
	const existingMedia = useSelect(
		(select) => {
			if (!qrAttachmentId) {
				return null;
			}
			return select('core').getMedia(qrAttachmentId) || null;
		},
		[qrAttachmentId]
	);

	const shortlinkUrl = getWpShortlinkUrl(homeUrl, postId);
	const hasSavedPost = postId > 0 && status && status !== 'auto-draft';

	// Render the QR canvas only when no saved attachment exists yet.
	useLayoutEffect(() => {
		if (
			!shortlinkUrl ||
			!hasSavedPost ||
			!isPanelOpen ||
			qrAttachmentId > 0
		) {
			setDownloadUrl('');
			setDataUrlError(false);
			return;
		}

		const canvas = canvasRef.current;
		if (!canvas) {
			return;
		}

		let cancelled = false;
		setDataUrlError(false);
		setDownloadUrl('');

		(async () => {
			try {
				await renderShortlinkQrWithLogo(
					canvas,
					shortlinkUrl,
					symbolSvgUrl
				);
				if (cancelled) {
					return;
				}
				setDownloadUrl(canvas.toDataURL('image/png'));
			} catch {
				if (!cancelled) {
					setDataUrlError(true);
				}
			}
		})();

		return () => {
			cancelled = true;
		};
	}, [shortlinkUrl, hasSavedPost, symbolSvgUrl, isPanelOpen, qrAttachmentId]);

	// Auto-upload to media library after the canvas has been rendered.
	useEffect(() => {
		if (!downloadUrl || qrAttachmentId > 0 || isUploading || !postId) {
			return;
		}

		setIsUploading(true);
		setUploadError(false);

		apiFetch({
			path: `/prc-schema-seo/v1/qr-attachment/${postId}`,
			method: 'POST',
			data: { image_data: downloadUrl },
		})
			.then(({ attachment_id: attachmentId }) => {
				setMeta({ ...meta, _prc_seo_qr_attachment_id: attachmentId });
			})
			.catch(() => {
				setUploadError(true);
			})
			.finally(() => {
				setIsUploading(false);
			});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [downloadUrl]);

	const copyShortlink = async () => {
		if (!shortlinkUrl || !hasSavedPost) {
			return;
		}
		try {
			const nav =
				typeof window !== 'undefined' ? window.navigator : undefined;
			if (
				nav &&
				nav.clipboard &&
				typeof nav.clipboard.writeText === 'function'
			) {
				await nav.clipboard.writeText(shortlinkUrl);
				setCopyDone(true);
				window.setTimeout(() => setCopyDone(false), 2000);
			}
		} catch {
			// Clipboard API can fail without a secure context; ignore.
		}
	};

	/** URL to use for the PNG download button. */
	const qrImageUrl =
		existingMedia?.source_url ||
		(isUploading || uploadError ? '' : downloadUrl);

	/** Filename for browser-download of the PNG. */
	const downloadFilename = `qr-shortlink-${postId}.png`;

	return {
		canvasRef,
		isPanelOpen,
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
	};
}
