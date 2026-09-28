app.component('conectaente--opportunity-ranges', {
    template: $TEMPLATES['conectaente--opportunity-ranges'],
    setup() {
        const messages = useMessages();
        return{messages};
    },
    props: {
        entity: {
            type: Entity,
            required: true
        }
    },
    created() {
        this.entity.registrationRanges = this.entity.registrationRanges || [];
    },
    methods: { 
        addRange() {
            if (this.areAllRangesValid()) {
                this.entity.registrationRanges.push({
                    label: '',
                    limit: 0,
                    value: NaN
                });

                this.$nextTick(() => {
                    const lastIndex = this.entity.registrationRanges.length - 1;
                    const descriptionInput = this.$refs['description-' + lastIndex];
                    if (descriptionInput && descriptionInput.length > 0) {
                        descriptionInput[0].focus();
                    }
                });
            } else{
               this.messages.error("Por favor, preencha todos os campos da faixa antes de adicionar uma nova faixa.");
            }
        },
        removeRange(item) {
            this.entity.registrationRanges = this.entity.registrationRanges.filter((value, key) => item != key);
        },
        // sem gravação: o edital selado só é salvo inteiro, pelo "Salvar" da página
        discardIfEmpty(item) {
            item.label = item.label.trim();

            if (item.label.length === 0) {
                this.removeRange(this.entity.registrationRanges.indexOf(item));
            }
        },
        areAllRangesValid() {
            return this.entity.registrationRanges.every(range => range.label.trim().length > 0);
        },
    }
});
