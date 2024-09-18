<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Inisiatif\DonationRecap\Models\DonationRecap;
use Inisiatif\DonationRecap\Enums\ProcessingState;
use Inisiatif\DonationRecap\Enums\DonationRecapState;
use Inisiatif\DonationRecap\Models\DonationRecapDonor;
use Inisiatif\DonationRecap\Builders\DonationRecapDetailBuilder;

final class BuildDonationRecapDetail implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DonationRecap $donationRecap,
        public readonly DonationRecapDonor $donor,
    ) {}

    public function handle(DonationRecapDetailBuilder $builder): void
    {
        if ($this->donationRecap->inState(DonationRecapState::new)) {
            $this->donor->state(ProcessingState::collecting);

            $builder->buildFor($this->donationRecap, $this->donor);

            $this->donor->state(ProcessingState::collected);

            $this->donationRecap->recordHistory(
                \sprintf('Mengambil detail donasi untuk %s', $this->donor->getAttribute('donor_name')),
                $this->donor->getAttribute('donor_id')
            );
        }
    }

    public function uniqueId(): string
    {
        return $this->donationRecap->getKey().'|'.$this->donor->getKey();
    }

    public function failed(Throwable $exception): void
    {
        $this->donationRecap->state(DonationRecapState::failure);

        $this->donationRecap->recordHistory($exception->getMessage(), $this->donor->getAttribute('donor_id'));

        \report($exception);
    }
}
