<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\ResultPayload;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultForm;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RegistrationDirector;

/**
 * O corpo do resultado no formato aceito pela Conecta Ente em 19/11 (`post-oportunidades-id-resultado.json`).
 */
class ResultPayloadTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RegistrationDirector;

    const ACCEPTED_DATE = '2026-10-16T10:00:00-03:00';
    const COMMITTEE = [['cpf' => '529.982.247-25', 'nome' => 'Fulana de Tal Teste', 'vinculo' => 'servidor público', 'categoria_grupo' => 'Todas as propostas']];
    const DECLARATION = 'Declaro, sob as penas da legislação em vigor, que as informações prestadas são verdadeiras, que a execução dos recursos está em conformidade com as regras e os normativos da Política Nacional Aldir Blanc de Fomento à Cultura e que este edital, bem como os demais procedimentos a ele relacionados, observa o Manual de Marcas disponibilizado pelo Ministério da Cultura, nos termos do art. 22 do Decreto nº 11.740/2023.';

    private ?ResultForm $form = null;

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testTheBodyHasTheShapeOfTheAcceptedOne()
    {
        $opportunity = $this->answeredEdital();

        $payload = $this->build($opportunity)['payload'];
        $byQuestion = array_column(array_map(fn($response) => [$response['id_par_questao'], $response['resposta']['resposta']], $payload['respostas']), 1, 0);

        $this->assertSame(['id_tipo', 'id_status', 'data_publicacao', 'respostas'], array_keys($payload), 'Sem anexos e sem tipo_planilha, como o corpo aceito.');
        $this->assertSame(3, $payload['id_status']);
        $this->assertSame('Termo de Execução Cultural (Lei nº 14.903/2024)', $byQuestion[13915], 'A escolha viaja pelo rótulo.');
        $this->assertIsInt($byQuestion[13918]);
        $this->assertSame(2, $byQuestion[13919], 'Os contemplados vêm da fase final.');
        $this->assertSame(15000, $byQuestion[13920]);
        $this->assertSame(['Heteroidentificação para pessoas negras'], $byQuestion[13921]);
        $this->assertTrue($byQuestion[13923]);
        $this->assertSame('2026-12-15', $byQuestion[13924]);
        $this->assertSame(self::COMMITTEE, $byQuestion[13933], 'A tabela vai como lista de objetos pela chave da coluna.');
        $this->assertSame([self::DECLARATION], $byQuestion[13934]);
        foreach ($payload['respostas'] as $response) {
            $this->assertSame(['id_par_questao', 'resposta'], array_keys($response));
        }
    }

    function testAnInvisibleQuestionStaysOutOfTheBody()
    {
        $opportunity = $this->answeredEdital([13926 => 'Comentário que a regra esconde.']);

        $ids = array_column($this->build($opportunity)['payload']['respostas'], 'id_par_questao');

        $this->assertNotContains(13926, $ids, 'Com a 13923 = sim a 13926 é invisível, e o servidor não a cobra.');
        $this->assertNotContains(13916, $ids, 'O espelho só leitura sem valor também fica fora.');
    }

    function testTheComplementTravelsWithTheLabel()
    {
        $opportunity = $this->answeredEdital([13915 => ['id' => 'fe_outros', 'complemento' => 'Termo de cessão de uso']]);

        $byQuestion = array_column(array_map(fn($response) => [$response['id_par_questao'], $response['resposta']['resposta']], $this->build($opportunity)['payload']['respostas']), 1, 0);

        $this->assertSame('Outros: Termo de cessão de uso', $byQuestion[13915]);
    }

    function testTheRetificationDateIsTheMomentOfTheSend()
    {
        $date = new \DateTime($this->build($this->answeredEdital(), ResultPayload::TYPE_CORRECTED)['payload']['data_publicacao']);

        $this->assertEqualsWithDelta(time(), $date->getTimestamp(), 5);
    }

    function testTheHashIgnoresTypeStatusAndDateAndFollowsTheContent()
    {
        $opportunity = $this->answeredEdital();

        $final = $this->build($opportunity, ResultPayload::TYPE_FINAL)['hash'];
        $corrected = $this->build($this->reloaded($opportunity), ResultPayload::TYPE_CORRECTED)['hash'];
        Plugin::instance()->resultAnswers()->save($this->reloaded($opportunity), $this->form(), [13925 => 'Outro comentário.']);
        $changed = $this->build($this->reloaded($opportunity), ResultPayload::TYPE_FINAL)['hash'];

        $this->assertSame($final, $corrected, 'O mesmo conteúdo como retificado não é reenvio novo.');
        $this->assertNotSame($final, $changed);
    }

    function testAnIncompleteSpreadsheetBlocksTheBody()
    {
        $opportunity = $this->answeredEdital(model: json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/modelo-planilha.com-planilha.json'), true));

        $built = $this->build($opportunity);

        $this->assertNull($built['payload'], 'Mandar sem linhas em silêncio é o que o CultEditais faz.');
        $this->assertNotNull($built['error']);
    }

    function testAnAttachmentGoesByTheFileNameWithTheFile()
    {
        $opportunity = $this->answeredEdital([13915 => 'fe_bolsa']);
        $path = sys_get_temp_dir() . '/' . uniqid('anexo-') . '.csv';
        file_put_contents($path, "a;b\n1;2\n");
        Plugin::instance()->resultAttachments()->attach($this->reloaded($opportunity), $this->form(), 13931, ['name' => 'planilha-bolsa.csv', 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]);

        $built = $this->build($this->reloaded($opportunity));
        $byQuestion = array_column(array_map(fn($response) => [$response['id_par_questao'], $response['resposta']['resposta']], $built['payload']['respostas']), 1, 0);

        $this->assertSame('planilha-bolsa.csv', $byQuestion[13931], 'É a forma da prova U2.');
        $this->assertSame('planilha-bolsa.csv', $built['files'][0]['name']);
    }

    function testSeveralFilesOfAnAttachmentThatTakesManyAreAllNamed()
    {
        $opportunity = $this->answeredEdital();
        $form = $this->form();

        foreach (['documento-1.csv', 'documento-2.csv'] as $name) {
            $path = sys_get_temp_dir() . '/' . uniqid('anexo-') . '.csv';
            file_put_contents($path, "a;b\n1;2\n");
            Plugin::instance()->resultAttachments()->attach($this->reloaded($opportunity), $form, 13929, ['name' => $name, 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]);
        }

        $built = $this->build($this->reloaded($opportunity));
        $byQuestion = array_column(array_map(fn($response) => [$response['id_par_questao'], $response['resposta']['resposta']], $built['payload']['respostas']), 1, 0);

        $this->assertSame(['documento-1.csv', 'documento-2.csv'], $byQuestion[13929], 'Os dois vão em arquivos; a resposta cita os dois.');
        $this->assertCount(2, $built['files']);
    }

    function testLinesWithAnAttachmentAreRefusedBeforeTheSend()
    {
        $opportunity = $this->answeredEdital([13915 => 'fe_bolsa'], ['possui_planilha' => true, 'campos' => [['ordem' => 1, 'coluna' => 'nome', 'nome' => 'Nome', 'tipo' => 'texto', 'obrigatoriedade' => 'opcional']]]);
        $path = sys_get_temp_dir() . '/' . uniqid('anexo-') . '.csv';
        file_put_contents($path, "a;b\n1;2\n");
        Plugin::instance()->resultAttachments()->attach($this->reloaded($opportunity), $this->form(), 13931, ['name' => 'planilha-bolsa.csv', 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)]);

        $built = $this->build($this->reloaded($opportunity));

        $this->assertNull($built['payload'], 'O multipart não leva linhas: o resultado sairia sem os contemplados.');
        $this->assertStringContainsString('junto com anexos', $built['error']);
    }

    private function answeredEdital(array $answers = [], array $model = ['possui_planilha' => false, 'campos' => []]): Opportunity
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->saveMetadata();
        Plugin::instance()->transport = FakeTransport::replying(200, $model);

        foreach ($this->registrationDirector->createSentRegistrations($this->reloaded($opportunity)->lastPhase, 2, proponent_type: 'Pessoa Física') as $registration) {
            $registration->status = Registration::STATUS_APPROVED;
            $registration->save(true);
        }

        Plugin::instance()->resultAnswers()->save($this->reloaded($opportunity), $this->form(), $answers + [
            13915 => 'fe_tec',
            13920 => 15000,
            13921 => ['heteroidentificacao'],
            13923 => true,
            13924 => '2026-12-15',
            13925 => 'Execução realizada conforme o planejado, sem intercorrências.',
            13933 => self::COMMITTEE,
            13934 => ['declaracao_integral'],
        ]);

        return $this->reloaded($opportunity);
    }

    private function build(Opportunity $opportunity, int $type = ResultPayload::TYPE_FINAL): array
    {
        return Plugin::instance()->resultPayload()->build($this->reloaded($opportunity), $this->form(), $type);
    }

    private function form(): ResultForm
    {
        return $this->form ??= new ResultForm(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true));
    }
}
