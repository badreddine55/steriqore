<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Actions\SeedTenantRolesAction;
use App\Domain\Identity\Enums\TenantRole;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Tenancy\Models\Room;
use App\Domain\Tenancy\Models\Site;
use App\Domain\Tenancy\Models\StorageLocation;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mirrors tests/Feature/Api/V1/CycleTest.php's sterilizationTenantWithMember,
 * renamed to avoid a global-function clash when the whole suite loads.
 */
function siteTenantWithMember(TenantRole $role = TenantRole::Owner): array
{
    $tenant = Tenant::create(['name' => 'Cabinet Test', 'slug' => 'cabinet-'.Str::lower(Str::random(10))]);

    $user = User::create([
        'name' => 'Alice Dupont',
        'email' => 'alice-'.Str::lower(Str::random(10)).'@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    app(SeedTenantRolesAction::class)->execute($tenant);
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->id);
    $user->assignRole($role->value);
    $registrar->setPermissionsTeamId(null);

    return [$tenant, $user];
}

test('an owner can view the sites list with rooms/locations/devices counts', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);

    TenantContext::run($tenant, function () use ($tenant) {
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Cabinet Principal']);
        $room = Room::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'name' => 'Sterilization room']);
        StorageLocation::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'room_id' => $room->id, 'name' => 'Cupboard A']);
    });

    $this->actingAs($owner)
        ->get('/sites')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/sites/index')
            ->has('sites', 1)
            ->where('sites.0.name', 'Cabinet Principal')
            ->where('sites.0.rooms_count', 1)
            ->where('sites.0.storage_locations_count', 1)
            ->where('sites.0.devices_count', 0)
            ->where('canManage', true)
        );
});

test('an owner can create a site, then add a room and a storage location to it from the show page', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);

    $this->actingAs($owner)
        ->post('/sites', ['name' => 'Cabinet Principal', 'city' => 'Lyon'])
        ->assertRedirect();

    $site = TenantContext::run($tenant, fn () => Site::firstOrFail());

    $this->actingAs($owner)
        ->post("/sites/{$site->id}/rooms", ['name' => 'Sterilization room'])
        ->assertRedirect();

    $room = TenantContext::run($tenant, fn () => Room::firstOrFail());

    $this->actingAs($owner)
        ->post("/rooms/{$room->id}/storage-locations", ['name' => 'Cupboard A'])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get("/sites/{$site->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/sites/show')
            ->has('rooms', 1)
            ->where('rooms.0.name', 'Sterilization room')
            ->has('storageLocations', 1)
            ->where('storageLocations.0.name', 'Cupboard A')
        );
});

test('creating a site with a duplicate name is rejected with a flashed error, not a 500', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    TenantContext::run($tenant, fn () => Site::create(['tenant_id' => $tenant->id, 'name' => 'Cabinet Principal']));

    $response = $this->actingAs($owner)->post('/sites', ['name' => 'Cabinet Principal']);

    $response->assertRedirect();
    $response->assertSessionHas('_flash');
});

test('a site can be renamed', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    $site = TenantContext::run($tenant, fn () => Site::create(['tenant_id' => $tenant->id, 'name' => 'Old Name']));

    $this->actingAs($owner)
        ->patch("/sites/{$site->id}", ['name' => 'New Name'])
        ->assertRedirect();

    expect($site->fresh()->name)->toBe('New Name');
});

test('archiving a site cascades to its rooms and storage locations, none of them hard-deleted', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    [$site, $room, $location] = TenantContext::run($tenant, function () use ($tenant) {
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Cabinet Principal']);
        $room = Room::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'name' => 'Room A']);
        $location = StorageLocation::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'room_id' => $room->id, 'name' => 'Shelf 1']);

        return [$site, $room, $location];
    });

    $this->actingAs($owner)
        ->delete("/sites/{$site->id}", ['reason' => 'Practice closed this location.'])
        ->assertRedirect();

    TenantContext::run($tenant, function () use ($site, $room, $location) {
        expect(Site::find($site->id))->toBeNull();
        expect(Site::withTrashed()->find($site->id))->not->toBeNull();
        expect(Room::find($room->id))->toBeNull();
        expect(Room::withTrashed()->find($room->id))->not->toBeNull();
        expect(StorageLocation::find($location->id))->toBeNull();
        expect(StorageLocation::withTrashed()->find($location->id))->not->toBeNull();
    });
});

test('an archived storage location still resolves on a historical stock record (invariant #6)', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    [$location, $stockLevel] = TenantContext::run($tenant, function () use ($tenant) {
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Cabinet Principal']);
        $location = StorageLocation::create(['tenant_id' => $tenant->id, 'site_id' => $site->id, 'name' => 'Fridge']);
        $product = Product::create([
            'tenant_id' => $tenant->id, 'name' => 'Gauze', 'reference' => 'REF-'.Str::random(6), 'unit' => 'box',
        ]);
        $batch = Batch::create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'batch_number' => 'B1', 'received_at' => now(),
        ]);
        $stockLevel = StockLevel::create([
            'tenant_id' => $tenant->id, 'batch_id' => $batch->id, 'location_id' => $location->id, 'quantity' => 5,
        ]);
        StockMovement::create([
            'tenant_id' => $tenant->id, 'type' => StockMovementType::Receipt, 'batch_id' => $batch->id,
            'location_id' => $location->id, 'qty' => 5, 'occurred_at' => now(),
        ]);

        return [$location, $stockLevel];
    });

    $this->actingAs($owner)
        ->delete("/storage-locations/{$location->id}", ['reason' => 'No longer used.'])
        ->assertRedirect();

    TenantContext::run($tenant, function () use ($stockLevel) {
        expect($stockLevel->fresh()->location)->not->toBeNull();
        expect($stockLevel->fresh()->location->id)->toBe($stockLevel->location_id);
    });
});

