<?php

namespace Tests\Feature\Padron;

use App\Models\FolioSequence;
use App\Services\FolioGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FolioGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_consecutive_padded_folios(): void
    {
        $folios = app(FolioGenerator::class);

        $this->assertSame('REC-00000001', $folios->next(FolioGenerator::RECEIPT));
        $this->assertSame('REC-00000002', $folios->next(FolioGenerator::RECEIPT));
        $this->assertSame('COM-000001', $folios->next(FolioGenerator::MERCHANT));
    }

    public function test_sequence_is_created_when_missing(): void
    {
        FolioSequence::query()->delete();

        $this->assertSame('COM-000001', app(FolioGenerator::class)->next(FolioGenerator::MERCHANT));
    }

    public function test_rolled_back_transaction_releases_the_number(): void
    {
        $folios = app(FolioGenerator::class);

        try {
            DB::transaction(function () use ($folios) {
                $folios->next(FolioGenerator::RECEIPT);
                throw new RuntimeException('falla');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame('REC-00000001', $folios->next(FolioGenerator::RECEIPT));
    }

    public function test_many_folios_are_unique(): void
    {
        $folios = app(FolioGenerator::class);
        $generated = collect(range(1, 200))->map(fn () => $folios->next(FolioGenerator::RECEIPT));

        $this->assertCount(200, $generated->unique());
        $this->assertSame('REC-00000200', $generated->last());
    }
}
