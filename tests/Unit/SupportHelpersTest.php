<?php

namespace Tests\Unit;

use App\Services\TerminalReportService;
use App\Support\Greeting;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class SupportHelpersTest extends TestCase
{
 

    public function test_amount_in_words_for_receipts(): void
    {
        $this->assertSame('One Thousand Two Hundred and Fifty Ghana Cedis, Fifty Pesewas Only', Money::words(1250.50));
        $this->assertSame('One Ghana Cedi Only', Money::words(1));
        $this->assertSame('Zero Ghana Cedis, Five Pesewas Only', Money::words(0.05));
    }

    public function test_positions_use_competition_ranking(): void
    {
        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 3, 'd' => 4], TerminalReportService::rank(['c' => 70, 'a' => 90, 'd' => 50, 'b' => 90]));
        $this->assertSame('1st', TerminalReportService::ordinal(1));
        $this->assertSame('12th', TerminalReportService::ordinal(12));
        $this->assertSame('22nd', TerminalReportService::ordinal(22));
    }
}