test('moving a room to a different site is refused once it has recorded stock movements', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    [$siteA, $siteB, $room] = TenantContext::run($tenant, function () use ($tenant) {
        $siteA = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site A']);
        $siteB = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site B']);
        $room = Room::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'name' => 'Room A']);
        $location = StorageLocation::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'room_id' => $room->id, 'name' => 'Shelf 1']);
        $product = Product::create([
            'tenant_id' => $tenant->id, 'name' => 'Gauze', 'reference' => 'REF-'.Str::random(6), 'unit' => 'box',
        ]);
        $batch = Batch::create([
            'tenant_id' => $tenant->id, 'product_id' => $product->id, 'batch_number' => 'B1', 'received_at' => now(),
        ]);
        StockMovement::create([
            'tenant_id' => $tenant->id, 'type' => StockMovementType::Receipt, 'batch_id' => $batch->id,
            'location_id' => $location->id, 'qty' => 5, 'occurred_at' => now(),
        ]);

        return [$siteA, $siteB, $room];
    });

    $response = $this->actingAs($owner)->patch("/rooms/{$room->id}", ['site_id' => $siteB->id]);

    $response->assertRedirect();
    $response->assertSessionHas('_flash');
    expect($room->fresh()->site_id)->toBe($siteA->id);
});

test('moving a room with no stock history to a different site succeeds', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    [$siteB, $room] = TenantContext::run($tenant, function () use ($tenant) {
        $siteA = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site A']);
        $siteB = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site B']);
        $room = Room::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'name' => 'Room A']);

        return [$siteB, $room];
    });

    $this->actingAs($owner)
        ->patch("/rooms/{$room->id}", ['site_id' => $siteB->id])
        ->assertRedirect();

    expect($room->fresh()->site_id)->toBe($siteB->id);
});

test('a storage location can move to a different room of the same site, but not a room of another site', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    [$roomB, $foreignRoom, $location] = TenantContext::run($tenant, function () use ($tenant) {
        $siteA = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site A']);
        $siteB = Site::create(['tenant_id' => $tenant->id, 'name' => 'Site B']);
        $roomA = Room::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'name' => 'Room A']);
        $roomB = Room::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'name' => 'Room B']);
        $foreignRoom = Room::create(['tenant_id' => $tenant->id, 'site_id' => $siteB->id, 'name' => 'Room C']);
        $location = StorageLocation::create(['tenant_id' => $tenant->id, 'site_id' => $siteA->id, 'room_id' => $roomA->id, 'name' => 'Shelf 1']);

        return [$roomB, $foreignRoom, $location];
    });

    $this->actingAs($owner)
        ->patch("/storage-locations/{$location->id}", ['room_id' => $roomB->id])
        ->assertRedirect();
    expect($location->fresh()->room_id)->toBe($roomB->id);

    $blocked = $this->actingAs($owner)->patch("/storage-locations/{$location->id}", ['room_id' => $foreignRoom->id]);
    $blocked->assertRedirect();
    $blocked->assertSessionHas('_flash');
    expect($location->fresh()->room_id)->toBe($roomB->id);
});

test('a viewer can see sites but cannot create, edit, or archive one', function () {
    [$tenant, $owner] = siteTenantWithMember(TenantRole::Owner);
    $site = TenantContext::run($tenant, fn () => Site::create(['tenant_id' => $tenant->id, 'name' => 'Cabinet Principal']));

    TenantContext::run($tenant, function () use ($owner) {
        $owner->removeRole(TenantRole::Owner->value);
        $owner->assignRole(TenantRole::Viewer->value);
    });
    $viewer = $owner->fresh();

    $this->actingAs($viewer)
        ->get('/sites')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->actingAs($viewer)->post('/sites', ['name' => 'New Site'])->assertForbidden();
    $this->actingAs($viewer)->patch("/sites/{$site->id}", ['name' => 'Renamed'])->assertForbidden();
    $this->actingAs($viewer)->delete("/sites/{$site->id}", ['reason' => 'X'])->assertForbidden();
});

test('a practice never reaches another practice site, room, or storage location (IDOR)', function () {
    [, $ownerA] = siteTenantWithMember(TenantRole::Owner);
    [$tenantB, $ownerB] = siteTenantWithMember(TenantRole::Owner);
    [$siteB, $roomB, $locationB] = TenantContext::run($tenantB, function () use ($tenantB) {
        $site = Site::create(['tenant_id' => $tenantB->id, 'name' => 'Cabinet B']);
        $room = Room::create(['tenant_id' => $tenantB->id, 'site_id' => $site->id, 'name' => 'Room B']);
        $location = StorageLocation::create(['tenant_id' => $tenantB->id, 'site_id' => $site->id, 'room_id' => $room->id, 'name' => 'Shelf B']);

        return [$site, $room, $location];
    });

    $this->actingAs($ownerA)->get("/sites/{$siteB->id}")->assertNotFound();
    $this->actingAs($ownerA)->patch("/sites/{$siteB->id}", ['name' => 'X'])->assertNotFound();
    $this->actingAs($ownerA)->patch("/rooms/{$roomB->id}", ['name' => 'X'])->assertNotFound();
    $this->actingAs($ownerA)->patch("/storage-locations/{$locationB->id}", ['name' => 'X'])->assertNotFound();

    $this->actingAs($ownerA)
        ->get('/sites')
        ->assertInertia(fn (Assert $page) => $page->has('sites', 0));
});

test('an unauthenticated visitor is redirected to login', function () {
    $this->get('/sites')->assertRedirect('/login');
});
