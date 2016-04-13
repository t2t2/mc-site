var gulp = require('gulp');

module.exports = function() {
	var config = require('../config'),
		extensionsGlob = require('../lib/extensions-glob'),
		path = require('path'),
		minify = require('gulp-minify'),
		replace = require('gulp-replace-path')

	var paths = {
		src: path.join(config.root.src, '../../../node_modules/retina.js/src/*' + extensionsGlob(config.tasks.js.extensions)),
		dest: path.join(config.root.dest, './mindcrack/assets/scripts'),
	}

	return gulp.src(paths.src)
		.pipe(minify(config.tasks.js.minify))
		.pipe(gulp.dest(paths.dest))
}
gulp.task('js', module.exports);