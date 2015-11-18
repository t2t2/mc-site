var gulp = require('gulp');

module.exports = function (cb) {
	var gulpSequence = require('gulp-sequence')

	gulpSequence('clean', ['assets'], ['css'], 'watch', cb)
}
gulp.task('default', module.exports);
