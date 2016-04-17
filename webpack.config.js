var path = require('path')
var webpack = require('webpack')
var ExtractTextPlugin = require("extract-text-webpack-plugin")

var config = module.exports = {
	context: path.join(__dirname, 'theme', 'mindcrack', 'assets'),
	entry: {
		main: ['./javascripts/main.js', './stylesheets/style.scss']
	},
	output: {
		filename: '[name].js',
		path: path.join(__dirname, 'public', 'theme', 'mindcrack', 'assets'),
		publicPath: '/theme/mindcrack/assets/'
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
				// Sass
				test: /\.scss$/,
				loader: ExtractTextPlugin.extract(['css', 'sass'])
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
		new ExtractTextPlugin("[name].css")
	]
}

// Production settings
if(process.env.NODE_ENV == 'production') {
	var ManifestPlugin = require('webpack-manifest-plugin')

	// Versioning
	config.output.filename = '[name].[chunkhash].bundle.js'

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
