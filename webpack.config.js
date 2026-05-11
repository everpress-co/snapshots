const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

module.exports = ( env, argv ) => {
	const isProduction = argv.mode === 'production';

	return {
		entry: {
			script: './src/script.js',
		},
		output: {
			path: path.resolve( __dirname, 'build' ),
			filename: '[name].js',
			clean: true,
		},
		watchOptions: {
			ignored: /node_modules/,
			aggregateTimeout: 300,
			...( process.env.WEBPACK_WATCH_POLL
				? {
						poll:
							Number( process.env.WEBPACK_WATCH_POLL ) ||
							1000,
				  }
				: {} ),
		},
		module: {
			rules: [
				{
					test: /\.s[ac]ss$/i,
					use: [
						MiniCssExtractPlugin.loader,
						'css-loader',
						'sass-loader',
					],
				},
			],
		},
		plugins: [
			new MiniCssExtractPlugin( {
				filename: 'style.css',
			} ),
		],
		devtool: isProduction ? 'source-map' : 'eval-source-map',
		stats: isProduction ? 'errors-warnings' : 'minimal',
	};
};
