<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Shared\ActorContext;
use App\Application\Vehicle\CreateVehicle;
use App\Application\Vehicle\DTO\PublicVehicleSummary;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Application\Vehicle\EnqueueVehiclePhotos;
use App\Application\Vehicle\ListVehicleAmenityCatalog;
use App\Application\Vehicle\ListVehicles;
use App\Application\Vehicle\PurgeVehicle;
use App\Application\Vehicle\RemoveVehiclePhoto;
use App\Application\Vehicle\ReorderVehiclePhotos;
use App\Application\Vehicle\RestoreVehicle;
use App\Application\Vehicle\TrashVehicle;
use App\Application\Vehicle\UpdateVehicle;
use App\Application\Vehicle\ViewVehicle;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use App\Domain\Vehicle\Amenity;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\VehicleFilterQuery;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

final readonly class VehicleController
{
    /** Compartilhado entre `store`/`update`: os specs são os mesmos campos opcionais nos dois. */
    private const array SPEC_RULES = [
        'manufacture_year' => 'numeric|between:1900,2100',
        'model_year' => 'numeric|between:1900,2100',
        'mileage_km' => 'numeric',
        'transmission' => 'in:manual,automatic,automated,cvt',
        'body_type' => 'in:hatch,sedan,suv,pickup,coupe,convertible,minivan,wagon',
        'fuel_type' => 'in:flex,gasoline,ethanol,diesel,electric,hybrid',
        'color' => 'max:40',
        'plate_end_digit' => 'numeric|between:0,9',
        'accepts_trade' => 'boolean',
        'ipva_paid' => 'boolean',
        'licensed' => 'boolean',
    ];

    public function __construct(
        private ListVehicles $listVehicles,
        private ViewVehicle $viewVehicle,
        private CreateVehicle $createVehicle,
        private UpdateVehicle $updateVehicle,
        private TrashVehicle $trashVehicle,
        private RestoreVehicle $restoreVehicle,
        private PurgeVehicle $purgeVehicle,
        private EnqueueVehiclePhotos $enqueueVehiclePhotos,
        private RemoveVehiclePhoto $removeVehiclePhoto,
        private ReorderVehiclePhotos $reorderVehiclePhotos,
        private ListVehicleAmenityCatalog $listVehicleAmenityCatalog,
        private PaginationPolicy $pagination,
    ) {
    }

    /**
     * Uma rota, dois usos: sem `scope`, é o catálogo público (todo mundo vê o mesmo estoque
     * ativo, dono logado ou não); `scope=mine` é o painel de quem gerencia, e exige a role.
     */
    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $offset = ($page - 1) * $perPage;
        $filters = VehicleFilterQuery::fromRequest($request);

        if ($request->query('scope') === 'mine') {
            $result = ($this->listVehicles)($this->assertManager($request), $filters, $perPage, $offset);

            return Response::paginated(
                array_map(static fn (VehicleProfile $profile): array => $profile->toArray(), $result['items']),
                $page,
                $perPage,
                $result['total'],
            );
        }

        $result = $this->listVehicles->catalog($filters, $perPage, $offset);

        return Response::paginated(
            array_map(static fn (PublicVehicleSummary $summary): array => $summary->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    public function filters(Request $request): Response
    {
        if ($request->query('scope') === 'mine') {
            return Response::success($this->listVehicles->availableFilters($this->assertManager($request)));
        }

        return Response::success($this->listVehicles->availableFiltersPublic());
    }

    public function show(Request $request): Response
    {
        return Response::success(($this->viewVehicle)($request->param('id'), RequestActor::fromRequest($request))->toArray());
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::SPEC_RULES + [
            'dealership_id' => 'required|uuid',
            'brand' => 'required|max:60',
            'model' => 'required|max:80',
            'version' => 'max:80',
            'price' => 'required|numeric',
            'description' => 'max:2000',
        ]);

        $profile = ($this->createVehicle)($data, $this->amenityIds($request), RequestActor::fromRequest($request));

        return Response::success($profile->toArray(), 201);
    }

    public function update(Request $request): Response
    {
        $changes = Validator::validate($request->json(), self::SPEC_RULES + [
            'dealership_id' => 'uuid',
            'brand' => 'max:60',
            'model' => 'max:80',
            'version' => 'max:80',
            'price' => 'numeric',
            'description' => 'max:2000',
        ]);

        $profile = ($this->updateVehicle)($request->param('id'), $changes, $this->amenityIds($request), RequestActor::fromRequest($request));

        return Response::success($profile->toArray());
    }

    public function amenitiesCatalog(): Response
    {
        return Response::success(array_map(
            static fn (Amenity $amenity): array => ['id' => $amenity->id, 'code' => $amenity->code, 'label' => $amenity->label],
            ($this->listVehicleAmenityCatalog)(),
        ));
    }

    /**
     * `null` = campo ausente, mantém os itens já ligados; lista vazia é um pedido explícito de "remover todos".
     *
     * @return ?list<string>
     */
    private function amenityIds(Request $request): ?array
    {
        $body = $request->json();
        $body = is_array($body) ? $body : [];

        if (!array_key_exists('amenity_ids', $body)) {
            return null;
        }

        $ids = $body['amenity_ids'];

        if (!is_array($ids)) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['amenity_ids' => 'The amenity_ids field must be a list of ids.']);
        }

        return array_values(array_map(static fn (mixed $id): string => is_string($id) ? $id : '', $ids));
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

    public function addPhotos(Request $request): Response
    {
        $uploads = [];

        foreach ($request->files('images') as $uploaded) {
            if (!$uploaded->isValid()) {
                throw new DomainException('Invalid data.', DomainErrorType::Validation, ['images' => 'One of the files failed to upload.']);
            }

            $uploads[] = ['tmp_path' => $uploaded->tmpName, 'original_name' => $uploaded->originalName, 'size_bytes' => $uploaded->size];
        }

        $jobId = ($this->enqueueVehiclePhotos)($request->param('id'), $uploads, RequestActor::fromRequest($request));

        return Response::success([
            'job_id' => $jobId,
            'status_url' => "/jobs/{$jobId}",
            'events_url' => "/jobs/{$jobId}/events",
        ], 202);
    }

    public function removePhoto(Request $request): Response
    {
        ($this->removeVehiclePhoto)($request->param('id'), $request->param('image_id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Image removed.']);
    }

    public function reorderPhotos(Request $request): Response
    {
        $order = Validator::validate($request->json(), ['order' => 'required'])->all()['order'] ?? null;

        if (!is_array($order)) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['order' => 'The order field must be a list of image ids.']);
        }

        ($this->reorderVehiclePhotos)($request->param('id'), array_values(array_map(static fn (mixed $id): string => is_string($id) ? $id : '', $order)), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Images reordered.']);
    }

    /** `scope=mine` não tem role de rota que o barre -- a rota é pública, então a checagem mora aqui. */
    private function assertManager(Request $request): ActorContext
    {
        $actor = RequestActor::fromRequest($request);

        if (!$actor->role instanceof \App\Domain\User\UserRole) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        if (!in_array($actor->role, [UserRole::Admin, UserRole::Seller], true)) {
            throw new DomainException('Not allowed for this role.', DomainErrorType::Forbidden);
        }

        return $actor;
    }
}
