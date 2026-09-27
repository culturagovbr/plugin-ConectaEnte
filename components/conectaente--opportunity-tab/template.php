<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\ThematicAgenda;
use MapasCulturais\i;

$this->import('
    conectaente--affirmative-actions
    conectaente--funding-sources
    conectaente--opportunity-ranges
    conectaente--opportunity-requirements
    conectaente--proponent-types
    conectaente--quota-reservation
    conectaente--registration-channels
    conectaente--targeting-multiselect
    entity-field
    entity-file
    mc-card
    mc-container
    mc-tab
');
?>
<mc-tab v-if="isSealed" label="<?php i::esc_attr_e('CultBR') ?>" slug="cultbr">
    <mc-container>
        <main class="conectaente-opportunity-tab">
            <mc-card v-if="showsAnyField(['<?= CultBrMetadata::EXECUTION_TYPE ?>', 'registrationProponentTypes', 'rules', 'registrationFrom'])">
                <template #title>
                    <h3><?php i::_e('Identificação do edital') ?></h3>
                </template>
                <template #content>
                    <div class="grid-12">
                        <entity-field v-if="showsField('<?= CultBrMetadata::EXECUTION_TYPE ?>')" :entity="entity" prop="<?= CultBrMetadata::EXECUTION_TYPE ?>" :required="true" classes="col-12"></entity-field>
                        <entity-file v-if="showsField('rules')" :entity="entity" group-name="rules" title="<?= i::esc_attr__('Adicionar regulamento') ?>" title-modal="<?= i::esc_attr__('Adicionar regulamento') ?>" required editable classes="col-12"></entity-file>
                        <entity-field v-if="showsField('registrationFrom')" :entity="entity" prop="registrationFrom" classes="col-6 sm:col-12"></entity-field>
                        <entity-field v-if="(!entity.isContinuousFlow || entity.hasEndDate) && showsField('registrationTo')" :entity="entity" prop="registrationTo" classes="col-6 sm:col-12"></entity-field>
                        <conectaente--proponent-types v-if="showsField('registrationProponentTypes')" :entity="entity" class="col-12"></conectaente--proponent-types>
                        <entity-field v-if="hasLegalEntity && showsField('<?= CultBrMetadata::LEGAL_ENTITY_TYPES ?>')" :entity="entity" prop="<?= CultBrMetadata::LEGAL_ENTITY_TYPES ?>" type="checklist" :required="true" classes="col-12"></entity-field>
                    </div>
                </template>
            </mc-card>

            <mc-card v-if="showsField('<?= CultBrMetadata::SEGMENTS ?>')">
                <template #title>
                    <h3><?php i::_e('Público e território') ?></h3>
                </template>
                <template #content>
                    <div class="grid-12">
                        <conectaente--targeting-multiselect :entity="entity" prop="<?= CultBrMetadata::SEGMENTS ?>" other-prop="<?= CultBrMetadata::SEGMENTS_OTHER ?>" other-option="<?= htmlspecialchars(Segment::OTHER->value) ?>" all-options required classes="col-12"></conectaente--targeting-multiselect>
                        <conectaente--targeting-multiselect :entity="entity" prop="<?= CultBrMetadata::CULTURAL_STAGES ?>" other-prop="<?= CultBrMetadata::CULTURAL_STAGES_OTHER ?>" other-option="<?= htmlspecialchars(CulturalStage::OTHER->value) ?>" required classes="col-12"></conectaente--targeting-multiselect>
                        <conectaente--targeting-multiselect :entity="entity" prop="<?= CultBrMetadata::THEMATIC_AGENDAS ?>" other-prop="<?= CultBrMetadata::THEMATIC_AGENDAS_OTHER ?>" other-option="<?= htmlspecialchars(ThematicAgenda::OTHER->value) ?>" required classes="col-12"></conectaente--targeting-multiselect>
                        <conectaente--targeting-multiselect :entity="entity" prop="<?= CultBrMetadata::PRIORITY_TERRITORIES ?>" required classes="col-12"></conectaente--targeting-multiselect>
                    </div>
                </template>
            </mc-card>

            <mc-card v-if="showsAnyField(['vacancies', '<?= CultBrMetadata::QUOTA_RESERVATION ?>'])">
                <template #title>
                    <h3><?php i::_e('Vagas e recursos') ?></h3>
                </template>
                <template #content>
                    <div class="grid-12 conectaente-opportunity-tab__quotas">
                        <entity-field v-if="showsField('vacancies')" :entity="entity" prop="vacancies" classes="col-6 sm:col-12"></entity-field>
                        <entity-field v-if="showsField('totalResource')" :entity="entity" prop="totalResource" classes="col-6 sm:col-12"></entity-field>
                        <conectaente--opportunity-ranges v-if="showsField('registrationRanges')" :entity="entity" class="col-12"></conectaente--opportunity-ranges>
                        <conectaente--quota-reservation v-if="showsField('<?= CultBrMetadata::QUOTA_RESERVATION ?>')" :entity="entity" prop="<?= CultBrMetadata::QUOTA_RESERVATION ?>" classes="col-12"></conectaente--quota-reservation>
                    </div>
                </template>
            </mc-card>

            <mc-card v-if="showsField('<?= CultBrMetadata::FUNDING_SOURCES ?>')">
                <template #title>
                    <h3><?php i::_e('Fontes, inscrições e ações afirmativas') ?></h3>
                </template>
                <template #content>
                    <div class="grid-12 conectaente-opportunity-tab__blocos">
                        <conectaente--funding-sources :entity="entity" prop="<?= CultBrMetadata::FUNDING_SOURCES ?>" classes="col-12"></conectaente--funding-sources>
                        <conectaente--registration-channels :entity="entity" prop="<?= CultBrMetadata::REGISTRATION_CHANNELS ?>" classes="col-12"></conectaente--registration-channels>
                        <conectaente--affirmative-actions :entity="entity" prop="<?= CultBrMetadata::AFFIRMATIVE_ACTIONS ?>" classes="col-12"></conectaente--affirmative-actions>
                    </div>
                </template>
            </mc-card>

            <mc-card v-if="showsField('<?= CultBrMetadata::PUBLISHED_AT ?>')">
                <template #title>
                    <h3><?php i::_e('Data de publicação') ?></h3>
                    <p><?php i::_e('Se ficar em branco, é gravada quando o edital selado é publicado. Nos editais publicados antes de receberem o selo, informe a data em que foram publicados.') ?></p>
                </template>
                <template #content>
                    <div class="grid-12">
                        <entity-field :entity="entity" prop="<?= CultBrMetadata::PUBLISHED_AT ?>" :required="isPublished" classes="col-6 sm:col-12"></entity-field>
                    </div>
                </template>
            </mc-card>
        </main>
        <aside>
            <mc-card>
                <template #title>
                    <h3><?php i::_e('Campos pendentes') ?></h3>
                    <p v-if="hasPendingFields && isPublished"><?php i::_e('Publicado, o edital selado por um Ente Federado só pode ser salvo com estes campos preenchidos, porque são eles que o CultBR recebe. A lista considera o que já foi salvo.') ?></p>
                    <p v-else-if="hasPendingFields"><?php i::_e('O edital selado por um Ente Federado só é publicado com estes campos preenchidos, porque são eles que o CultBR recebe. A lista considera o que já foi salvo.') ?></p>
                </template>
                <template #content>
                    <conectaente--opportunity-requirements :missing="missing" :labels="labels" :anchors="anchors" :field-groups="fieldGroups" @group="openGroup = $event" :group-labels="{ core: '<?= i::esc_attr__('Campos nativos') ?>', plugin: '<?= i::esc_attr__('Novos campos') ?>' }" :loading="loading" :failed="loadFailed"></conectaente--opportunity-requirements>
                </template>
            </mc-card>
        </aside>
    </mc-container>
</mc-tab>
