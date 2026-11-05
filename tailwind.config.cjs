const path = require('node:path');

global.tailwind = {};
require('./public/assets/js/tailwind-config.js');

module.exports = {
    content: [
        path.join(__dirname, 'app/Views/**/*.php'),
        path.join(__dirname, 'admin.php'),
        path.join(__dirname, 'index.php'),
        path.join(__dirname, 'stitch_mobilex_tech_storefront/**/*.html'),
    ],
    darkMode: global.tailwind.config.darkMode,
    theme: global.tailwind.config.theme,
    plugins: [],
};
