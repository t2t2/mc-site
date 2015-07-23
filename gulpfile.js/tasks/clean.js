var gulp = require('gulp');

gulp.task('clean', function (cb) {
	var del = require('del');
	var config = require('../config');

	del([
		config.publicThemes
	], cb);
});
