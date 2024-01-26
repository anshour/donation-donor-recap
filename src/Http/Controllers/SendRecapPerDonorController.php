<?php

declare(strict_types=1);

namespace Inisiatif\DonationRecap\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Inisiatif\DonationRecap\Actions\SendRecapPerDonor;
use Inisiatif\DonationRecap\Models\DonationRecapDonor;
use Inisiatif\DonationRecap\Exceptions\CannotSendRecap;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SendRecapPerDonorController
{
    public function store(DonationRecapDonor $donor, SendRecapPerDonor $sendRecapPerDonor): JsonResponse
    {
        try {
            // TODO : Tambah validasi hanya untuk `SendRecapPerDonor` dengan state `combined`

            $sendRecapPerDonor->handle($donor);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (CannotSendRecap $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }
}
