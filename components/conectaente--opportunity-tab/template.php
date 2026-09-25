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
    conectaente--opportunity-requirements
    conectaente--targeting-multiselect
    entity-field
    mc-card
    mc-container
    mc-tab
');
?>
<mc-tab v-if="isSealed" label="<?php i::esc_attr_e('CultBR') ?>" slug="cultbr">
    <mc-container>
        <main class="conectaente-opportunity-tab">
            <mc-card>
                <template #title>
                    <h3><?php i::_e('Identificação do edital') ?></h3>
                </template>
                <template #content>
                    <div class="grid-12">
                        <entity-field :entity="entity" prop="<?= CultBrMetadata::EXECUTION_TYPE ?>" :required="true" classes="col-12"></entity-field>
                        <entity-field v-if="hasLegalEntity" :entity="entity" prop="<?= CultBrMetadata::LEGAL_ENTITY_TYPES ?>" type="checklist" :required="true" classes="col-12"></entity-field>
                    </div>
                </template>
            </mc-card>

            <mc-card>
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

            <mc-card>
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
                    <conectaente--opportunity-requirements :missing="missing" :labels="labels" :loading="loading" :failed="loadFailed"></conectaente--opportunity-requirements>
                </template>
            </mc-card>
        </aside>
    </mc-container>
</mc-tab>
