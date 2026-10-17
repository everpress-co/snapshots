/**
 * Build static GitHub Pages output into _site/.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.resolve( __dirname, '..' );
const docsDir = path.join( root, 'docs' );
const siteDir = path.join( root, '_site' );
const readmePath = path.join( root, 'README.md' );
const wpOrgDir = path.join( root, '.wordpress-org' );

const CHANGELOG_START = '<!-- changelog -->';
const CHANGELOG_END = '<!-- /changelog -->';
const CHANGELOG_HEADING = '## Changelog';

/**
 * @param {string} markdown Full README contents.
 * @return {string} Changelog body without the section heading.
 */
function extractChangelogMarkdown( markdown ) {
	const start = markdown.indexOf( CHANGELOG_HEADING );
	if ( start === -1 ) {
		throw new Error( `Missing ${ CHANGELOG_HEADING } in README.md` );
	}

	let body = markdown.slice( start + CHANGELOG_HEADING.length );
	const nextH2 = body.search( /\n## / );
	if ( nextH2 !== -1 ) {
		body = body.slice( 0, nextH2 );
	}

	return body.trim();
}

/**
 * Minimal markdown → HTML for changelog blocks only.
 *
 * @param {string} md Changelog markdown.
 * @return {string} Changelog HTML fragment.
 */
function changelogMarkdownToHtml( md ) {
	const lines = md.split( '\n' );
	const out = [];
	let inList = false;

	const closeList = () => {
		if ( inList ) {
			out.push( '</ul>' );
			inList = false;
		}
	};

	const inline = ( text ) =>
		text
			.replace( /`([^`]+)`/g, '<code>$1</code>' )
			.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );

	for ( const line of lines ) {
		const trimmed = line.trimEnd();

		if ( trimmed === '' ) {
			closeList();
			continue;
		}

		if ( trimmed.startsWith( '### ' ) ) {
			closeList();
			out.push( `<h3>${ inline( trimmed.slice( 4 ) ) }</h3>` );
			continue;
		}

		if ( trimmed.startsWith( '- ' ) ) {
			if ( ! inList ) {
				out.push( '<ul>' );
				inList = true;
			}
			out.push( `<li>${ inline( trimmed.slice( 2 ) ) }</li>` );
			continue;
		}

		closeList();
		out.push( `<p>${ inline( trimmed ) }</p>` );
	}

	closeList();
	return out.join( '\n' );
}

/**
 * Insert README changelog HTML between the stable markers.
 *
 * The published site is the docs folder, so the filled block is written back
 * to docs/index.html as well as the deploy output.
 *
 * @param {string} html          Homepage HTML.
 * @param {string} changelogHtml Changelog fragment.
 * @return {string} Homepage HTML with the changelog filled in.
 */
function applyChangelog( html, changelogHtml ) {
	const block = `${ CHANGELOG_START }\n${ changelogHtml }\n${ CHANGELOG_END }`;
	const region = new RegExp(
		`${ escapeRegExp( CHANGELOG_START ) }[\\s\\S]*?${ escapeRegExp( CHANGELOG_END ) }`
	);

	if ( region.test( html ) ) {
		return html.replace( region, block );
	}

	if ( ! html.includes( CHANGELOG_START ) ) {
		throw new Error( `Missing ${ CHANGELOG_START } in docs/index.html` );
	}

	return html.replace( CHANGELOG_START, block );
}

/**
 * @param {string} value Literal text.
 * @return {string} RegExp-safe text.
 */
function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

function copyFile( src, dest ) {
	fs.mkdirSync( path.dirname( dest ), { recursive: true } );
	fs.copyFileSync( src, dest );
}

fs.rmSync( siteDir, { recursive: true, force: true } );
fs.mkdirSync( siteDir, { recursive: true } );

const readme = fs.readFileSync( readmePath, 'utf8' );
const changelogHtml = changelogMarkdownToHtml(
	extractChangelogMarkdown( readme )
);

const indexPath = path.join( docsDir, 'index.html' );
let indexHtml = fs.readFileSync( indexPath, 'utf8' );
indexHtml = applyChangelog( indexHtml, changelogHtml );

fs.writeFileSync( path.join( siteDir, 'index.html' ), indexHtml );
fs.writeFileSync( indexPath, indexHtml );
copyFile(
	path.join( docsDir, 'guide.html' ),
	path.join( siteDir, 'guide.html' )
);
copyFile(
	path.join( docsDir, 'style.css' ),
	path.join( siteDir, 'style.css' )
);
copyFile(
	path.join( docsDir, 'agents.md' ),
	path.join( siteDir, 'agents.md' )
);
copyFile( path.join( docsDir, 'llms.txt' ), path.join( siteDir, 'llms.txt' ) );
copyFile( path.join( docsDir, 'CNAME' ), path.join( siteDir, 'CNAME' ) );

const iconSrc = path.join( wpOrgDir, 'icon-256x256.png' );
const iconSvgSrc = path.join( wpOrgDir, 'icon.svg' );
const screenshotSrc = path.join( wpOrgDir, 'screenshot-1.png' );

for ( const assetsDir of [
	path.join( siteDir, 'assets' ),
	path.join( docsDir, 'assets' ),
] ) {
	fs.mkdirSync( assetsDir, { recursive: true } );
	copyFile( iconSrc, path.join( assetsDir, 'icon.png' ) );
	copyFile( iconSvgSrc, path.join( assetsDir, 'icon.svg' ) );
	copyFile( screenshotSrc, path.join( assetsDir, 'screenshot-1.png' ) );
}
