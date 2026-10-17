const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		files: [ 'src/script.js' ],
		languageOptions: {
			globals: {
				snapshots: 'readonly',
			},
		},
		rules: {
			'no-var': 'off',
			'no-alert': 'off',
			'@wordpress/no-global-active-element': 'off',
			'@wordpress/valid-sprintf': 'off',
		},
	},
];
