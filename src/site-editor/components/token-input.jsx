/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useRef, useCallback, useMemo } from '@wordpress/element';
import {
	BaseControl,
	Button,
	Popover,
	__experimentalVStack as VStack,
} from '@wordpress/components';

/**
 * External Dependencies
 */
import styled from '@emotion/styled';

/**
 * Styled Components
 */
const TokenPreview = styled.div`
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	padding: 8px 10px;
	background: #f0f0f0;
	border: 1px solid #ddd;
	border-radius: 4px;
	min-height: 32px;
	line-height: 1.4;
`;

const TokenPreviewText = styled.span`
	color: #1e1e1e;
	font-size: 13px;
	white-space: pre-wrap;
	word-break: break-word;
`;

const TokenPreviewToken = styled.span`
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
	color: white;
	font-size: 12px;
	font-weight: 500;
	border-radius: 12px;
	cursor: pointer;
	transition: all 0.15s ease;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);

	&:hover {
		background: linear-gradient(135deg, #1a5a93 0%, #0e4a75 100%);
		transform: scale(1.02);
	}

	&:focus {
		outline: 2px solid #2271b1;
		outline-offset: 2px;
	}
`;

const InputWrapper = styled.div`
	width: 100%;
`;

const InputField = styled.input`
	width: 100%;
	padding: 8px 10px;
	font-size: 13px;
	line-height: 1.4;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	font-family: inherit;

	&:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: none;
	}
`;

const TextareaField = styled.textarea`
	width: 100%;
	padding: 8px 10px;
	font-size: 13px;
	line-height: 1.4;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	font-family: inherit;
	resize: vertical;
	min-height: 80px;

	&:focus {
		border-color: #2271b1;
		box-shadow: 0 0 0 1px #2271b1;
		outline: none;
	}
`;

const TokenActions = styled.div`
	display: flex;
	gap: 8px;
`;

const PopoverContent = styled.div`
	padding: 12px;
	min-width: 280px;
	max-width: 400px;
`;

const PopoverHeader = styled.div`
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: #757575;
	margin-bottom: 10px;
	padding-bottom: 8px;
	border-bottom: 1px solid #e0e0e0;
`;

const TokenList = styled.div`
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
`;

const TokenChicletButton = styled(Button)`
	&.components-button {
		padding: 4px 10px !important;
		height: auto !important;
		font-size: 12px !important;
		font-weight: 500 !important;
		border-radius: 14px !important;
		background: #f0f0f0 !important;
		border: 1px solid #ddd !important;
		color: #1e1e1e !important;
		transition: all 0.15s ease !important;

		&:hover {
			background: #2271b1 !important;
			border-color: #2271b1 !important;
			color: white !important;
			transform: translateY(-1px);
			box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
		}

		&:focus {
			box-shadow: 0 0 0 2px #fff, 0 0 0 4px #2271b1 !important;
		}
	}
`;

const StyledBaseControl = styled(BaseControl)`
	margin-bottom: 16px;

	.components-base-control__label {
		display: block;
		margin-bottom: 8px;
		font-weight: 500;
	}
`;

/**
 * Token Chiclet component - displays a token as a styled chip/badge.
 *
 * @param {Object}   props         Component props.
 * @param {string}   props.token   The token string to display.
 * @param {Function} props.onClick Callback when the token is clicked.
 * @return {JSX.Element} Token chiclet element.
 */
function TokenChiclet({ token, onClick }) {
	return (
		<TokenChicletButton
			variant="secondary"
			size="small"
			onClick={() => onClick(token)}
			aria-label={`Insert ${token}`}
		>
			{token}
		</TokenChicletButton>
	);
}

/**
 * Parse value into segments of text and tokens.
 *
 * @param {string} value The input value.
 * @return {Array} Array of segments with type 'text' or 'token'.
 */
function parseValueToSegments(value) {
	if (!value) {
		return [];
	}

	const segments = [];
	const tokenRegex = /%[a-z_:]+%/gi;
	let lastIndex = 0;
	let match;

	while ((match = tokenRegex.exec(value)) !== null) {
		// Add text before the token
		if (match.index > lastIndex) {
			segments.push({
				type: 'text',
				value: value.slice(lastIndex, match.index),
			});
		}
		// Add the token
		segments.push({
			type: 'token',
			value: match[0],
		});
		lastIndex = tokenRegex.lastIndex;
	}

	// Add remaining text
	if (lastIndex < value.length) {
		segments.push({
			type: 'text',
			value: value.slice(lastIndex),
		});
	}

	return segments;
}

