var path = require('path')
var webpack = require('webpack')
var ExtractTextPlugin = require("extract-text-webpack-plugin")

var isProd = process.env.NODE_ENV == 'production'

var cssLoaderOptions = JSON.stringify({
	autoprefixer: {
		add: true // overwrite cssnano
	}
})

var config = module.exports = {
	context: path.join(__dirname, 'theme', 'mindcrack', 'assets'),
	entry: {
		main: ['./javascripts/main.js', './stylesheets/style.scss'],
		polyfill: ['core-js/es6/promise.js']
	},
	output: {
		filename: '[name].js',
		path: path.join(__dirname, 'public', 'theme', 'mindcrack', 'assets'),
		publicPath: '/theme/mindcrack/assets/',
		chunkFilename: '[name].[id].js'
	},
	module: {
		loaders: [
			{
				// Provide bootstrap with jQuery
				test: path.join(__dirname, 'node_modules', 'bootstrap'),
				exclude: /\.scss$/,
				loaders: [
					{
						loader: 'imports',
						query: {
							jQuery: 'jquery',
							define: '>false'
						}
					}
				]
			},
			{
				// Don't let moment load extra locales
				test: path.join(__dirname, 'node_modules', 'moment'),
				loaders: [
					{
						loader: 'imports',
						query: {
							require: '>false',
							define: '>false'
						}
					}
				]
			},
			{
				// ES6
				test: /\.js$/,
				exclude: /(node_modules)/,
				loader: 'babel',
				query: {
					presets: ['es2015-webpack']
				}
			},
			{
				// Sass
				test: /\.scss$/,
				// Disable sass minification so css-loader handles it
				loader: ExtractTextPlugin.extract(['css?' + cssLoaderOptions, 'sass?outputStyle=nested'])
			},
			{
				// Images
				test: /\.(png|jpe?g|gif|svg|woff2?|eot|ttf|otf)(\?.*)?$/,
				loader: 'url',
				query: {
					limit: 10000,
					name: '[name].[hash:7].[ext]'
				}
			}
		]
	},
	plugins: [
		new ExtractTextPlugin(isProd ? "[name].[hash].css" : "[name].css")
	]
}

// Production settings
if (isProd) {
	var ManifestPlugin = require('webpack-manifest-plugin')

	// Versioning
	config.output.filename = '[name].[chunkhash].bundle.js'
	config.output.chunkFilename = '[name].[id].[chunkhash].js'

	// Plugins
	config.plugins.push(
		new ManifestPlugin({
			basePath: '/theme/mindcrack/assets/',
			fileName: '../../../manifest.json'
		}),
		new webpack.DefinePlugin({
			'process.env': {
				NODE_ENV: '"production"'
			}
		}),
		new webpack.optimize.UglifyJsPlugin({
			compress: {
				warnings: false
			}
		})
	);
}
