<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class CustomerSatisfactionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testKepalaTokoSeesInputForm(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->get('penilaian/customer_satisfaction');
        $result->assertStatus(200);

        $body = (string)$result->response()->getBody();
        $this->assertStringContainsString('Input Review Google Maps', $body);
        $this->assertStringContainsString('jumlah_review', $body);
        $this->assertStringContainsString('Rekap Bulanan', $body);
    }

    public function testSpvViewOnlyReadOnly(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 56,
            'ID_UNIT'   => 4,
            'ID_JABATAN' => 40,
        ]);

        $result = $this->get('penilaian/customer_satisfaction');
        $result->assertStatus(200);

        $body = (string)$result->response()->getBody();
        $this->assertStringNotContainsString('Input Review Google Maps', $body);
        $this->assertStringContainsString('Rekap Bulanan', $body);
    }

    public function testKepalaTokoCannotInputUnitLain(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->post('penilaian/customer_satisfaction/save', [
            'tanggal'       => '2026-09-01',
            'id_unit'       => 2,
            'jumlah_review' => 1,
        ]);
        $result->assertSessionHas('error');
    }

    public function testKepalaTokoSaveValid(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => 1,
            'ID_JABATAN' => 41,
        ]);

        $result = $this->post('penilaian/customer_satisfaction/save', [
            'tanggal'       => '2026-09-11',
            'id_unit'       => 1,
            'jumlah_review' => 0,
        ]);
        $result->assertSessionHas('message');
    }
}