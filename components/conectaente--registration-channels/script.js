/**
 * Formas de inscrição previstas no edital: objeto com previstasNoEdital e formas[].
 */

app.component('conectaente--registration-channels', {
    template: $TEMPLATES['conectaente--registration-channels'],

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
        const text = Utils.getTexts('conectaente--registration-channels');
        const vocabulary = $MAPAS.config.conectaenteRegistrationChannels;

        return { text, ...vocabulary };
    },

    computed: {
        data() {
            const raw = this.entity[this.prop];
            return this.normalizeData(raw);
        },
        previstasNoEdital: {
            get() { return this.data.previstasNoEdital || ''; },
            set(val) {
                this.ensureData();
                this.entity[this.prop].previstasNoEdital = val;
                if (val === 'nao') {
                    this.entity[this.prop].formas = [];
                }
            }
        },
        isSim() {
            return this.data.previstasNoEdital === 'sim';
        },
        formasArray() {
            const arr = this.entity[this.prop]?.formas;
            return Array.isArray(arr) ? arr : [];
        },
        hasError() {
            const err = this.entity.__validationErrors?.[this.prop];
            return Array.isArray(err) && err.length > 0;
        },
        errorMessage() {
            const err = this.entity.__validationErrors?.[this.prop];
            return Array.isArray(err) && err.length > 0 ? err[0] : '';
        },
        emailFieldError() {
            const err = this.entity.__validationErrors?.[`${this.prop}Email`];
            return Array.isArray(err) && err.length > 0 ? err[0] : '';
        },
    },

    methods: {
        getDefaultData() {
            return {
                previstasNoEdital: '',
                formas: [],
            };
        },
        normalizeData(raw) {
            if (raw === null || raw === undefined) return this.getDefaultData();
            if (typeof raw === 'object' && !Array.isArray(raw)) {
                const d = { ...this.getDefaultData(), ...raw };
                if (Array.isArray(d.formas)) {
                    d.formas = d.formas.filter(f => f && this.channels.some(channel => channel.value === f.tipo));
                } else {
                    d.formas = [];
                }
                return d;
            }
            return this.getDefaultData();
        },
        ensureData() {
            if (!this.entity[this.prop] || typeof this.entity[this.prop] !== 'object') {
                this.entity[this.prop] = { ...this.getDefaultData() };
            }
            if (!Array.isArray(this.entity[this.prop].formas)) {
                this.entity[this.prop].formas = [];
            }
        },
        isTipoMarcado(tipo) {
            return this.formasArray.some(f => f.tipo === tipo);
        },
        getDescricao(tipo) {
            const item = this.formasArray.find(f => f.tipo === tipo);
            return item ? (item.descricao || '') : '';
        },
        setMarcado(tipo, checked) {
            this.ensureData();
            let arr = [...(this.entity[this.prop].formas || [])];
            if (checked) {
                if (!arr.some(f => f.tipo === tipo)) {
                    arr.push({ tipo, descricao: '' });
                }
            } else {
                arr = arr.filter(f => f.tipo !== tipo);
            }
            this.entity[this.prop].formas = arr;
        },
        setDescricao(tipo, valor) {
            this.ensureData();
            const arr = this.entity[this.prop].formas;
            const item = arr.find(f => f.tipo === tipo);
            if (item) item.descricao = valor;
        },
    }
});