/**
 * TokenInput - A text input with visual token chiclets and token insertion.
 * Similar to Yoast's SEO variable replacements.
 *
 * @param {Object}   props                 Component props.
 * @param {string}   props.label           The input label.
 * @param {string}   props.help            Help text below the input.
 * @param {string}   props.value           Current input value.
 * @param {Function} props.onChange        Callback when value changes.
 * @param {string[]} props.availableTokens Array of available token strings.
 * @param {boolean}  props.multiline       Whether to use a multiline input.
 * @param {number}   props.rows            Number of rows for multiline input.
 * @return {JSX.Element} TokenInput component.
 */
export default function TokenInput({
	label,
	help,
	value = '',
	onChange,
	availableTokens = [],
	multiline = false,
	rows = 3,
}) {
	const [isTokenPopoverOpen, setIsTokenPopoverOpen] = useState(false);
	const inputRef = useRef(null);
	const buttonRef = useRef(null);

	// Parse value to show visual segments
	const segments = useMemo(() => parseValueToSegments(value), [value]);

	/**
	 * Insert a token at the current cursor position or at the end.
	 *
	 * @param {string} token The token to insert.
	 */
	const handleInsertToken = useCallback(
		(token) => {
			const input = inputRef.current;
			if (input) {
				const start = input.selectionStart || value.length;
				const end = input.selectionEnd || value.length;
				const newValue =
					value.slice(0, start) + token + value.slice(end);
				onChange(newValue);

				// Set cursor position after the inserted token
				setTimeout(() => {
					const newPos = start + token.length;
					input.setSelectionRange(newPos, newPos);
					input.focus();
				}, 0);
			} else {
				onChange(value + token);
			}
			setIsTokenPopoverOpen(false);
		},
		[value, onChange]
	);

	/**
	 * Handle removing a token from the value.
	 *
	 * @param {string} token The token to remove.
	 */
	const handleRemoveToken = useCallback(
		(token) => {
			const newValue = value.replace(token, '');
			onChange(newValue);
		},
		[value, onChange]
	);

	const InputComponent = multiline ? TextareaField : InputField;

	return (
		<StyledBaseControl label={label} help={help}>
			<VStack spacing={2}>
				{/* Visual preview of segments */}
				{segments.length > 0 && (
					<TokenPreview>
						{segments.map((segment, index) => {
							if (segment.type === 'token') {
								return (
									<TokenPreviewToken
										key={index}
										onClick={() =>
											handleRemoveToken(segment.value)
										}
										onKeyDown={(e) => {
											if (
												e.key === 'Enter' ||
												e.key === ' '
											) {
												handleRemoveToken(
													segment.value
												);
											}
										}}
										role="button"
										tabIndex={0}
										title={__(
											'Click to remove',
											'prc-schema-seo'
										)}
									>
										{segment.value}
									</TokenPreviewToken>
								);
							}
							return (
								<TokenPreviewText key={index}>
									{segment.value}
								</TokenPreviewText>
							);
						})}
					</TokenPreview>
				)}

				{/* Input field */}
				<InputWrapper>
					<InputComponent
						ref={inputRef}
						value={value}
						onChange={(e) => onChange(e.target.value)}
						rows={multiline ? rows : undefined}
					/>
				</InputWrapper>

				{/* Token insertion button and popover */}
				{availableTokens.length > 0 && (
					<TokenActions>
						<Button
							ref={buttonRef}
							variant="secondary"
							size="small"
							onClick={() =>
								setIsTokenPopoverOpen(!isTokenPopoverOpen)
							}
							aria-expanded={isTokenPopoverOpen}
						>
							{__('Insert variable', 'prc-schema-seo')}
						</Button>

						{isTokenPopoverOpen && (
							<Popover
								anchor={buttonRef.current}
								placement="bottom-start"
								onClose={() => setIsTokenPopoverOpen(false)}
							>
								<PopoverContent>
									<PopoverHeader>
										{__(
											'Available variables',
											'prc-schema-seo'
										)}
									</PopoverHeader>
									<TokenList>
										{availableTokens.map((token) => (
											<TokenChiclet
												key={token}
												token={token}
												onClick={handleInsertToken}
											/>
										))}
									</TokenList>
								</PopoverContent>
							</Popover>
						)}
					</TokenActions>
				)}
			</VStack>
		</StyledBaseControl>
	);
}
