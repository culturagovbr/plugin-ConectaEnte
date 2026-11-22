<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultAttachments;
use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultValidator;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityFile;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * O envio do resultado com arquivo, em multipart.
 */
class ResultAttachmentsTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const BOLSA_SPREADSHEET = 13931;
    const TEC_SPREADSHEET = 13930;
    const EXECUTION_FORM = 13915;

    private ?ResultForm $form = null;

    function testWithFilesTheResultGoesAsMultipart()
    {
        $transport = FakeTransport::replying(201, ['id_par_edital_resultado' => 1]);
        $path = $this->tempFile('planilha.csv');

        (new Client('https://ente.example', $transport))->sendResult('um-token', 53781, ['id_tipo' => 2, 'id_status' => 3, 'respostas' => [['id_par_questao' => self::BOLSA_SPREADSHEET, 'resposta' => ['resposta' => 'planilha.csv']]]], null, [
            ['path' => $path, 'name' => 'planilha.csv', 'mime' => 'text/csv', 'description' => 'Planilha de resultados — Bolsa'],
        ]);

        $multipart = $transport->sentMultiparts[0];
        $this->assertContains(['id_tipo', '2'], $multipart['fields']);
        $this->assertContains(['descricoes', 'Planilha de resultados — Bolsa'], $multipart['fields']);
        $this->assertSame('arquivos', $multipart['files'][0]['name'], 'É o campo que o contrato nomeia, repetido por arquivo.');
        $respostas = json_decode(array_values(array_filter($multipart['fields'], fn($field) => $field[0] === 'respostas_json'))[0][1], true);
        $this->assertSame('planilha.csv', $respostas[0]['resposta']['resposta']);
    }

    function testWithoutFilesTheResultGoesAsJson()
    {
        $transport = FakeTransport::replying(201, ['id_par_edital_resultado' => 1]);

        (new Client('https://ente.example', $transport))->sendResult('um-token', 53781, ['id_tipo' => 2]);

        $this->assertSame([], $transport->sentMultiparts);
        $this->assertSame(['id_tipo' => 2], $transport->sentBodies[0]);
    }

    private function edital(): Opportunity
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->saveMetadata();

        return $this->reloaded($opportunity);
    }

    private function upload(string $name): array
    {
        $path = $this->tempFile($name);

        return ['name' => $name, 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    }

    private function tempFile(string $name): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('anexo-') . '-' . $name;
        file_put_contents($path, "tipo_agente;cpf\nPessoa física;52998224725\n");

        return $path;
    }

    private function form(): ResultForm
    {
        return $this->form ??= new ResultForm(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true));
    }

    private function attachments(): ResultAttachments
    {
        return Plugin::instance()->resultAttachments();
    }
}
