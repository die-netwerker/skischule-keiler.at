import template from './sw-cms-el-hoverbox.html.twig';
import './sw-cms-el-hoverbox.scss';

Shopware.Component.register('sw-cms-el-hoverbox', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        header() {
            return `${this.element.config.header.value}`;
        },
        text() {
            return `${this.element.config.text.value}`;
        }
    },
    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('hoverbox');
        }
    }
});