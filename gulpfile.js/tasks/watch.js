var gulp = require('gulp');

gulp.task('watch', function () {
	var watch = require('gulp-watch'),
		config = require('../config'),
		assets = require('../config/assets'),
		less = require('../config/less');

	watch(assets.watch, config.watchOptions, function () {
		gulp.start('assets');
	});
	watch(less.watch, config.watchOptions, function () {
		gulp.start('less');
	});
});