/**
 * External Dependencies
 */
import QRCode from 'qrcode';

/**
 * Canvas size in CSS pixels (preview + download).
 */
export const SHORTLINK_QR_SIZE = 200;

/**
 * Center logo diameter as a fraction of canvas width (quiet zone around modules).
 */
export const SHORTLINK_QR_LOGO_DIAMETER_FRACTION = 0.25;

/**
 * White plate behind the logo — slightly larger than the logo for separation from QR modules.
 */
const PLATE_RADIUS_FACTOR = 1.12;

export const SHORTLINK_QR_CODE_OPTIONS = {
	width: SHORTLINK_QR_SIZE,
	margin: 2,
	color: { dark: '#1a1a2e', light: '#ffffff' },
	errorCorrectionLevel: 'H',
};

/**
 * @param {string} src Image URL (same-origin SVG from `plugins_url()`).
 * @return {Promise<HTMLImageElement>} Resolves when the image has loaded.
 */
export function loadImage(src) {
	return new Promise((resolve, reject) => {
		const img = document.createElement('img');
		img.onload = () => resolve(img);
		img.onerror = () => reject(new Error('Image load failed'));
		img.src = src;
	});
}

/**
 * Draw QR code on canvas, then overlay centered brand mark (Jetpack-style).
 * If the logo fails to load, redraws QR only so the code stays scannable.
 *
 * @param {HTMLCanvasElement} canvas  Target canvas.
 * @param {string}            text    URL to encode.
 * @param {string}            logoSrc URL for the center image (SVG/PNG).
 * @return {Promise<void>}
 */
export async function renderShortlinkQrWithLogo(canvas, text, logoSrc) {
	await QRCode.toCanvas(canvas, text, SHORTLINK_QR_CODE_OPTIONS);

	if (!logoSrc) {
		return;
	}

	try {
		const img = await loadImage(logoSrc);
		const ctx = canvas.getContext('2d');
		if (!ctx) {
			return;
		}
		const w = canvas.width;
		const h = canvas.height;
		const logoDiameter = w * SHORTLINK_QR_LOGO_DIAMETER_FRACTION;
		const cx = w / 2;
		const cy = h / 2;
		const logoR = logoDiameter / 2;
		const plateR = logoR * PLATE_RADIUS_FACTOR;

		ctx.save();
		ctx.fillStyle = '#ffffff';
		ctx.beginPath();
		ctx.arc(cx, cy, plateR, 0, Math.PI * 2);
		ctx.fill();
		ctx.restore();

		ctx.save();
		ctx.beginPath();
		ctx.arc(cx, cy, logoR, 0, Math.PI * 2);
		ctx.clip();
		ctx.drawImage(img, cx - logoR, cy - logoR, logoDiameter, logoDiameter);
		ctx.restore();
	} catch {
		await QRCode.toCanvas(canvas, text, SHORTLINK_QR_CODE_OPTIONS);
	}
}
