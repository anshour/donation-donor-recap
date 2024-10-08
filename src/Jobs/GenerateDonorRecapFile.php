<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Jobs;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Inisiatif\DonationRecap\GeneratePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Inisiatif\DonationRecap\Models\DonationRecap;
use Inisiatif\DonationRecap\Enums\ProcessingState;
use Inisiatif\DonationRecap\DonationRecap as Recap;
use Inisiatif\DonationRecap\Enums\DonationRecapState;
use Inisiatif\DonationRecap\Models\DonationRecapDonor;

final class GenerateDonorRecapFile implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DonationRecap $donationRecap,
        public readonly DonationRecapDonor $donor,
    ) {}

    public function handle(): void
    {
        if ($this->donor->inState(ProcessingState::collected)) {
            $this->donationRecap->recordHistory(
                \sprintf('Membuat file rekap donasi untuk %s', $this->donor->getAttribute('donor_name')),
                $this->donor->getAttribute('donor_id')
            );

            $this->donor->state(ProcessingState::generating);

            $path = \sprintf('%s/%s/%s.pdf', Recap::getFileGeneratedBasePath(), now()->year, Str::random(64));

            $donorId = $this->donor->getAttribute('donor_id');
            $content = GeneratePdf::view('recap::recap', [
                'donor' => $this->donor,
                'recap' => $this->donationRecap,
                'items' => $this->donationRecap->getItemCollection($donorId),
                'summaries' => $this->donationRecap->getCategoryItemsSummaries($donorId),
            ])->base64pdf();

            if (Str::isJson($content)) {
                $content = Arr::get(\json_decode($content, true, 512, JSON_THROW_ON_ERROR), 'result');
            }

            Storage::disk(Recap::getDefaultFileDisk())->put($path, \base64_decode($content));

            $this->donor->update([
                'disk' => Recap::getDefaultFileDisk(),
                'file_path' => $path,
            ]);

            $this->donor->state(ProcessingState::generated);
        }
    }

    public function uniqueId(): string
    {
        return $this->donationRecap->getKey() . '|' . $this->donor->getKey();
    }

    public function failed(Throwable $exception): void
    {
        $this->donationRecap->state(DonationRecapState::failure);

        $this->donationRecap->recordHistory($exception->getMessage(), $this->donor->getAttribute('donor_id'));

        \report($exception);
    }
}
