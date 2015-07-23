var gulp = require('gulp');

gulp.task('assets', function () {
	var changed = require('gulp-changed'),
		config = require('../config/assets');

	return gulp.src(config.src)
		.pipe(changed(config.dest)) // Ignore unchanged files
		.pipe(gulp.dest(config.dest));
});