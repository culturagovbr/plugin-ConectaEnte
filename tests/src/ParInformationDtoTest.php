<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Dto\ParInformation;
use Tests\Abstract\TestCase;

class ParInformationDtoTest extends TestCase
{
    const DOCUMENT = '12345678000190';

    function testEmptyWhenDataIsMissing()
    {
        $tree = ParInformation::fromApiListResponse(['pagination' => []], self::DOCUMENT);

        $this->assertSame([], $tree->exercicios);
    }

    function testEmptyWhenDataIsEmpty()
    {
        $tree = ParInformation::fromApiListResponse(['pagination' => [], 'data' => []], self::DOCUMENT);

        $this->assertSame([], $tree->exercicios);
    }

    function testUsesTheFirstMatchWhenMoreThanOneItemMatchesTheSameCnpj()
    {
        $body = ['pagination' => [], 'data' => [
            ['cnpj' => self::DOCUMENT, 'exercicios' => [['id' => 'primeiro']]],
            ['cnpj' => self::DOCUMENT, 'exercicios' => [['id' => 'segundo']]],
        ]];

        $tree = ParInformation::fromApiListResponse($body, self::DOCUMENT);

        $this->assertCount(1, $tree->exercicios);
        $this->assertSame('primeiro', $tree->exercicios[0]->id);
    }
}
