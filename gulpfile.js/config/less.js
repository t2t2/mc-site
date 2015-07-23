var config = require('./');

module.exports = {
	// Paths
	watch:        [config.sourceThemes + '/**/*.less'],
	src:          [config.sourceThemes + '/**/*.less', '!' + config.sourceThemes + '/**/_*.less'],
	dest:         config.publicThemes,
	
	autoprefixer: {browsers: ['last 2 version']},
	settings:     {},
};