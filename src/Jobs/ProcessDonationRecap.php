<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Inisiatif\DonationRecap\Models\DonationRecap;
use Inisiatif\DonationRecap\DonationRecap as Recap;

final class ProcessDonationRecap implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DonationRecap $donationRecap,
    ) {}

    public function handle(): void
    {
        $this->donationRecap->recordHistory('Memproses pembuatan rekap donasi');

        $donors = $this->donationRecap->donors()->get();

        foreach ($donors as $donor) {
            $jobChain = [
                new BuildDonationRecapDetail($this->donationRecap, $donor),
                new GenerateDonorRecapFile($this->donationRecap, $donor),
                new CombineDonorRecapFile($this->donationRecap, $donor),
                new IncreaseProgressDonationRecap($this->donationRecap, $donor),
                new CheckDonationRecapProgress($this->donationRecap, $donor),
            ];

            $this->dispatchChain($jobChain);
        }
    }

    public function uniqueId(): string
    {
        return $this->donationRecap->getKey();
    }

    protected function dispatchChain(array $jobs): void
    {
        $connectionName = Recap::getQueueConnection();

        $queueName = Recap::getQueueName();

        Bus::chain($jobs)->onConnection($connectionName)->onQueue($queueName)->dispatch();
    }
}
