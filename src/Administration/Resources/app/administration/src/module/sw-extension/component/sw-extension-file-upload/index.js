import template from './sw-extension-file-upload.html.twig';
import './sw-extension-file-upload.scss';
import pluginErrorHandler from '../../service/extension-error-handler.service';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const USER_CONFIG_KEY = 'extension.plugin_upload';

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['extensionStoreActionService', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            confirmModalVisible: false,
            shouldHideConfirmModal: false,
            pluginUploadUserConfig: null,
        };
    },

    computed: {
        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userConfigCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('key', USER_CONFIG_KEY));
            criteria.addFilter(Criteria.equals('userId', this.currentUser?.id));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.getUserConfig();
            this.isLoading = false;
        },

        onClickUpload() {
            this.$refs.fileInput.click();
        },

        onFileInputChange() {
            const newFiles = Array.from(this.$refs.fileInput.files);
            this.handleUpload(newFiles);
            this.$refs.fileForm.reset();
        },

        handleUpload(files) {
            this.isLoading = true;
            const formData = new FormData();
            formData.append('file', files[0]);

            return this.extensionStoreActionService.upload(formData).then(() => {
                Shopware.Service('shopwareExtensionService').updateExtensionData().then(() => {
                    return this.createNotificationSuccess({
                        message: this.$tc('sw-extension.my-extensions.fileUpload.messageUploadSuccess'),
                    });
                });
            }).catch((exception) => {
                const mappedErrors = pluginErrorHandler.mapErrors(exception.response.data.errors);
                mappedErrors.forEach((error) => {
                    if (error.parameters) {
                        this.showStoreError(error);
                        return;
                    }

                    this.createNotificationError({
                        message: this.$tc(error.message),
                    });
                });
            }).finally(() => {
                this.isLoading = false;
                this.confirmModalVisible = false;

                if (this.shouldHideConfirmModal === true) {
                    this.saveConfig(true);
                }
            });
        },

        showStoreError(error) {
            const docLink = this.$tc('sw-extension.errors.messageToTheShopwareDocumentation', 0, error.parameters);
            this.createNotificationError({
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        showConfirmModal() {
            if (this.pluginUploadUserConfig.value.hide_upload_warning === true) {
                this.onClickUpload();
                return;
            }

            this.confirmModalVisible = true;
        },

        closeConfirmModal() {
            this.confirmModalVisible = false;
        },

        getUserConfig() {
            return this.userConfigRepository.search(this.userConfigCriteria, Shopware.Context.api).then(response => {
                if (response.length) {
                    this.pluginUploadUserConfig = response.first();
                } else {
                    this.pluginUploadUserConfig = this.userConfigRepository.create(Shopware.Context.api);
                    this.pluginUploadUserConfig.key = USER_CONFIG_KEY;
                    this.pluginUploadUserConfig.userId = this.currentUser?.id;
                    this.pluginUploadUserConfig.value = {
                        hide_upload_warning: false,
                    };
                }
            });
        },

        saveConfig(value) {
            this.pluginUploadUserConfig.value = {
                hide_upload_warning: value,
            };

            this.userConfigRepository.save(this.pluginUploadUserConfig, Shopware.Context.api).then(() => {
                this.getUserConfig();
            });
        },
    },
};
