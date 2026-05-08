const esbuild = require('esbuild');
const fs = require('fs');

// Ensure dist directory exists
fs.mkdirSync('resources/dist', { recursive: true });

// Build JS bundle
esbuild.build({
    entryPoints: ['resources/js/icon-picker.js'],
    bundle: true,
    minify: true,
    outfile: 'resources/dist/icon-picker.js',
    format: 'iife',
    globalName: 'IconPickerBundle',
    external: [],  // bundle everything including @alpinejs/focus
}).then(() => {
    console.log('JS build complete → resources/dist/icon-picker.js');
}).catch((e) => {
    console.error('JS build failed:', e);
    process.exit(1);
});

// Copy CSS to dist (minified inline in future)
fs.copyFileSync(
    'resources/css/icon-picker.css',
    'resources/dist/icon-picker.css'
);
console.log('CSS copied → resources/dist/icon-picker.css');
