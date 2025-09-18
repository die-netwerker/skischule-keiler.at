import template from './sw-cms-el-config-hoverbox.html.twig';

Shopware.Component.register('sw-cms-el-config-hoverbox', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        header: {
            get() {
                return this.element.config.header.value;
            },
            set(value) {
                this.element.config.header.value = value;
                this.$emit('element-update', this.element);
            }
        },
        text: {
            get() {
                return this.element.config.text.value;
            },
            set(value) {
                this.element.config.text.value = value;
                this.$emit('element-update', this.element);
            }
        },
        hovertext: {
            get() {
                return this.element.config.hovertext.value;
            },
            set(value) {
                this.element.config.hovertext.value = value;
                this.$emit('element-update', this.element);
            }
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