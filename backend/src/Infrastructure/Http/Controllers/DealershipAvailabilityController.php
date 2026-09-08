<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Availability\CreateDealershipAvailabilityRule;
use App\Application\Availability\DeleteDealershipAvailabilityRule;
use App\Application\Availability\ListDealershipAvailabilityRules;
use App\Application\Availability\UpdateDealershipAvailabilityRule;
use App\Domain\Availability\DealershipAvailabilityRule;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final readonly class DealershipAvailabilityController
{
    private const array RULES = ['weekday' => 'required|numeric|between:0,6', 'start_time' => 'required|time', 'end_time' => 'required|time'];

    public function __construct(
        private ListDealershipAvailabilityRules $listRules,
        private CreateDealershipAvailabilityRule $createRule,
        private UpdateDealershipAvailabilityRule $updateRule,
        private DeleteDealershipAvailabilityRule $deleteRule,
    ) {
    }

    public function index(Request $request): Response
    {
        $rules = ($this->listRules)($request->param('id'));

        return Response::success(array_map($this->toArray(...), $rules));
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $rule = ($this->createRule)($request->param('id'), $data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($rule), 201);
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->json(), self::RULES);
        $rule = ($this->updateRule)($request->param('id'), $data, RequestActor::fromRequest($request));

        return Response::success($this->toArray($rule));
    }

    public function destroy(Request $request): Response
    {
        ($this->deleteRule)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Availability rule removed.']);
    }

    /** @return array{id: string, dealership_id: string, weekday: int, start_time: string, end_time: string} */
    private function toArray(DealershipAvailabilityRule $rule): array
    {
        return [
            'id' => $rule->id,
            'dealership_id' => $rule->dealershipId,
            'weekday' => $rule->window->weekday,
            'start_time' => $rule->window->startTime->format('H:i'),
            'end_time' => $rule->window->endTime->format('H:i'),
        ];
    }
}
