var gutil = require('gulp-util');

var config = {
	root:  {
		src:  './theme/*/assets',
		dest: './public/theme',
	},
	watch: {},

	tasks: {
		assets: {
			ignoreExtensions: ['scss', 'twig', 'yml']
		},
		css: {
			autoprefixer: {
				browsers: ["last 3 version"]
			},
			extensions: ['scss'],
			sass: {},
		},
		js: {
			extensions: ['js'],
			minify: {
				ext:{
					src: '.js',
					min: '.min.js'
				}
			},
		}
	},
}

/*
 * In case default watch isn't working (eg. windows host of linux vagrant box),
 * use --polling to force polling
 */
if (gutil.env.polling) {
	config.watch.usePolling = true;
	config.watch.interval = 1000;
}

module.exports = config
