import './component';
import './preview';
Shopware.Service('cmsService').registerCmsBlock({
    name: 'nw-layout-one',
    category: 'nw-layouts',
    label: '3 Spalten',
    component: 'sw-cms-block-nw-layout-one',
    previewComponent: 'sw-cms-preview-nw-layout-one',
    defaultConfig: {
        marginBottom: '',
        marginTop: '',
        marginLeft: '',
        marginRight: '',
        sizingMode: 'boxed'
    },
    slots: {
        left: 'image',
        'center-top': 'text',
        'center-bottom': 'text',
        'right-top': 'text',
        'right-bottom': 'text'
    }
});