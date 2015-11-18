var gulp = require('gulp');

module.exports = function () {
	var autoprefixer = require('gulp-autoprefixer'),
		changed = require('gulp-changed'),
		config = require('../config'),
		extensionsGlob = require('../lib/extensions-glob'),
		handleErrors = require('../lib/handle-errors'),
		sourcemaps = require('gulp-sourcemaps'),
		path = require('path'),
		sass = require('gulp-sass')

	var paths = {
		src:  path.join(config.root.src, '**/*' + extensionsGlob(config.tasks.css.extensions)),
		dest: config.root.dest,
	}

	return gulp.src(paths.src)
		.pipe(sourcemaps.init())
		.pipe(sass(config.tasks.css.sass))
		.on('error', handleErrors)
		.pipe(autoprefixer(config.tasks.css.autoprefixer))
		.pipe(sourcemaps.write())
		.pipe(gulp.dest(paths.dest))
}
gulp.task('css', module.exports);