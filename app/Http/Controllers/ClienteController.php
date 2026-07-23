<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;

class ClienteController extends Controller
{
    /**
     * Crea un nuovo cliente per il tenant corrente (usato dalla modale
     * di creazione rapida nel form pratica).
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = Cliente::create($request->validated());

        return response()->json([
            'cliente' => $cliente->only(['id', 'nome', 'telefono', 'email']),
        ], 201);
    }
}
