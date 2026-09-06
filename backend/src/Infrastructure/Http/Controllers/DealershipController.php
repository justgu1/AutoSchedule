<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealerships\Dealership;
use App\Domain\Dealerships\DealershipImage;
use App\Domain\Dealerships\DTO\DealershipProfile;
use App\Domain\Dealerships\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\StoredFile;
use App\Domain\Ports\StorageProvider;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Users\UserRole;
use App\Infrastructure\Files\FileUploadService;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\UploadedFile;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

/**
 * Admin vê/gerencia qualquer concessionária; seller só as próprias -- RLS já
 * escopa isso na leitura (linha de outro dono nem aparece pro `findById`),
 * então "não encontrada" e "não é sua" são a mesma resposta (404), de propósito.
 */
final readonly class DealershipController
{
    private const array ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private DealershipRepository $dealerships,
        private FileRepository $files,
        private FileUploadService $uploads,
        private StorageProvider $storage,
        private AuditLogger $audit,
        private PaginationPolicy $pagination,
    ) {
    }

    public function index(Request $request): Response
    {
        $claims = $request->attribute('auth');
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $offset = ($page - 1) * $perPage;

        if ($claims->role === UserRole::Admin) {
            $profiles = array_map(
                static fn (Dealership $dealership): array => DealershipProfile::fromDealership($dealership)->toArray(),
                $this->dealerships->findPage($perPage, $offset),
            );

            return Response::paginated($profiles, $page, $perPage, $this->dealerships->count());
        }

        $profiles = array_map(
            static fn (Dealership $dealership): array => DealershipProfile::fromDealership($dealership)->toArray(),
            $this->dealerships->findByOwner($claims->subject, $perPage, $offset),
        );

        return Response::paginated($profiles, $page, $perPage, $this->dealerships->countByOwner($claims->subject));
    }

    public function show(Request $request): Response
    {
        $dealership = $this->requireDealership($request);

        return Response::success($this->withImages($dealership));
    }

    public function store(Request $request): Response
    {
        $claims = $request->attribute('auth');
        $rules = [
            'name' => 'required|max:160',
            'zip_code' => 'required|max:10',
            'address' => 'required|max:255',
            'number' => 'required|max:20',
            'complement' => 'max:120',
            'neighborhood' => 'required|max:120',
            'city' => 'required|max:120',
            'state' => 'required|max:2',
            'phone' => 'max:20',
        ];

        if ($claims->role === UserRole::Admin) {
            $rules['owner_user_id'] = 'required|uuid';
        }

        $data = Validator::validate($request->json(), $rules);
        $ownerUserId = $claims->role === UserRole::Admin ? $data['owner_user_id'] : $claims->subject;

        $dealership = Dealership::register(
            ownerUserId: $ownerUserId,
            name: $data['name'],
            zipCode: $data['zip_code'],
            address: $data['address'],
            number: $data['number'],
            complement: $data['complement'] ?? null,
            neighborhood: $data['neighborhood'],
            city: $data['city'],
            state: $data['state'],
            phone: $data['phone'] ?? null,
        );

        $this->dealerships->insert($dealership);
        $this->audit->record(AuditEvent::DealershipCreated, $claims->subject, 'Dealership', $dealership->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(DealershipProfile::fromDealership($dealership)->toArray(), 201);
    }

    /**
     * `owner_user_id` só é aceito no corpo quando quem chama é admin -- é a
     * mesma rota que reassocia dono, sem endpoint paralelo pra isso.
     */
    public function update(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');
        $rules = [
            'name' => 'max:160',
            'zip_code' => 'max:10',
            'address' => 'max:255',
            'number' => 'max:20',
            'complement' => 'max:120',
            'neighborhood' => 'max:120',
            'city' => 'max:120',
            'state' => 'max:2',
            'phone' => 'max:20',
        ];

        if ($claims->role === UserRole::Admin) {
            $rules['owner_user_id'] = 'uuid';
        }

        $data = Validator::validate($request->json(), $rules);
        $previousOwnerUserId = $dealership->ownerUserId;

        $updated = $dealership->withProfile(
            name: $data['name'] ?? $dealership->name,
            zipCode: $data['zip_code'] ?? $dealership->zipCode,
            address: $data['address'] ?? $dealership->address,
            number: $data['number'] ?? $dealership->number,
            complement: $data['complement'] ?? $dealership->complement,
            neighborhood: $data['neighborhood'] ?? $dealership->neighborhood,
            city: $data['city'] ?? $dealership->city,
            state: $data['state'] ?? $dealership->state,
            phone: $data['phone'] ?? $dealership->phone,
            latitude: $dealership->latitude,
            longitude: $dealership->longitude,
            googlePlaceId: $dealership->googlePlaceId,
        );

        if (array_key_exists('owner_user_id', $data) && $data['owner_user_id'] !== $previousOwnerUserId) {
            $updated = $updated->withOwner($data['owner_user_id']);
        }

        $this->dealerships->update($updated);
        $this->audit->record(AuditEvent::DealershipUpdated, $claims->subject, 'Dealership', $updated->id, ['fields' => array_keys($data)], $request->ip(), $request->header('user-agent'));

        if ($updated->ownerUserId !== $previousOwnerUserId) {
            $this->audit->record(
                AuditEvent::DealershipOwnerReassigned,
                $claims->subject,
                'Dealership',
                $updated->id,
                ['from' => $previousOwnerUserId, 'to' => $updated->ownerUserId],
                $request->ip(),
                $request->header('user-agent'),
            );
        }

        return Response::success(DealershipProfile::fromDealership($updated)->toArray());
    }

    /** Move pra lixeira -- recuperável por 30 dias (`restore()`/`purge()` abaixo). */
    public function destroy(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');

        $this->dealerships->trash($dealership->id, byOwnerDeactivation: false);
        $this->audit->record(AuditEvent::DealershipTrashed, $claims->subject, 'Dealership', $dealership->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Dealership moved to trash.']);
    }

    public function restore(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');

        if (!$dealership->isEligibleForRestore()) {
            throw new DomainException('This dealership is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->dealerships->restore($dealership->id);
        $this->audit->record(AuditEvent::DealershipRestored, $claims->subject, 'Dealership', $dealership->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Dealership restored.']);
    }

    /** Apaga em definitivo agora, sem esperar os 30 dias. */
    public function purge(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');

        if ($dealership->status !== TrashableStatus::Trashed) {
            throw new DomainException('This dealership is not in the trash.', DomainErrorType::Conflict);
        }

        $this->dealerships->update($dealership->anonymized());
        $this->audit->record(AuditEvent::DealershipPurged, $claims->subject, 'Dealership', $dealership->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Dealership permanently deleted.']);
    }

    public function addImage(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');
        $uploaded = $request->file('image');

        if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['image' => 'No valid image file was sent.']);
        }

        $file = $this->uploads->upload($uploaded->tmpName, $uploaded->originalName, self::ALLOWED_IMAGE_MIME_TYPES, $claims->subject);
        $position = $this->dealerships->nextImagePosition($dealership->id);
        $image = DealershipImage::register($dealership->id, $file->id, $position);
        $this->dealerships->insertImage($image);

        $this->audit->record(AuditEvent::DealershipImageAdded, $claims->subject, 'Dealership', $dealership->id, ['image_id' => $image->id], $request->ip(), $request->header('user-agent'));

        return Response::success(['id' => $image->id, 'url' => $this->storage->url($file->path), 'position' => $image->position], 201);
    }

    public function removeImage(Request $request): Response
    {
        $dealership = $this->requireDealership($request);
        $claims = $request->attribute('auth');
        $imageId = $request->param('imageId');
        $image = $imageId !== null ? $this->dealerships->findImageById($imageId) : null;

        if (!$image instanceof DealershipImage || $image->dealershipId !== $dealership->id) {
            throw new DomainException('Image not found.', DomainErrorType::NotFound);
        }

        $this->dealerships->deleteImage($image->id);
        $this->audit->record(AuditEvent::DealershipImageRemoved, $claims->subject, 'Dealership', $dealership->id, ['image_id' => $image->id], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Image removed.']);
    }

    /** `{id}` da rota -- RLS já barra dono errado (linha nem aparece), então "não é sua" e "não existe" viram o mesmo 404. */
    private function requireDealership(Request $request): Dealership
    {
        $id = $request->param('id');
        $dealership = $id !== null ? $this->dealerships->findById($id) : null;

        if (!$dealership instanceof Dealership) {
            throw new DomainException('Dealership not found.', DomainErrorType::NotFound);
        }

        return $dealership;
    }

    /** @return array<string, mixed> */
    private function withImages(Dealership $dealership): array
    {
        $payload = DealershipProfile::fromDealership($dealership)->toArray();

        $payload['images'] = array_map(
            fn (DealershipImage $image): array => [
                'id' => $image->id,
                'url' => $this->urlForImage($image),
                'position' => $image->position,
            ],
            $this->dealerships->findImagesByDealership($dealership->id),
        );

        return $payload;
    }

    private function urlForImage(DealershipImage $image): string
    {
        $file = $this->files->findById($image->fileId);

        return $file instanceof StoredFile ? $this->storage->url($file->path) : '';
    }
}
