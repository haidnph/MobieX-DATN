<?php

namespace App\Http\Controllers\Addresses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Addresses\StoreAddressRequest;
use App\Http\Requests\Addresses\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
     * Set an address as the authenticated user's default address.
     */
    public function setDefault(
        Request $request,
        int $addressId,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $address = DB::transaction(function () use (
            $user,
            $addressId,
        ): UserAddress {
            // Serialize default-address changes for the same user.
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Only an address belonging to this user can be selected.
            $address = $lockedUser->addresses()
                ->whereKey($addressId)
                ->lockForUpdate()
                ->firstOrFail();

            // Clear all existing default addresses.
            $lockedUser->addresses()
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                ]);

            // Reload because the bulk update bypasses Eloquent model state.
            $address->refresh();

            // Set the selected address as the only default.
            $address->forceFill([
                'is_default' => true,
            ])->save();

            return $address->refresh();
        });

        return response()->json([
            'message' => 'Default address updated successfully.',
            'data' => [
                'address' => AddressResource::make($address)
                    ->resolve($request),
            ],
        ]);
    }

    /**
     * Delete an address owned by the authenticated user.
     *
     * Deleting a default address does not automatically promote
     * another address.
     */
    public function destroy(
        Request $request,
        int $addressId,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use (
            $user,
            $addressId,
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $address = $lockedUser->addresses()
                ->whereKey($addressId)
                ->lockForUpdate()
                ->firstOrFail();

            $address->delete();
        });

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }
}
