'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass');
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');
const rev = require('gulp-rev');
const minify = require('gulp-minify');
const notify = require('gulp-notify');
const imagemin = require('gulp-imagemin');
const plumber = require('gulp-plumber');
const sizereport = require('gulp-sizereport');
const del = require('del');

// ------------------
// Gulp Tasks
// ------------------

gulp.task('sass', () => {
    return (
        gulp
            .src('./Resources/Assets/**/*.scss')
            //.pipe(sourcemaps.init())
            .pipe(
                plumber({
                    errorHandler: notify.onError({
                        title: 'Error CSS',
                        message: '<%= error.message %>',
                        sound: 'Frog'
                    })
                })
            )
            .pipe(
                sass({
                    outputStyle: 'expanded',
                    sourceMap: true,
                    errLogToConsole: true
                }).on('error', sass.logError)
            )
            .pipe(
                autoprefixer({
                    overrideBrowserslist: ['last 2 versions and > 0.5%'],
                    cascade: false
                })
            )
            .pipe(
                cleanCSS({
                    level: 2
                })
            )
            //.pipe(sourcemaps.write())
            .pipe(gulp.dest('./dist'))
    );
});

gulp.task('js', () => {
    return gulp
        .src('./Resources/Assets/**/*.js')
        .pipe(
            minify({
                ext: {
                    src: '-debug.js',
                    min: '.js'
                }
            })
        )
        .pipe(gulp.dest('./dist'));
});

gulp.task('img', () => {
    return gulp
        .src('./Resources/Assets/**/*.+(png|jpg|jpeg|gif|svg)')
        .pipe(
            imagemin(
                [
                    imagemin.gifsicle({ interlaced: true }),
                    imagemin.jpegtran({ progressive: true }),
                    imagemin.optipng({ optimizationLevel: 5 }),
                    imagemin.svgo({
                        plugins: [
                            { removeViewBox: true },
                            { cleanupIDs: false }
                        ]
                    })
                ],
                { verbose: true }
            )
        )
        .pipe(gulp.dest('./dist'));
});

gulp.task('clean', done => {
    del.sync('./dist', {
        force: true
    });
    done();
});

gulp.task('clean_watch', done => {
    del.sync(['./dist/**/js', './dist/**/scss'], {
        force: true
    });
    done();
});

gulp.task('rev', () => {
    return gulp
        .src('./dist/**/*.+(css|js)')
        .pipe(rev())
        .pipe(gulp.dest('./dist')) // write rev'd assets to build dir
        .pipe(rev.manifest())
        .pipe(gulp.dest('./dist')); // write manifest to build dir
});

gulp.task('size', () => {
    return gulp.src('./dist/**/*.+(css|js)').pipe(
        sizereport({
            gzip: true
        })
    );
});

// Main tasks
gulp.task(
    'build',
    gulp.series('clean', gulp.parallel('img', 'js', 'sass'), 'rev', 'size')
);
gulp.task(
    'build_watch',
    gulp.series('clean_watch', gulp.parallel('js', 'sass'), 'rev', 'size')
);

gulp.task('watch', () => {
    console.log('WATCH INCLUDE (css) ' + './Resources/Assets/**/*.scss');
    console.log('WATCH INCLUDE (js) ' + './Resources/Assets/**/*.js');
    gulp.watch(['./Resources/Assets/**/*.scss', './Resources/Assets/**/*.js'], { interval: 1000, usePolling: true }, gulp.series('build_watch'));
});
