<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Models;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inisiatif\DonationRecap\DonationRecap as Recap;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Inisiatif\DonationRecap\Enums\DonationRecapState;
use Inisiatif\DonationRecap\Supports\DonationSummaries;

final class DonationRecap extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'state' => DonationRecapState::class,
        'start_at' => 'date',
        'end_at' => 'date',
        'last_send_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DonationRecapTemplate::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Recap::getEmployeeClassModel());
    }

    public function items(): HasMany
    {
        return $this->hasMany(DonationRecapDetail::class);
    }

    public function donors(): HasMany
    {
        return $this->hasMany(DonationRecapDonor::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DonationRecapHistory::class);
    }

    public function state(DonationRecapState $state): self
    {
        $this->update(['state' => $state]);

        return $this;
    }

    public function recordHistory(string $description, ?string $donorId = null): void
    {
        $this->histories()->create([
            'description' => $description,
            'donor_id' => $donorId,
        ]);
    }

    public function getPeriodStartDate(): Carbon
    {
        return $this->getAttribute('start_at');
    }

    public function getPeriodEndDate(): Carbon
    {
        return $this->getAttribute('end_at');
    }

    public function getPeriodInString(): string
    {
        return \sprintf(
            '%s - %s',
            $this->getPeriodStartDate()->format('d M Y'),
            $this->getPeriodEndDate()->format('d M Y')
        );
    }

    public function inState(DonationRecapState $new): bool
    {
        $state = $this->getAttribute('state');

        return $state->value === $new->value;
    }

    public function isLastRecordProcessed(): bool
    {
        return $this->getAttribute('count_total') === ($this->getAttribute('count_progress') + 1);
    }

    public function getItemCollection(string $donorId): Collection
    {
        return $this->items()
            ->select([
                DB::raw('donation_recap_details.*'),
                DB::raw('COALESCE(donations.currency, \'IDR\') as currency'),
                DB::raw('COALESCE(donations.currency_rate, 1.0)::FLOAT as currency_rate'), // set default value
            ])
            ->leftJoin('donations', 'donations.id', '=', 'donation_id')
            ->where('donation_recap_details.donor_id', $donorId)->oldest('donation_transaction_date')
            ->get();
    }

    public function getCategoryItemsSummaries(string $donorId): DonationSummaries
    {
        $collection = $this->items()
            ->select([
                DB::raw('donation_funding_category_id as category_id'),
                DB::raw('donation_funding_category_name as category'),
                DB::raw('SUM(donation_amount) as donation_amount'),
                DB::raw('COALESCE(donations.currency_rate, 1.0)::FLOAT as currency_rate'), // set default value
            ])
            ->leftJoin('donations', 'donations.id', '=', 'donation_id')
            ->where('donation_recap_details.donor_id', $donorId)
            ->groupBy([
                DB::raw('donation_funding_category_id'),
                DB::raw('donation_funding_category_name'),
                DB::raw('donations.currency_rate'),
            ])
            ->orderBy(DB::raw('donation_funding_category_id'))
            ->get();

        return new DonationSummaries(
            $collection->all()
        );
    }
}
