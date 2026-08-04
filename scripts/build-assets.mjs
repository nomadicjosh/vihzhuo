import { build, context } from 'esbuild';
import { compile } from 'sass';
import { copyFile, mkdir, readdir, stat } from 'node:fs/promises';
import { dirname, join } from 'node:path';

const production = process.env.NODE_ENV === 'production';
const watching = process.argv.includes('--watch');

const entries = [
  ['src/Modules/Auth/resources/assets/sass/app.scss', 'dist/auth/app.css', 'sass'],
  ['src/Modules/WebsiteManager/resources/assets/sass/app.scss', 'dist/websitemanager/app.css', 'sass'],
  ['src/Modules/WebsiteManager/resources/assets/js/app.js', 'dist/websitemanager/app.js', 'js'],
  ['src/Modules/GrapesJS/resources/assets/sass/app.scss', 'dist/pagebuilder/app.css', 'sass'],
  ['src/Modules/GrapesJS/resources/assets/sass/page-injection.scss', 'dist/pagebuilder/page-injection.css', 'sass'],
  ['src/Modules/GrapesJS/resources/assets/js/app.js', 'dist/pagebuilder/app.js', 'js'],
  ['src/Modules/GrapesJS/resources/assets/js/page-injection.js', 'dist/pagebuilder/page-injection.js', 'js'],
  ['src/Modules/GrapesJS/resources/assets/js/grapesjs.js', 'dist/pagebuilder/grapesjs-v0.23.4.min.js', 'js'],
];

async function copyDirectory(source, destination) {
  await mkdir(destination, { recursive: true });
  for (const item of await readdir(source)) {
    const sourcePath = join(source, item);
    const destinationPath = join(destination, item);
    if ((await stat(sourcePath)).isDirectory()) {
      await copyDirectory(sourcePath, destinationPath);
    } else {
      await copyFile(sourcePath, destinationPath);
    }
  }
}

async function buildAssets() {
  await Promise.all(entries.map(async ([source, destination, type]) => {
    await mkdir(dirname(destination), { recursive: true });
    if (type === 'sass') {
      const result = compile(source, { style: production ? 'compressed' : 'expanded' });
      const { writeFile } = await import('node:fs/promises');
      await writeFile(destination, result.css);
      return;
    }
    await build({
      entryPoints: [source],
      outfile: destination,
      bundle: true,
      minify: production,
      sourcemap: !production,
      target: ['es2022'],
      format: 'iife',
      logLevel: 'info',
    });
  }));

  await copyFile('node_modules/grapesjs/dist/css/grapes.min.css', 'dist/pagebuilder/grapesjs-v0.23.4.min.css');
  await copyDirectory('src/Modules/GrapesJS/resources/assets/images', 'dist/pagebuilder/images');
}

if (watching) {
  const contexts = await Promise.all(entries.filter(([, , type]) => type === 'js').map(([source, destination]) => context({
    entryPoints: [source],
    outfile: destination,
    bundle: true,
    sourcemap: true,
    target: ['es2022'],
    format: 'iife',
  })));
  await Promise.all(contexts.map(buildContext => buildContext.watch()));
  await buildAssets();
} else {
  await buildAssets();
}
