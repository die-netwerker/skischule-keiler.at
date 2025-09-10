<?php

namespace Deployer;

require_once 'recipe/common.php';
require_once 'contrib/cachetool.php';

set('bin/console', '{{bin/php}} {{release_or_current_path}}/bin/console');

set('cachetool', '/run/php/php-fpm.sock');
set('application', 'Shopware 6');
set('allow_anonymous_stats', false);
set('default_timeout', 3600); // Increase when tasks take longer than that.

// Hosts

import('.hosts.yaml');

set('writable_mode', 'chmod');
set('keep_releases', 3); // Keeps 3 old releases for rollbacks (if no DB migrations were executed) 

// Gemeinsame Dateien (z. B. Konfiguration)
set('shared_files', [
    '.env.local',
    'install.lock',
    'public/.htaccess',
    'public/.user.ini',
    'auth.json',
]);

// Gemeinsame Verzeichnisse
set('shared_dirs', [
    'config/jwt',
    'files',
    'var/log',
    'public/media',
    'public/thumbnail',
    'public/sitemap',
]);

// Schreibbare Verzeichnisse
set('writable_dirs', [
    'config/jwt',
    'custom/plugins',
    'files',
    'public/bundles',
    'public/css',
    'public/fonts',
    'public/js',
    'public/media',
    'public/sitemap',
    'public/theme',
    'public/thumbnail',
    'var',
]);

// Lokaler Admin-Build in DDEV (damit administration:build nicht auf dem Server nötig ist)
//task('local:build:admin', function () {
//    runLocally('bin/console administration:build');
//});

// Shopware Deployment Helper (führt z. B. cache:warmup und migrations aus)
task('sw:deployment:helper', static function() {
    run('cd {{release_path}} && vendor/bin/shopware-deployment-helper run');
});

// Touch der install.lock Datei (Shopware braucht das zur Aktivierung)
task('sw:touch_install_lock', static function () {
    run('cd {{release_path}} && touch install.lock');
});

// Vorab-Healthcheck
task('sw:health_checks', static function () {
    run('{{bin/console}} system:check --context=pre_rollout');
});

// Plugins aktualisieren
task('sw:plugins:refresh', static function () {
    run('{{bin/console}} plugin:refresh');
});

// CSS/JS/Theme kompilieren + Cache leeren
task('sw:build:assets', static function () {
    run('{{bin/console}} theme:compile');
    run('{{bin/console}} cache:clear');
});


// Haupt-Deploy-Task
desc('Deploys your Shopware 6 project');
task('deploy', [
    'deploy:prepare',
    'deploy:clear_paths',
    'sw:deployment:helper',  // Shopware-spezifischer Deployment-Prozess
    'sw:touch_install_lock',
    'sw:health_checks',
    'sw:plugins:refresh',    // Plugins registrieren (Composer + custom)
    'sw:build:assets',       // Theme + Cache
    'deploy:publish',
]);

// Überträgt Projekt auf Server – ohne bestimmte lokale Verzeichnisse
task('deploy:update_code')->setCallback(static function () {
    upload('.', '{{release_path}}', [
        'options' => [
            '--exclude=.git',
            '--exclude=.ddev',
            '--exclude=.github',
            '--exclude=deploy.php',
            '--exclude=_deploy.php',
            '--exclude=node_modules',
            // public/bundles NICHT mehr ausschließen, da Admin-Build enthalten!
        ],
    ]);
});

// Fehlerbehandlung
after('deploy:failed', 'deploy:unlock');