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
 * Os arquivos das questões de anexo do resultado, sobre a estrutura 541.
 */
class ResultAttachmentsTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const BOLSA_SPREADSHEET = 13931;
    const TEC_SPREADSHEET = 13930;
    const EXECUTION_FORM = 13915;

    private ?ResultForm $form = null;

    function testACsvIsAttachedToTheQuestionAsAPrivateFile()
    {
        $opportunity = $this->edital();

        $this->assertNull($this->attachments()->attach($opportunity, $this->form(), self::BOLSA_SPREADSHEET, $this->upload('planilha.csv')));

        $entries = Plugin::instance()->resultAnswers()->saved($this->reloaded($opportunity))['answers'][self::BOLSA_SPREADSHEET];
        $file = $this->app->repo(OpportunityFile::class)->find($entries[0]['fileId']);
        $this->assertSame('planilha.csv', $entries[0]['name']);
        $this->assertSame(ResultAttachments::FILE_GROUP, $file->group);
        $this->assertTrue($file->private, 'A planilha de Bolsa traz CPF de contemplados.');
    }

    function testAFormatOutsideTheQuestionIsRefused()
    {
        $this->assertNotNull($this->attachments()->attach($this->edital(), $this->form(), self::BOLSA_SPREADSHEET, $this->upload('planilha.pdf')));
    }

    function testAFileAboveTheLimitIsRefused()
    {
        $upload = $this->upload('planilha.csv');
        $upload['size'] = 11 * 1024 * 1024;

        $this->assertNotNull($this->attachments()->attach($this->edital(), $this->form(), self::BOLSA_SPREADSHEET, $upload));
    }

    function testASecondFileIsRefusedWhenTheQuestionTakesOne()
    {
        $opportunity = $this->edital();
        $this->attachments()->attach($opportunity, $this->form(), self::BOLSA_SPREADSHEET, $this->upload('planilha.csv'));

        $this->assertNotNull($this->attachments()->attach($this->reloaded($opportunity), $this->form(), self::BOLSA_SPREADSHEET, $this->upload('outra.csv')));
    }

    function testAQuestionThatIsNotAnAttachmentTakesNoFile()
    {
        $this->assertNotNull($this->attachments()->attach($this->edital(), $this->form(), self::EXECUTION_FORM, $this->upload('planilha.csv')));
    }

    function testDetachingRemovesTheEntryAndTheFile()
    {
        $opportunity = $this->edital();
        $this->attachments()->attach($opportunity, $this->form(), self::BOLSA_SPREADSHEET, $this->upload('planilha.csv'));
        $fileId = Plugin::instance()->resultAnswers()->saved($this->reloaded($opportunity))['answers'][self::BOLSA_SPREADSHEET][0]['fileId'];

        $this->attachments()->detach($this->reloaded($opportunity), $this->form(), self::BOLSA_SPREADSHEET, $fileId);

        $this->assertArrayNotHasKey(self::BOLSA_SPREADSHEET, Plugin::instance()->resultAnswers()->saved($this->reloaded($opportunity))['answers']);
        $this->assertNull($this->app->repo(OpportunityFile::class)->find($fileId));
    }

    function testAVisibleRequiredAttachmentIsMissingAndTheDispensedOneIsNot()
    {
        $missing = (new ResultValidator($this->form()))->missing([self::EXECUTION_FORM => 'fe_bolsa'], []);

        $this->assertContains(self::BOLSA_SPREADSHEET, $missing, 'O CultBR recusa o resultado de Bolsa sem a planilha.');
        $this->assertNotContains(self::TEC_SPREADSHEET, $missing, 'A planilha da 13930 vem em linhas, e o contrato a dispensa.');
    }

    function testTheFormAutosaveCannotPointAnAttachmentAtAnyFile()
    {
        $opportunity = $this->edital();

        Plugin::instance()->resultAnswers()->save($opportunity, $this->form(), [self::BOLSA_SPREADSHEET => [['fileId' => 1, 'name' => 'alheio.csv']]]);

        $this->assertArrayNotHasKey(self::BOLSA_SPREADSHEET, Plugin::instance()->resultAnswers()->saved($this->reloaded($opportunity))['answers']);
    }

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

    function testTheHistoryNamesTheFilesOfAMultipartSend()
    {
        $transport = FakeTransport::replying(201, ['id_par_edital_resultado' => 1]);
        $client = new Client('https://ente.example', $transport);

        $client->sendResult('um-token', 53781, ['id_tipo' => 2, 'respostas' => []], null, [
            ['path' => $this->tempFile('planilha.csv'), 'name' => 'planilha.csv', 'mime' => 'text/csv', 'description' => 'Planilha de resultados — Bolsa'],
        ]);

        $this->assertSame([['nome' => 'planilha.csv', 'descricao' => 'Planilha de resultados — Bolsa']], $client->exchanges()[0]->requestPayload['arquivos']);
    }

    function testWithoutFilesTheResultGoesAsJson()
    {
        $transport = FakeTransport::replying(201, ['id_par_edital_resultado' => 1]);

        (new Client('https://ente.example', $transport))->sendResult('um-token', 53781, ['id_tipo' => 2]);

        $this->assertSame([], $transport->sentMultiparts);
        $this->assertSame(['id_tipo' => 2], $transport->sentBodies[0]);
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->edital();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->POST('conectaente', 'resultAttachmentDelete', [$opportunity->id], ['questionId' => 1, 'fileId' => 1], ajax: true)));
    }

    function testAFileAboveTheServerLimitIsNamedAsTooBig()
    {
        $error = $this->attachments()->attach($this->edital(), $this->form(), self::BOLSA_SPREADSHEET, ['name' => 'planilha.csv', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]);

        $this->assertStringContainsString('tamanho', (string) $error);
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
