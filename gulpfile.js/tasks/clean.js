var gulp = require('gulp');

module.exports = function (cb) {
	var del = require('del');
	var config = require('../config');

	del([
		config.root.dest
	], cb);
}
gulp.task('clean', module.exports);
