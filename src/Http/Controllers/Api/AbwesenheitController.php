<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Http\Controllers\Api;

use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class AbwesenheitController extends Controller
{
    public function __construct(
        private readonly AbwesenheitService $abwesenheitService,
    ) {}

    public function store(Request $request, string $username): JsonResponse
    {
        $user = AbwesenheitModels::userQuery()->where('username', $username)->firstOrFail();

        $validated = $request->validate([
            'email_vertreter' => 'required|string',
            'email_delegate' => 'boolean',
            'call_forwarding' => 'boolean',
            'd3_forwarding' => 'boolean',
            'notice' => 'string|nullable',
            'call_destination' => 'required_if:call_forwarding,true|string',
            'start' => 'date|nullable',
            'end' => 'date|nullable',
            'phone_vertreter' => 'string|nullable',
            'd3_vertreter' => 'string|nullable',
        ]);

        if (isset($validated['start'])) {
            $validated['start'] = Carbon::parse($validated['start']);
        }
        if (isset($validated['end'])) {
            $validated['end'] = Carbon::parse($validated['end']);
        }

        $result = $this->abwesenheitService->apply($user, AbwesenheitStoreData::fromArray($validated));

        return response()->json([
            'message' => 'Abwesenheit set',
            'warnings' => $result->warnings,
        ]);
    }

    public function show(Request $request, string $username): JsonResponse
    {
        $user = AbwesenheitModels::userQuery()->where('username', $username)->firstOrFail();

        return response()->json($this->abwesenheitService->show($user));
    }

    public function destroy(Request $request, string $username): JsonResponse
    {
        $user = AbwesenheitModels::userQuery()->where('username', $username)->firstOrFail();

        $this->abwesenheitService->destroy($user);

        return response()->json(['message' => 'Abwesenheit removed']);
    }
}
