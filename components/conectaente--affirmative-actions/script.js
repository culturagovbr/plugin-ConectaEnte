/**
 * Outras modalidades de ações afirmativas: opções, subcategorias por opção e a descrição da legislação local.
 */

app.component('conectaente--affirmative-actions', {
    template: $TEMPLATES['conectaente--affirmative-actions'],

    props: {
        entity: {
            type: Object,
            required: true,
        },
        prop: {
            type: String,
            required: true,
        },
        classes: {
            type: [String, Array, Object],
            default: '',
        },
    },

    setup() {
        const text = Utils.getTexts('conectaente--affirmative-actions');
        const vocabulary = $MAPAS.config.conectaenteAffirmativeActions;

        return { text, ...vocabulary };
    },

    computed: {
        description() {
            return this.entity.$PROPERTIES?.[this.prop];
        },
        opcoesComSublistaKeys() {
            return this.options.filter((option) => option.hasGroups).map((option) => option.value);
        },
        data() {
            const raw = this.entity[this.prop];
            return this.normalizeData(raw);
        },
        opcoesArray() {
            const arr = this.entity[this.prop]?.opcoes;
            return Array.isArray(arr) ? arr : [];
        },
        isNaoPrevistasMarcado() {
            return this.opcoesArray.includes(this.notPlanned.value);
        },
        isOpcaoMarcada() {
            return (optionKey) => this.opcoesArray.includes(optionKey);
        },
        sublistLabels() {
            return this.groups.reduce((accumulator, group) => {
                accumulator[group.value] = group.label;
                return accumulator;
            }, {});
        },
        descricaoOutra() {
            return (this.entity[this.prop]?.outra_legislacao_descricao || '').slice(0, this.descriptionMaxLength);
        },
        descricaoOutraLength() {
            return (this.entity[this.prop]?.outra_legislacao_descricao || '').length;
        },
        contadorCaracteres() {
            return `${this.descricaoOutraLength}/${this.descriptionMaxLength}`;
        },
        hasError() {
            const err = this.entity.__validationErrors?.[this.prop];
            return Array.isArray(err) && err.length > 0;
        },
        errorMessage() {
            const err = this.entity.__validationErrors?.[this.prop];
            return Array.isArray(err) && err.length > 0 ? err[0] : '';
        },
        errorMessages() {
            const errors = this.entity.__validationErrors?.[this.prop];
            return Array.isArray(errors) ? errors : [];
        },
        hasErrorOutraLegislacao() {
            return this.hasError && this.opcoesArray.includes(this.otherLegislation) && this.descricaoOutra.trim() === '';
        }
    },

    methods: {
        getDefaultData() {
            const def = {
                opcoes: [],
                outra_legislacao_descricao: ''
            };
            this.opcoesComSublistaKeys.forEach((optionKey) => { def[optionKey] = []; });
            return def;
        },
        normalizeData(raw) {
            if (raw === null || raw === undefined) return this.getDefaultData();
            if (typeof raw === 'object' && !Array.isArray(raw)) {
                const data = { ...this.getDefaultData(), ...raw };
                if (!Array.isArray(data.opcoes)) data.opcoes = [];
                this.opcoesComSublistaKeys.forEach((optionKey) => {
                    data[optionKey] = Array.isArray(data[optionKey]) ? data[optionKey] : [];
                });
                data.outra_legislacao_descricao = typeof data.outra_legislacao_descricao === 'string' ? data.outra_legislacao_descricao : '';
                return data;
            }
            return this.getDefaultData();
        },
        ensureData() {
            if (!this.entity[this.prop] || typeof this.entity[this.prop] !== 'object') {
                this.entity[this.prop] = { ...this.getDefaultData() };
            }
            const data = this.entity[this.prop];
            if (!Array.isArray(data.opcoes)) data.opcoes = [];
            this.opcoesComSublistaKeys.forEach((optionKey) => {
                if (!Array.isArray(data[optionKey])) data[optionKey] = [];
            });
            if (typeof data.outra_legislacao_descricao !== 'string') data.outra_legislacao_descricao = '';
        },
        setNaoPrevistas(checked) {
            this.ensureData();
            const data = this.entity[this.prop];
            if (checked) {
                data.opcoes = [this.notPlanned.value];
                this.opcoesComSublistaKeys.forEach((optionKey) => { data[optionKey] = []; });
                data.outra_legislacao_descricao = '';
            } else {
                data.opcoes = data.opcoes.filter((optionKey) => optionKey !== this.notPlanned.value);
            }
        },
        setOpcao(optionKey, checked) {
            this.ensureData();
            const data = this.entity[this.prop];
            if (checked) {
                if (data.opcoes.includes(this.notPlanned.value)) {
                    data.opcoes = [];
                }
                if (!data.opcoes.includes(optionKey)) data.opcoes.push(optionKey);
                if (this.opcoesComSublistaKeys.includes(optionKey) && !Array.isArray(data[optionKey])) data[optionKey] = [];
            } else {
                data.opcoes = data.opcoes.filter((key) => key !== optionKey);
                if (this.opcoesComSublistaKeys.includes(optionKey)) data[optionKey] = [];
                if (optionKey === this.otherLegislation) data.outra_legislacao_descricao = '';
            }
        },
        getSublistModel(optionKey) {
            this.ensureData();
            const arr = this.entity[this.prop][optionKey];
            return Array.isArray(arr) ? arr : [];
        },
        setDescricaoOutra(value) {
            this.ensureData();
            this.entity[this.prop].outra_legislacao_descricao = (value || '').slice(0, this.descriptionMaxLength);
        },
        /** Opção marcada mas sublista vazia — erro abaixo do select */
        hasErrorForSublista(optionKey) {
            if (!this.hasError || !this.opcoesArray.includes(optionKey)) return false;
            const arr = this.getSublistModel(optionKey);
            return !Array.isArray(arr) || arr.length === 0;
        }
    }
});
