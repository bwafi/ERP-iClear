<?php

namespace Tests\unit;

use App\Services\Inventory\InventoryLedgerBridge;
use PHPUnit\Framework\TestCase;

final class InventoryLedgerBridgeTest extends TestCase
{
    public function testFirstDocumentedIncomingCanStartAnEmptyLedger(): void
    {
        $movement = ['sumber' => 'Pembelian', 'referensi_id' => 10, 'masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 100];
        InventoryLedgerBridge::assertUntrackedStart([$movement], $movement, 'incoming', 2, 2, null, 100);
        $this->assertTrue(true);
    }

    public function testHistoricalMovementsCannotBeSeededFromCurrentBalance(): void
    {
        $movement = ['sumber' => 'Pembelian', 'referensi_id' => 10, 'masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 100];
        $this->expectException(\RuntimeException::class);
        InventoryLedgerBridge::assertUntrackedStart([$movement, $movement], $movement, 'incoming', 2, 2, null);
    }

    public function testFirstOutgoingCannotInferLegacyCost(): void
    {
        $movement = ['sumber' => 'Penjualan/Service', 'referensi_id' => 11, 'masuk' => 0, 'keluar' => 1, 'hpp_satuan' => 100];
        $this->expectException(\RuntimeException::class);
        InventoryLedgerBridge::assertUntrackedStart([$movement], $movement, 'outgoing', 1, 0, null);
    }

    public function testNonzeroBalanceWithoutEntriesCannotBeTrusted(): void
    {
        $movement = ['sumber' => 'Pembelian', 'referensi_id' => 10, 'masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 100];
        $this->expectException(\RuntimeException::class);
        InventoryLedgerBridge::assertUntrackedStart([$movement], $movement, 'incoming', 2, 2,
            ['quantity' => 0, 'value' => 100]);
    }

    public function testMissingOrChangedHistoricalCostCannotStartLedger(): void
    {
        $movement = ['sumber' => 'Pembelian', 'referensi_id' => 10, 'masuk' => 2, 'keluar' => 0, 'hpp_satuan' => null];
        $this->expectException(\RuntimeException::class);
        InventoryLedgerBridge::assertUntrackedStart([$movement], $movement, 'incoming', 2, 2, null, 100);
    }

    public function testChangedSourceCostCannotStartLedger(): void
    {
        $movement = ['sumber' => 'Pembelian', 'referensi_id' => 10, 'masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 90];
        $this->expectException(\RuntimeException::class);
        InventoryLedgerBridge::assertUntrackedStart([$movement], $movement, 'incoming', 2, 2, null, 100);
    }
}
