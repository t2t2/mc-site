var gulp = require('gulp');

module.exports = function () {
	var changed = require('gulp-changed'),
		config = require('../config'),
		extensionsGlob = require('../lib/extensions-glob'),
		path = require('path')

	var paths = {
		src:  [
			path.join(config.root.src, '**/*'),
			'!' + path.join(config.root.src, '**/*' + extensionsGlob(config.tasks.assets.ignoreExtensions)),
		],
		dest: config.root.dest,
	}

	return gulp.src(paths.src)
		.pipe(changed(paths.dest)) // Ignore unchanged files
		.pipe(gulp.dest(paths.dest));
}
gulp.task('assets', module.exports);