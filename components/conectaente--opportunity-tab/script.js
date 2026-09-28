app.component('conectaente--opportunity-tab', {
    template: $TEMPLATES['conectaente--opportunity-tab'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
    },

    setup() {
        const api = new API('conectaente');
        const config = $MAPAS.config.conectaenteOpportunityTab;

        return { api, config };
    },

    data() {
        return {
            serverSealed: null,
            // null até a rota responder
            missing: null,
            labels: {},
            anchors: {},
            fieldGroups: {},
            // null enquanto nenhum grupo está aberto: aí a aba mostra o formulário inteiro
            openGroup: null,
            loading: false,
            loadFailed: false,
            lastRequest: 0,
            reloadTimer: null,
        };
    },

    computed: {
        hasFederativeSeal() {
            // sem selos na resposta, o populate() do core inicializa seals como objeto, não array
            return Object.values(this.entity.seals || {}).some((seal) => this.config.federativeSealIds.includes(seal.sealId));
        },

        isSealed() {
            return this.serverSealed ?? this.hasFederativeSeal;
        },

        hasLegalEntity() {
            return (this.entity.registrationProponentTypes || []).includes(this.config.legalEntityLabel);
        },

        hasPendingFields() {
            return !!this.missing && Object.keys(this.missing).length > 0;
        },

        // no rascunho a data é gravada ao publicar; só a oportunidade já publicada precisa dela
        isPublished() {
            return Number(this.entity.status) === 1;
        },

        // o regulamento é regra do plugin; o avatar, do core, quando a instalação o exige
        validatedFiles() {
            const files = this.entity.files || {};

            return [files.rules?.id, files.avatar?.id].join();
        },
    },

    watch: {
        hasFederativeSeal() {
            this.serverSealed = null;
            this.scheduleReload();
        },

        // __originalValues muda quando a entidade é repovoada pelo servidor; __processing, também a cada renovação do lock
        'entity.__originalValues': {
            deep: true,
            handler() {
                this.reloadIfSealed();
            },
        },

        validatedFiles() {
            this.reloadIfSealed();
        },
    },

    mounted() {
        // sem Ente Federado ativo, nenhuma oportunidade é selada
        if (this.config.federativeSealIds.length) {
            this.loadRequirements();
        }
    },

    beforeUnmount() {
        clearTimeout(this.reloadTimer);
    },

    methods: {
        // a rota só classifica as pendências, e aqui há campo preenchido também
        groupOf(field) {
            return field.startsWith('conectaente_') ? 'plugin' : 'core';
        },

        showsField(field) {
            return !this.openGroup || this.groupOf(field) === this.openGroup;
        },

        showsAnyField(fields) {
            return fields.some((field) => this.showsField(field));
        },

        scheduleReload() {
            // a resposta de uma consulta em curso já nasce velha
            this.lastRequest++;
            clearTimeout(this.reloadTimer);
            this.reloadTimer = setTimeout(() => this.loadRequirements());
        },

        // salvar não mexe em selo: sem ele, a resposta da rota não muda
        reloadIfSealed() {
            if (this.isSealed) {
                this.scheduleReload();
            }
        },

        async loadRequirements() {
            const request = ++this.lastRequest;
            this.loading = true;

            const requirements = await this.fetchRequirements().catch(() => null);

            if (request !== this.lastRequest) {
                return;
            }

            this.loading = false;
            this.loadFailed = !requirements;

            if (requirements) {
                this.serverSealed = requirements.sealed;
                this.missing = requirements.missing;
                this.labels = requirements.labels;
                this.anchors = requirements.anchors;
                this.fieldGroups = requirements.groups;
            }
        },

        async fetchRequirements() {
            const url = Utils.createUrl('conectaente', 'opportunityRequirements', [this.entity.id]);
            const response = await this.api.GET(url, {}, { cache: 'no-store' });
            const requirements = await response.json();

            if (!response.ok) {
                throw requirements;
            }

            return requirements;
        },
    },
});
