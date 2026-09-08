<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Vehicle\CreateVehicle;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Application\Vehicle\ListVehicles;
use App\Application\Vehicle\PurgeVehicle;
use App\Application\Vehicle\RestoreVehicle;
use App\Application\Vehicle\TrashVehicle;
use App\Application\Vehicle\UpdateVehicle;
use App\Application\Vehicle\ViewVehicle;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

final readonly class VehicleController
{
    public function __construct(
        private ListVehicles $listVehicles,
        private ViewVehicle $viewVehicle,
        private CreateVehicle $createVehicle,
        private UpdateVehicle $updateVehicle,
        private TrashVehicle $trashVehicle,
        private RestoreVehicle $restoreVehicle,
        private PurgeVehicle $purgeVehicle,
        private PaginationPolicy $pagination,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $result = ($this->listVehicles)(RequestActor::fromRequest($request), $perPage, ($page - 1) * $perPage);

        return Response::paginated(
            array_map(static fn (VehicleProfile $profile): array => $profile->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    public function show(Request $request): Response
    {
        return Response::success(($this->viewVehicle)($request->param('id'))->toArray());
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'dealership_id' => 'required|uuid',
            'brand' => 'required|max:60',
            'model' => 'required|max:80',
            'version' => 'max:80',
            'year' => 'numeric|between:1900,2100',
            'price' => 'required|numeric',
            'description' => 'max:2000',
        ]);

        $profile = ($this->createVehicle)($data, RequestActor::fromRequest($request));

        return Response::success($profile->toArray(), 201);
    }

    public function update(Request $request): Response
    {
        $changes = Validator::validate($request->json(), [
            'dealership_id' => 'uuid',
            'brand' => 'max:60',
            'model' => 'max:80',
            'version' => 'max:80',
            'year' => 'numeric|between:1900,2100',
            'price' => 'numeric',
            'description' => 'max:2000',
        ]);

        $profile = ($this->updateVehicle)($request->param('id'), $changes, RequestActor::fromRequest($request));

        return Response::success($profile->toArray());
    }

    public function destroy(Request $request): Response
    {
        ($this->trashVehicle)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Vehicle moved to trash.']);
    }

    public function restore(Request $request): Response
    {
        ($this->restoreVehicle)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Vehicle restored.']);
    }

    public function purge(Request $request): Response
    {
        ($this->purgeVehicle)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Vehicle permanently deleted.']);
    }
}
