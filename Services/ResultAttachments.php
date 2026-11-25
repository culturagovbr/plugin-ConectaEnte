<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityFile;
use MapasCulturais\i;

/**
 * Os arquivos que respondem as questões de anexo do resultado, guardados como arquivo privado da oportunidade.
 */
final class ResultAttachments
{
    const FILE_GROUP = 'conectaenteResultAttachment';
    const DEFAULT_MAX_MB = 10;

    public function __construct(private ResultAnswers $answers)
    {
    }

    /** Anexa o arquivo enviado à questão; devolve o motivo da recusa, ou null. */
    public function attach(Opportunity $opportunity, ResultForm $form, int $questionId, array $upload): ?string
    {
        $question = $form->question($questionId);
        $current = $this->entries($opportunity, $questionId);
        $error = $this->uploadError($form, $question, $upload, $current);

        if ($error) {
            return $error;
        }

        $file = new OpportunityFile($upload);
        $file->group = self::FILE_GROUP;
        $file->owner = $opportunity;
        $file->save(true);

        $this->store($opportunity, $form, $questionId, [...$current, ['fileId' => (int) $file->id, 'name' => (string) $file->name]]);

        return null;
    }

    /** Tira o arquivo da questão e o apaga. */
    public function detach(Opportunity $opportunity, ResultForm $form, int $questionId, int $fileId): void
    {
        $kept = array_values(array_filter($this->entries($opportunity, $questionId), fn($entry) => (int) $entry['fileId'] !== $fileId));
        $this->store($opportunity, $form, $questionId, $kept);

        $file = App::i()->repo(OpportunityFile::class)->find($fileId);

        if ($file && $file->owner?->id === $opportunity->id && $file->group === self::FILE_GROUP) {
            $file->delete(true);
        }
    }

    /**
     * Os arquivos de cada questão de anexo, com o caminho para o envio.
     *
     * @return array<int, list<array{fileId: int, name: string, path: string, mime: string}>>
     */
    public function files(Opportunity $opportunity): array
    {
        $files = [];

        foreach ($this->answers->saved($opportunity)['answers'] as $questionId => $entries) {
            foreach ($this->isEntryList($entries) ? $entries : [] as $entry) {
                $file = App::i()->repo(OpportunityFile::class)->find((int) $entry['fileId']);

                if ($file && $file->owner?->id === $opportunity->id) {
                    $files[(int) $questionId][] = ['fileId' => (int) $file->id, 'name' => (string) $file->name, 'path' => $file->getPath(), 'mime' => (string) $file->mimeType];
                }
            }
        }

        return $files;
    }

    private function uploadError(ResultForm $form, ?array $question, array $upload, array $current): ?string
    {
        $config = $question['config'] ?? [];
        $formats = array_map('strtolower', (array) ($config['formatos'] ?? []));
        $extension = '.' . strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $maxBytes = (float) ($config['tamanhoMaxMb'] ?? self::DEFAULT_MAX_MB) * 1024 * 1024;

        return match (true) {
            !$question || !in_array($question['tipo_questao'] ?? null, ResultValidator::ATTACHMENT_TYPES, true) => i::__('Esta questão não recebe arquivo.'),
            $form->isAttachmentUnavailable($question) => i::__('Este edital envia a planilha de contemplados, e o CultBR não recebe arquivos junto com ela.'),
            ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string) ($upload['tmp_name'] ?? '')) => i::__('O arquivo não chegou ao servidor.'),
            $formats && !in_array($extension, $formats, true) => sprintf(i::__('Envie um arquivo %s.'), implode(', ', $formats)),
            (int) ($upload['size'] ?? 0) > $maxBytes => sprintf(i::__('O arquivo passa de %s MB.'), $config['tamanhoMaxMb'] ?? self::DEFAULT_MAX_MB),
            $current && empty($config['multiplos']) && ($question['tipo_questao'] ?? null) === 'anexo' => i::__('Esta questão aceita um arquivo só; remova o atual antes de enviar outro.'),
            default => null,
        };
    }

    private function entries(Opportunity $opportunity, int $questionId): array
    {
        $entries = $this->answers->saved($opportunity)['answers'][$questionId] ?? [];

        return $this->isEntryList($entries) ? $entries : [];
    }

    private function isEntryList(mixed $entries): bool
    {
        return is_array($entries) && $entries && array_is_list($entries) && isset($entries[0]['fileId']);
    }

    private function store(Opportunity $opportunity, ResultForm $form, int $questionId, array $entries): void
    {
        $saved = $this->answers->saved($opportunity);
        $saved['answers'][$questionId] = $entries ?: null;

        // a atribuição passa pelo serializador do tipo json; setMetadata gravaria o array cru
        $opportunity->{CultBrMetadata::RESULT_FORM} = ['versionId' => $form->versionId(), 'answers' => array_filter($saved['answers'], fn($value) => $value !== null)];
        $opportunity->saveMetadata();
    }
}
