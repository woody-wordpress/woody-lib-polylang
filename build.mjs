import pipe from '../../../builder/lib/pipe.mjs';
import images from '../../../builder/tasks/images.mjs';
import stylesheets from '../../../builder/tasks/stylesheets.mjs';
import javascripts from '../../../builder/tasks/javascripts.mjs';

pipe({
    'dist': 'dist',
    'pipe': ['img', 'css', 'js'],
    'tasks': {
        'img': {
            'fn': images,
            'config': {
                'source': ['Resources/Assets/img/**/*.+(png|jpg|jpeg|gif|svg|webp)'],
                'target': 'img'
            }
        },
        'css': {
            'fn': stylesheets,
            'config': {
                'source': ['Resources/Assets/scss/*.+(sass|scss)'],
                'target': 'css'
            }
        },
        'js': {
            'fn': javascripts,
            'config': {
                'source': ['Resources/Assets/js/*.+(js|mjs)'],
                'target': 'js'
            }
        }
    }
});
