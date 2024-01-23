<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use Inisiatif\DonationRecap\Models\DonationRecap;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class FetchDonationDonorRecapPagination
{
    public function handle(DonationRecap $donationRecap, Request $request): LengthAwarePaginator
    {

        $donationDonors = $donationRecap->donors()->with(['donor.branch', 'donor.partner', 'donor.employee']);

        return QueryBuilder::for($donationDonors, $request)
            ->allowedFilters([
                AllowedFilter::partial('name', 'donor_name'),
                AllowedFilter::partial('identification_number', 'donor_identification_number'),
                AllowedFilter::partial('phone_number', 'donor_phone_number'),
                AllowedFilter::callback('created', static function (Builder $query, $value, string $property): Builder {
                    $date = CarbonImmutable::parse($value);

                    return $query->whereBetween($property, [$date->startOfDay(), $date->endOfDay()]);
                }, 'created_at'),
            ])
            ->paginate()
            ->appends($request->all());
    }
}
