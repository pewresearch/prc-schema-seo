/**
 * Prepend allowlist pragma to the block-editor bundle so pre-commit secret scanning
 * does not false-positive on minified qrcode/webpack output.
 */
const fs = require('fs');
const path = require('path');

const target = path.join(__dirname, '..', 'build', 'block-editor', 'index.js');

const pragma = '// pragma: allowlist secret\n';

if (!fs.existsSync(target)) {
	process.exit(0);
}

let code = fs.readFileSync(target, 'utf8');
if (!code.startsWith(pragma)) {
	code = pragma + code;
}
// Pre-commit secret scan can false-positive; pragma must be valid JS (after ` closes
// the emotion styled template literal), not inside the CSS string.
code = code.replace(
	/\`;function Ve/,
	'`; // pragma: allowlist secret\nfunction Ve'
);
// Cursor secret scan matches a substring on the same physical line as `function Ve…`
// (a very long minified line). A pragma on the previous line does not apply; append
// an end-of-line pragma on that line.
const lines = code.split('\n');
for (let i = 0; i < lines.length; i++) {
	const line = lines[i];
	if (
		line.startsWith('function Ve(') &&
		!line.includes('pragma: allowlist secret')
	) {
		lines[i] = line + ' // pragma: allowlist secret';
	}
}
code = lines.join('\n');
fs.writeFileSync(target, code);
