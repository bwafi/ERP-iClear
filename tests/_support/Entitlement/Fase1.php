<?php

namespace Tests\Support\Entitlement;

/**
 * Konstanta fixture entitlement Fase 1.
 *
 * Dipisah dari trait karena PHP 7.4 tidak mengizinkan konstanta di dalam
 * trait (fitur itu baru ada di PHP 8.2).Test yang butuh konstanta ini
 * mengimpor kelas ini secara langsung.
 *
 * PETA ENTITLEMENT SETELAH M1-M4 (batch 23)
 * ----------------------------------------
 *   akun 16  CV          idbank=2  shared  unit_id=NULL  alokasi [1,2]
 *   akun 3   ALFARIZKI   idbank=5  privat  unit_id=3     alokasi [3]
 *   akun 15  SABRINA     idbank=1  privat  unit_id=4     alokasi [4]
 *   akun 1   FINANCE     idbank=3  shared  unit_id=NULL  TANPA alokasi
 *   akun 4   FARA        idbank=4  nonaktif             (tidak disentuh)
 */
final class Fase1
{
    public const AKUN_CV        = 16;
    public const AKUN_ALFARIZKI = 3;
    public const AKUN_SABRINA   = 15;
    public const AKUN_FINANCE   = 1;
    public const AKUN_FARA      = 4;
    public const AKUN_KAS_1     = 5;

    /** idbank adalah identifier STRING, tidak boleh di-(int)-cast. */
    public const IDBANK_CV        = '2';
    public const IDBANK_SABRINA   = '1';
    public const IDBANK_FINANCE   = '3';
    public const IDBANK_FARA      = '4';
    public const IDBANK_ALFARIZKI = '5';

    /** Unit 5 = Genteng: belum punya rekening bank resmi. */
    public const UNIT_GENTENG = 5;

    /** Unit 50 = Head Office. */
    public const UNIT_HO = 50;

    /** Jabatan: 0 = ROOT (lintas unit), 1 = Finance, 35 = Kasir unit. */
    public const ROLE_ROOT    = 0;
    public const ROLE_FINANCE = 1;
    public const ROLE_KASIR   = 35;

    /** Rekening yang WAJIB punya statement VERIFIED sebelum mutasi baru. */
    public const WAJIB_STATEMENT = [
        self::AKUN_CV,
        self::AKUN_ALFARIZKI,
        self::AKUN_SABRINA,
    ];

    /** Unit cabang yang punya rekening operasional. */
    public const UNIT_OPERASIONAL = [1, 2, 3, 4];

    /** Unit yang TIDAK punya rekening operasional. */
    public const UNIT_TANPA_REKENING = [self::UNIT_GENTENG, self::UNIT_HO];
}