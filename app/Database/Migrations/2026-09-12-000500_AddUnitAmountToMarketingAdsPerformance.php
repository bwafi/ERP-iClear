<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Performa Ads menjadi sumber TUNGGAL data iklan:
 *  - tambah `unit_id` (cabang) dan `amount` (biaya harian / spending);
 *  - migrasi data spending lama dari marketing_ads_cost;
 *  - drop tabel marketing_ads_cost (fungsi Biaya Iklan digabung ke Performa Ads).
 */
class AddUnitAmountToMarketingAdsPerformance extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1) Kolom baru di marketing_ads_performance.
        if (!$db->fieldExists('unit_id', 'marketing_ads_performance')) {
            $this->forge->addColumn('marketing_ads_performance', [
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'channel_id',
                ],
            ]);
        }
        if (!$db->fieldExists('amount', 'marketing_ads_performance')) {
            $this->forge->addColumn('marketing_ads_performance', [
                'amount' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'daily_budget',
                ],
            ]);
        }

        // 2) Migrasi data spending lama → amount di marketing_ads_performance.
        if ($db->tableExists('marketing_ads_cost')) {
            $old = $db->table('marketing_ads_cost')->get()->getResultArray();
            foreach ($old as $row) {
                $exists = $db->table('marketing_ads_performance')
                    ->where('period_month', (int)$row['period_month'])
                    ->where('period_year', (int)$row['period_year'])
                    ->where('tanggal', $row['tanggal'] ?: null)
                    ->where('channel_id', $row['channel_id'] ?: null)
                    ->where('campaign', (string)$row['campaign'])
                    ->where('unit_id', null)
                    ->get()->getRow();
                if ($exists) {
                    $db->table('marketing_ads_performance')
                        ->where('id', $exists->id)
                        ->set('amount', (float)$row['amount'])
                        ->update();
                } else {
                    $db->table('marketing_ads_performance')->insert([
                        'period_month' => (int)$row['period_month'],
                        'period_year'  => (int)$row['period_year'],
                        'tanggal'      => $row['tanggal'] ?: null,
                        'channel_id'   => $row['channel_id'] ?: null,
                        'unit_id'      => null,
                        'campaign'     => (string)$row['campaign'],
                        'amount'       => (float)$row['amount'],
                        'daily_budget' => null,
                        'ppn'          => null,
                        'objective'    => null,
                        'reach'        => null,
                        'impression'   => null,
                        'klik'         => null,
                        'hasil'        => null,
                        'note'         => $row['note'] ?: null,
                        'created_by'   => $row['created_by'] ?: null,
                    ]);
                }
            }

            // 3) Tabel biaya iklan lama dibuang — spending kini di Performa Ads.
            $this->forge->dropTable('marketing_ads_cost', true);
        }
    }

    public function down()
    {
        // Non-destruktif: data Performa Ads tidak dikembalikan ke tabel lama.
    }
}