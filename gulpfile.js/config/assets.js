var config = require('./');

var paths = [
	config.sourceThemes + '/**/*',
	// Ignore source less files
	'!' + config.sourceThemes + '/**/*.less',
	// Ignore theme files
	'!' + config.sourceThemes + '/**/*.twig',
	'!' + config.sourceThemes + '/**/*.yml',
];

module.exports = {
	// Paths
	watch: paths,
	src:   paths,
	dest:  config.publicThemes,
};