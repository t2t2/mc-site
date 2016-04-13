var gulp = require('gulp');

module.exports = function (cb) {
	var gulpSequence = require('gulp-sequence')

	gulpSequence('clean', ['assets'], ['css'], ['js'], 'watch', cb)
}
gulp.task('default', module.exports);
