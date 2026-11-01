<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\StoreAddressRequest;
use App\Http\Requests\Addresses\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    /**
     * Retrieve addresses belonging to the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $addresses = $user->addresses()
            ->orderByDesc('id')
            ->paginate(15);

        return AddressResource::collection($addresses)
            ->additional([
                'message' => 'Addresses retrieved successfully.',
            ]);
    }

    /**
     * Create an address for the authenticated user.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Ownership is always assigned through the authenticated user's
        // relationship. The client cannot choose user_id.
        $address = $user->addresses()->create(
            $request->validated(),
        );

        return response()->json([
            'message' => 'Address created successfully.',
            'data' => [
                'address' => AddressResource::make($address)
                    ->resolve($request),
            ],
        ], 201);
    }

    /**
     * Update an address owned by the authenticated user.
     */
    public function update(
        UpdateAddressRequest $request,
        int $addressId,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        // Query through the authenticated user's relationship.
        // An address owned by another user is treated as not found.
        $address = $user->addresses()->findOrFail($addressId);

        $address->fill($request->validated());
        $address->save();

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => [
                'address' => AddressResource::make(
                    $address->refresh(),
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Delete an address owned by the authenticated user.
     */
    public function destroy(
        Request $request,
        int $addressId,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        // Never retrieve an address globally by ID.
        $address = $user->addresses()->findOrFail($addressId);

        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }
}
