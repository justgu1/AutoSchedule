<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Dealership\CreateDealership;
use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Dealership\EnqueueDealershipPhoto;
use App\Application\Dealership\ListDealerships;
use App\Application\Dealership\PurgeDealership;
use App\Application\Dealership\RemoveDealershipPhoto;
use App\Application\Dealership\RestoreDealership;
use App\Application\Dealership\TrashDealership;
use App\Application\Dealership\UpdateDealership;
use App\Application\Dealership\ViewDealership;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\UploadedFile;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

/**
 * Admin vê/gerencia qualquer concessionária; seller só as próprias -- RLS já
 * escopa isso na leitura (linha de outro dono nem aparece pro `findById`),
 * então "não encontrada" e "não é sua" são a mesma resposta (404), de propósito.
 *
 * `show()` é a exceção: responde qualquer um, autenticado ou não -- dono/admin
 * recebem o perfil completo, todo o resto (outro seller, customer, sem conta
 * nenhuma) recebe o perfil público de uma concessionária `active` (RLS
 * garante isso na leitura em si; ver migration da policy pública).
 */
final readonly class DealershipController
{
    public function __construct(
        private ListDealerships $listDealerships,
        private ViewDealership $viewDealership,
        private CreateDealership $createDealership,
        private UpdateDealership $updateDealership,
        private TrashDealership $trashDealership,
        private RestoreDealership $restoreDealership,
        private PurgeDealership $purgeDealership,
        private EnqueueDealershipPhoto $enqueueDealershipPhoto,
        private RemoveDealershipPhoto $removeDealershipPhoto,
        private PaginationPolicy $pagination,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $result = ($this->listDealerships)(RequestActor::fromRequest($request), $perPage, ($page - 1) * $perPage);

        return Response::paginated(
            array_map(static fn (DealershipProfile $profile): array => $profile->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    public function show(Request $request): Response
    {
        $profile = ($this->viewDealership)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success($profile->toArray());
    }

    public function store(Request $request): Response
    {
        $actor = RequestActor::fromRequest($request);
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
            'email' => 'max:190|email',
        ];

        if ($actor->role === UserRole::Admin) {
            $rules['owner_user_id'] = 'required|uuid';
        }

        $profile = ($this->createDealership)(Validator::validate($request->json(), $rules), $actor);

        return Response::success($profile->toArray(), 201);
    }

    /**
     * `owner_user_id` só é aceito no corpo quando quem chama é admin -- é a
     * mesma rota que reassocia dono, sem endpoint paralelo pra isso.
     */
    public function update(Request $request): Response
    {
        $actor = RequestActor::fromRequest($request);
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
            'email' => 'max:190|email',
        ];

        if ($actor->role === UserRole::Admin) {
            $rules['owner_user_id'] = 'uuid';
        }

        $profile = ($this->updateDealership)($request->param('id'), Validator::validate($request->json(), $rules), $actor);

        return Response::success($profile->toArray());
    }

    /** Move pra lixeira -- recuperável por 30 dias (`restore()`/`purge()` abaixo). */
    public function destroy(Request $request): Response
    {
        ($this->trashDealership)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Dealership moved to trash.']);
    }

    public function restore(Request $request): Response
    {
        ($this->restoreDealership)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Dealership restored.']);
    }

    /** Apaga em definitivo agora, sem esperar os 30 dias. */
    public function purge(Request $request): Response
    {
        ($this->purgeDealership)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Dealership permanently deleted.']);
    }

    /**
     * Só enfileira: otimização (WebP) e gravação rodam no worker. Quem chamou
     * acompanha o progresso via `job_id` (`GET /jobs/{id}` ou `/events`).
     */
    public function setPhoto(Request $request): Response
    {
        $uploaded = $request->file('image');

        if (!$uploaded instanceof UploadedFile || !$uploaded->isValid()) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['image' => 'No valid image file was sent.']);
        }

        $jobId = ($this->enqueueDealershipPhoto)(
            $request->param('id'),
            $uploaded->tmpName,
            $uploaded->originalName,
            $uploaded->size,
            RequestActor::fromRequest($request),
        );

        return Response::success([
            'job_id' => $jobId,
            'status_url' => "/jobs/{$jobId}",
            'events_url' => "/jobs/{$jobId}/events",
        ], 202);
    }

    public function removePhoto(Request $request): Response
    {
        ($this->removeDealershipPhoto)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Photo removed.']);
    }
}
