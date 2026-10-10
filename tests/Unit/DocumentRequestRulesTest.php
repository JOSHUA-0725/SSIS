<?php

namespace Tests\Unit;

use App\Services\DocumentRequestService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentRequestRulesTest extends TestCase
{
    public function test_fee_is_fee_per_copy_times_copies(): void
    {
        $this->assertSame(300.00, DocumentRequestService::computeFee(150.00, 2));
    }

    #[DataProvider('invalidCopies')]
    public function test_copies_outside_one_to_ten_are_rejected(int $copies): void
    {
        $this->expectException(InvalidArgumentException::class);
        DocumentRequestService::computeFee(150.00, $copies);
    }

    public static function invalidCopies(): array
    {
        return [[0], [11], [-1]];
    }

    public function test_payment_cannot_be_skipped(): void
    {
        $this->assertFalse(DocumentRequestService::canTransition('Pending Payment', 'Processing'));
        $this->assertFalse(DocumentRequestService::canTransition('Pending Payment', 'Released'));
        $this->assertTrue(DocumentRequestService::canTransition('Pending Payment', 'Paid'));
    }

    public function test_request_follows_the_processing_order(): void
    {
        $this->assertTrue(DocumentRequestService::canTransition('Paid', 'Processing'));
        $this->assertTrue(DocumentRequestService::canTransition('Processing', 'Ready for Pickup'));
        $this->assertTrue(DocumentRequestService::canTransition('Ready for Pickup', 'Released'));
        $this->assertFalse(DocumentRequestService::canTransition('Paid', 'Released'));
    }

    public function test_closed_requests_cannot_change(): void
    {
        foreach (['Released', 'Rejected', 'Cancelled'] as $closed) {
            $this->assertFalse(DocumentRequestService::canTransition($closed, 'Processing'));
        }
    }
}
