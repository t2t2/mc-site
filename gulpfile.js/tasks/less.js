var gulp = require('gulp');

gulp.task('less', function () {

	var less = require('gulp-less'),
		config = require('../config/less'),
	//	sourcemaps = require('gulp-sourcemaps'), // broken :(
		autoprefixer = require('gulp-autoprefixer');

	return gulp.src(config.src)
	//	.pipe(sourcemaps.init())
		.pipe(less(config.settings))
		.pipe(autoprefixer(config.autoprefixer))
	//	.pipe(sourcemaps.write())
		.pipe(gulp.dest(config.dest));
});