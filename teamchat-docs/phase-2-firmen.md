# Phase 2: Firmen-System (Woche 3-4)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Vollständige Company CRUD API
- Channel-Management innerhalb von Firmen
- Beitritts-System mit Passwort
- Channel-Beitrittsanfragen für private Channels
- Mitgliederverwaltung mit Rollen (Admin/User)

---

## 2.1 Company Controller [BE]

### 2.1.1 CompanyController erstellen
- [x] **Erledigt**

→ *Abhängig von Phase 1 abgeschlossen*

**Durchführung:**
```bash
php artisan make:controller Api/CompanyController
```

**Datei:** `app/Http/Controllers/Api/CompanyController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function __construct(
        private ImageCompressionService $imageService
    ) {}

    // Methoden folgen in den nächsten Tasks
}
```

---

### 2.1.2 Endpoint: GET /api/my-companies
- [x] **Erledigt**

**Beschreibung:** Liste aller Firmen des aktuellen Users.

**Response (200):**
```json
{
    "companies": [
        {
            "id": 1,
            "name": "Test GmbH",
            "slug": "test-gmbh",
            "logo_url": null,
            "owner": {
                "id": 1,
                "username": "Max",
                "avatar_url": "..."
            },
            "members_count": 5,
            "my_role": "admin",
            "is_owner": true,
            "created_at": "2024-..."
        }
    ]
}
```

**Implementierung:**
```php
public function myCompanies(Request $request): JsonResponse
{
    $user = $request->user();

    $companies = $user->companies()
        ->withCount('members')
        ->with('owner:id,username,avatar_path')
        ->get()
        ->map(fn ($company) => $this->formatCompany($company, $user));

    return response()->json(['companies' => $companies]);
}

private function formatCompany(Company $company, User $user): array
{
    return [
        'id' => $company->id,
        'name' => $company->name,
        'slug' => $company->slug,
        'logo_url' => $company->logo_url,
        'owner' => [
            'id' => $company->owner->id,
            'username' => $company->owner->username,
            'avatar_url' => $company->owner->avatar_url,
        ],
        'members_count' => $company->members_count,
        'my_role' => $company->pivot->role ?? null,
        'is_owner' => $company->owner_id === $user->id,
        'created_at' => $company->created_at->toIso8601String(),
    ];
}
```

**Unit Test:** `tests/Feature/Api/Company/MyCompaniesTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_users_companies(): void
    {
        $user = User::factory()->create();
        $myCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $myCompany->members()->attach($user->id, ['role' => 'user']);

        $response = $this->actingAs($user)
            ->getJson('/api/my-companies');

        $response->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonFragment(['name' => $myCompany->name]);
    }

    public function test_shows_correct_role(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id, ['role' => 'admin']);

        $response = $this->actingAs($user)
            ->getJson('/api/my-companies');

        $response->assertJsonFragment(['my_role' => 'admin']);
    }

    public function test_shows_owner_flag(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $company->members()->attach($owner->id, ['role' => 'admin']);

        $response = $this->actingAs($owner)
            ->getJson('/api/my-companies');

        $response->assertJsonFragment(['is_owner' => true]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/my-companies');
        $response->assertStatus(401);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Firmen des Users werden gelistet
- [ ] Owner-Info ist dabei
- [ ] members_count ist korrekt
- [ ] my_role zeigt aktuelle Rolle
- [ ] is_owner Flag ist korrekt

---

### 2.1.3 Endpoint: GET /api/companies/search
- [x] **Erledigt**

**Beschreibung:** Firmen durchsuchen (für Beitritt).

**Query Parameters:**
```php
$validated = $request->validate([
    'q' => ['required', 'string', 'min:2', 'max:100'],
]);
```

**Response (200):**
```json
{
    "companies": [
        {
            "id": 1,
            "name": "Test GmbH",
            "slug": "test-gmbh",
            "logo_url": null,
            "members_count": 5
        }
    ]
}
```

**Implementierung:**
```php
public function search(Request $request): JsonResponse
{
    $validated = $request->validate([
        'q' => ['required', 'string', 'min:2', 'max:100'],
    ]);

    $query = $validated['q'];

    $companies = Company::where('name', 'LIKE', "%{$query}%")
        ->orWhere('slug', 'LIKE', "%{$query}%")
        ->withCount('members')
        ->limit(20)
        ->get()
        ->map(fn ($company) => [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'logo_url' => $company->logo_url,
            'members_count' => $company->members_count,
        ]);

    return response()->json(['companies' => $companies]);
}
```

**Unit Test:** `tests/Feature/Api/Company/SearchCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_by_name(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['name' => 'Acme Corporation']);
        Company::factory()->create(['name' => 'Other Company']);

        $response = $this->actingAs($user)
            ->getJson('/api/companies/search?q=Acme');

        $response->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonFragment(['name' => 'Acme Corporation']);
    }

    public function test_finds_by_slug(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['name' => 'Test Firma', 'slug' => 'test-firma']);

        $response = $this->actingAs($user)
            ->getJson('/api/companies/search?q=test-firma');

        $response->assertOk()
            ->assertJsonCount(1, 'companies');
    }

    public function test_does_not_expose_join_password(): void
    {
        $user = User::factory()->create();
        Company::factory()->create(['name' => 'Test']);

        $response = $this->actingAs($user)
            ->getJson('/api/companies/search?q=Test');

        $response->assertOk()
            ->assertJsonMissing(['join_password']);
    }

    public function test_limits_results_to_20(): void
    {
        $user = User::factory()->create();
        Company::factory()->count(25)->create(['name' => 'Test Company']);

        $response = $this->actingAs($user)
            ->getJson('/api/companies/search?q=Test');

        $response->assertOk()
            ->assertJsonCount(20, 'companies');
    }

    public function test_requires_min_2_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/companies/search?q=A');

        $response->assertStatus(422);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Suche funktioniert nach Name
- [ ] Suche funktioniert nach Slug
- [ ] join_password ist NICHT in Response
- [ ] Max 20 Ergebnisse
- [ ] Min 2 Zeichen erforderlich

---

### 2.1.4 Endpoint: POST /api/companies
- [x] **Erledigt**

**Beschreibung:** Neue Firma erstellen.

**Request:**
```json
{
    "name": "Meine Firma GmbH",
    "join_password": "secret123"
}
```

**Response (201):**
```json
{
    "message": "Company created",
    "company": { ... }
}
```

**Implementierung:**
```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'join_password' => ['required', 'string', 'min:6', 'max:100'],
    ]);

    $user = $request->user();

    $company = Company::create([
        'name' => $validated['name'],
        'join_password' => Hash::make($validated['join_password']),
        'owner_id' => $user->id,
    ]);

    // Ersteller als Admin hinzufügen
    $company->addMember($user, 'admin');

    // Default-Channel erstellen
    $channel = $company->channels()->create([
        'name' => 'Allgemein',
        'description' => 'Allgemeiner Kanal für alle Mitglieder',
        'is_private' => false,
        'created_by' => $user->id,
    ]);
    $channel->addMember($user);

    return response()->json([
        'message' => 'Company created',
        'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
    ], 201);
}
```

**Unit Test:** `tests/Feature/Api/Company/CreateCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_company(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Neue Firma GmbH',
                'join_password' => 'secret123',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Neue Firma GmbH']);

        $this->assertDatabaseHas('companies', [
            'name' => 'Neue Firma GmbH',
            'owner_id' => $user->id,
        ]);
    }

    public function test_slug_is_auto_generated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma GmbH',
                'join_password' => 'secret123',
            ]);

        $this->assertDatabaseHas('companies', [
            'slug' => 'test-firma-gmbh',
        ]);
    }

    public function test_creates_default_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertDatabaseHas('channels', [
            'company_id' => $company->id,
            'name' => 'Allgemein',
            'is_private' => false,
        ]);
    }

    public function test_creator_is_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertTrue($user->isAdminOf($company));
    }

    public function test_creator_is_in_default_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();
        $channel = $company->channels()->first();

        $this->assertTrue($user->isMemberOfChannel($channel));
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => 'secret123',
            ]);

        $company = Company::where('name', 'Test Firma')->first();

        $this->assertNotEquals('secret123', $company->join_password);
        $this->assertTrue($company->checkJoinPassword('secret123'));
    }

    public function test_validation_fails_without_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'join_password' => 'secret123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_validation_fails_with_short_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/companies', [
                'name' => 'Test Firma',
                'join_password' => '12345',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['join_password']);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Firma wird erstellt
- [ ] Slug wird automatisch generiert
- [ ] Passwort ist gehasht
- [ ] Ersteller ist Owner UND Admin
- [ ] "Allgemein" Channel existiert
- [ ] Ersteller ist in "Allgemein" Channel

---

### 2.1.5 Endpoint: GET /api/companies/{company}
- [x] **Erledigt**

**Beschreibung:** Details einer Firma (nur für Mitglieder).

**Response (200):**
```json
{
    "company": {
        "id": 1,
        "name": "Test GmbH",
        "slug": "test-gmbh",
        "logo_url": null,
        "owner": { ... },
        "members_count": 5,
        "my_role": "admin",
        "is_owner": true,
        "created_at": "..."
    },
    "channels": [
        {
            "id": 1,
            "name": "Allgemein",
            "description": "...",
            "is_private": false,
            "is_member": true,
            "members_count": 5
        }
    ]
}
```

**Response (403):**
```json
{
    "message": "You are not a member of this company"
}
```

**Implementierung:**
```php
public function show(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($company)) {
        return response()->json([
            'message' => 'You are not a member of this company',
        ], 403);
    }

    $company->load('owner:id,username,avatar_path');
    $company->loadCount('members');

    $channels = $company->channels()
        ->withCount('members')
        ->get()
        ->map(fn ($channel) => [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_private' => $channel->is_private,
            'is_member' => $user->isMemberOfChannel($channel),
            'members_count' => $channel->members_count,
        ]);

    return response()->json([
        'company' => $this->formatCompany($company, $user),
        'channels' => $channels,
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Company/ShowCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $response = $this->actingAs($user)
            ->getJson("/api/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => $company->name]);
    }

    public function test_non_member_cannot_view_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/companies/{$company->id}");

        $response->assertStatus(403);
    }

    public function test_channels_show_membership_status(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $memberChannel = Channel::factory()->create(['company_id' => $company->id]);
        $memberChannel->members()->attach($user->id);

        $nonMemberChannel = Channel::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/companies/{$company->id}");

        $channels = collect($response->json('channels'));

        $this->assertTrue($channels->firstWhere('id', $memberChannel->id)['is_member']);
        $this->assertFalse($channels->firstWhere('id', $nonMemberChannel->id)['is_member']);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Mitglieder können Details sehen
- [ ] Nicht-Mitglieder bekommen 403
- [ ] Channels werden mit is_member Flag gelistet

---

### 2.1.6 Endpoint: PUT /api/companies/{company}
- [x] **Erledigt**

**Beschreibung:** Firma aktualisieren (nur Owner).

**Request:**
```json
{
    "name": "Neuer Name",
    "join_password": "neuespasswort"
}
```

**Response (200):**
```json
{
    "message": "Company updated",
    "company": { ... }
}
```

**Response (403):**
```json
{
    "message": "Only the owner can update this company"
}
```

**Implementierung:**
```php
public function update(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if ($company->owner_id !== $user->id) {
        return response()->json([
            'message' => 'Only the owner can update this company',
        ], 403);
    }

    $validated = $request->validate([
        'name' => ['sometimes', 'string', 'min:2', 'max:255'],
        'join_password' => ['sometimes', 'string', 'min:6', 'max:100'],
    ]);

    if (isset($validated['join_password'])) {
        $validated['join_password'] = Hash::make($validated['join_password']);
    }

    $company->update($validated);

    return response()->json([
        'message' => 'Company updated',
        'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Company/UpdateCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_company(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Neuer Name',
            ]);

        $response->assertOk();
        $this->assertEquals('Neuer Name', $company->fresh()->name);
    }

    public function test_admin_cannot_update_company(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->putJson("/api/companies/{$company->id}", [
                'name' => 'Neuer Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_password_is_hashed_on_update(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->putJson("/api/companies/{$company->id}", [
                'join_password' => 'neuespasswort',
            ]);

        $this->assertTrue($company->fresh()->checkJoinPassword('neuespasswort'));
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Owner kann ändern
- [ ] Nicht-Owner bekommt 403
- [ ] Passwort wird gehasht
- [ ] Name kann geändert werden

---

### 2.1.7 Endpoint: POST /api/companies/{company}/join
- [x] **Erledigt**

**Beschreibung:** Firma beitreten mit Passwort.

**Request:**
```json
{
    "password": "secret123"
}
```

**Response (200):**
```json
{
    "message": "Successfully joined company",
    "company": { ... }
}
```

**Response (422) - falsches Passwort:**
```json
{
    "message": "Invalid password"
}
```

**Implementierung:**
```php
public function join(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if ($user->isMemberOf($company)) {
        return response()->json([
            'message' => 'You are already a member of this company',
        ], 422);
    }

    $validated = $request->validate([
        'password' => ['required', 'string'],
    ]);

    if (!$company->checkJoinPassword($validated['password'])) {
        return response()->json([
            'message' => 'Invalid password',
        ], 422);
    }

    $company->addMember($user, 'user');

    // Zu allen öffentlichen Channels hinzufügen
    $company->channels()
        ->where('is_private', false)
        ->each(fn ($channel) => $channel->addMember($user));

    return response()->json([
        'message' => 'Successfully joined company',
        'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Company/JoinCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JoinCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_join_with_correct_password(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $response->assertOk();
        $this->assertTrue($user->isMemberOf($company));
    }

    public function test_join_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid password']);
    }

    public function test_already_member_gets_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'join_password' => Hash::make('secret123'),
        ]);
        $company->members()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'You are already a member of this company']);
    }

    public function test_join_adds_to_public_channels(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $company = Company::factory()->create([
            'owner_id' => $owner->id,
            'join_password' => Hash::make('secret123'),
        ]);

        $publicChannel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => false,
        ]);
        $privateChannel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/join", [
                'password' => 'secret123',
            ]);

        $this->assertTrue($user->isMemberOfChannel($publicChannel));
        $this->assertFalse($user->isMemberOfChannel($privateChannel));
    }
}
```

**Akzeptanzkriterien:**
- [ ] Richtiges Passwort → Beitritt erfolgreich
- [ ] Falsches Passwort → 422
- [ ] Bereits Mitglied → 422
- [ ] User ist in allen öffentlichen Channels

---

### 2.1.8 Endpoint: POST /api/companies/{company}/leave
- [x] **Erledigt**

**Beschreibung:** Firma verlassen.

**Response (200):**
```json
{
    "message": "Successfully left company"
}
```

**Response (422) - Owner:**
```json
{
    "message": "Owner cannot leave the company. Transfer ownership first."
}
```

**Implementierung:**
```php
public function leave(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($company)) {
        return response()->json([
            'message' => 'You are not a member of this company',
        ], 422);
    }

    if ($company->owner_id === $user->id) {
        return response()->json([
            'message' => 'Owner cannot leave the company. Transfer ownership first.',
        ], 422);
    }

    $company->removeMember($user);

    return response()->json([
        'message' => 'Successfully left company',
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Company/LeaveCompanyTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_leave(): void
    {
        $member = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($member->id);

        $response = $this->actingAs($member)
            ->postJson("/api/companies/{$company->id}/leave");

        $response->assertOk();
        $this->assertFalse($member->isMemberOf($company));
    }

    public function test_owner_cannot_leave(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);
        $company->members()->attach($owner->id, ['role' => 'admin']);

        $response = $this->actingAs($owner)
            ->postJson("/api/companies/{$company->id}/leave");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Owner cannot leave the company. Transfer ownership first.']);
    }

    public function test_leaving_removes_from_all_channels(): void
    {
        $member = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($member->id);

        $channel = Channel::factory()->create(['company_id' => $company->id]);
        $channel->members()->attach($member->id);

        $this->actingAs($member)
            ->postJson("/api/companies/{$company->id}/leave");

        $this->assertFalse($member->isMemberOfChannel($channel));
    }

    public function test_non_member_gets_error(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/leave");

        $response->assertStatus(422);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Normaler User kann verlassen
- [ ] Owner kann NICHT verlassen (422)
- [ ] User wird aus allen Channels der Firma entfernt
- [ ] Nicht-Mitglied bekommt 422

---

### 2.1.9 Endpoint: GET /api/companies/{company}/members
- [x] **Erledigt**

**Beschreibung:** Mitgliederliste einer Firma.

**Response (200):**
```json
{
    "members": [
        {
            "id": 1,
            "username": "Max",
            "email": "max@test.de",
            "avatar_url": "...",
            "status": "available",
            "role": "admin",
            "joined_at": "2024-...",
            "is_owner": true
        }
    ]
}
```

**Implementierung:**
```php
public function members(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($company)) {
        return response()->json([
            'message' => 'You are not a member of this company',
        ], 403);
    }

    $members = $company->members()
        ->get()
        ->map(fn ($member) => [
            'id' => $member->id,
            'username' => $member->username,
            'email' => $member->email,
            'avatar_url' => $member->avatar_url,
            'status' => $member->status,
            'role' => $member->pivot->role,
            'joined_at' => $member->pivot->joined_at,
            'is_owner' => $member->id === $company->owner_id,
        ]);

    return response()->json(['members' => $members]);
}
```

**Akzeptanzkriterien:**
- [ ] Nur Mitglieder können Liste sehen
- [ ] Rolle wird korrekt angezeigt
- [ ] Owner ist markiert

---

### 2.1.10 Endpoint: PUT /api/companies/{company}/members/{userId}
- [x] **Erledigt**

**Beschreibung:** Rolle eines Mitglieds ändern (nur Admin).

**Request:**
```json
{
    "role": "admin"
}
```

**Response (200):**
```json
{
    "message": "Member role updated"
}
```

**Response (403):**
```json
{
    "message": "Only admins can change member roles"
}
```

**Implementierung:**
```php
public function updateMember(Request $request, Company $company, int $userId): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($company)) {
        return response()->json([
            'message' => 'Only admins can change member roles',
        ], 403);
    }

    if ($userId === $company->owner_id) {
        return response()->json([
            'message' => 'Owner role cannot be changed',
        ], 422);
    }

    $validated = $request->validate([
        'role' => ['required', 'in:admin,user'],
    ]);

    $company->members()->updateExistingPivot($userId, [
        'role' => $validated['role'],
    ]);

    return response()->json([
        'message' => 'Member role updated',
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Company/UpdateMemberTest.php`
```php
<?php

namespace Tests\Feature\Api\Company;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_member_role(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($member->id, ['role' => 'user']);

        $response = $this->actingAs($admin)
            ->putJson("/api/companies/{$company->id}/members/{$member->id}", [
                'role' => 'admin',
            ]);

        $response->assertOk();
        $this->assertEquals('admin', $company->members()->find($member->id)->pivot->role);
    }

    public function test_user_cannot_change_roles(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($user->id, ['role' => 'user']);
        $company->members()->attach($member->id, ['role' => 'user']);

        $response = $this->actingAs($user)
            ->putJson("/api/companies/{$company->id}/members/{$member->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_change_owner_role(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $company = Company::factory()->create(['owner_id' => $owner->id]);

        $company->members()->attach($owner->id, ['role' => 'admin']);
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->putJson("/api/companies/{$company->id}/members/{$owner->id}", [
                'role' => 'user',
            ]);

        $response->assertStatus(422);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können Rollen ändern
- [ ] Owner-Rolle kann NICHT geändert werden
- [ ] Nicht-Admin bekommt 403

---

### 2.1.11 Endpoint: DELETE /api/companies/{company}/members/{userId}
- [x] **Erledigt**

**Beschreibung:** Mitglied aus Firma entfernen (nur Admin).

**Response (200):**
```json
{
    "message": "Member removed"
}
```

**Implementierung:**
```php
public function removeMember(Request $request, Company $company, int $userId): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($company)) {
        return response()->json([
            'message' => 'Only admins can remove members',
        ], 403);
    }

    if ($userId === $company->owner_id) {
        return response()->json([
            'message' => 'Owner cannot be removed',
        ], 422);
    }

    $memberToRemove = User::find($userId);
    if (!$memberToRemove || !$memberToRemove->isMemberOf($company)) {
        return response()->json([
            'message' => 'User is not a member of this company',
        ], 404);
    }

    $company->removeMember($memberToRemove);

    return response()->json([
        'message' => 'Member removed',
    ]);
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können entfernen
- [ ] Owner kann NICHT entfernt werden
- [ ] User wird aus allen Channels der Firma entfernt

---

### 2.1.12 Endpoint: POST /api/companies/{company}/logo
- [x] **Erledigt**

**Beschreibung:** Firmenlogo hochladen (nur Owner).

**Request:** multipart/form-data mit `logo` Feld

**Response (200):**
```json
{
    "message": "Logo uploaded",
    "logo_url": "https://..."
}
```

**Implementierung:**
```php
public function uploadLogo(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if ($company->owner_id !== $user->id) {
        return response()->json([
            'message' => 'Only the owner can update the logo',
        ], 403);
    }

    $request->validate([
        'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
    ]);

    $file = $request->file('logo');

    if ($company->logo_path) {
        Storage::disk('public')->delete($company->logo_path);
    }

    $filename = 'company-logos/' . $company->id . '_' . Str::random(10) . '.' . $file->extension();

    if ($this->imageService->isCompressible($file->getMimeType())) {
        $result = $this->imageService->compressUploadedFile($file, $filename, 'public');
        $path = $result['path'];
    } else {
        $path = $file->storeAs('company-logos', basename($filename), 'public');
    }

    $company->update(['logo_path' => $path]);

    return response()->json([
        'message' => 'Logo uploaded',
        'logo_url' => $company->fresh()->logo_url,
    ]);
}
```

**Akzeptanzkriterien:**
- [ ] Nur Owner kann Logo ändern
- [ ] Altes Logo wird gelöscht
- [ ] Neues Logo wird komprimiert
- [ ] URL wird zurückgegeben

---

## 2.2 Channel Controller [BE]

### 2.2.1 ChannelController erstellen
- [x] **Erledigt**

→ *Abhängig von 2.1.1*

**Durchführung:**
```bash
php artisan make:controller Api/ChannelController
```

**Datei:** `app/Http/Controllers/Api/ChannelController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelJoinRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    // Methoden folgen
}
```

---

### 2.2.2 Endpoint: GET /api/companies/{company}/channels
- [x] **Erledigt**

**Beschreibung:** Alle Channels einer Firma auflisten.

**Response (200):**
```json
{
    "channels": [
        {
            "id": 1,
            "name": "Allgemein",
            "description": "...",
            "is_private": false,
            "is_member": true,
            "members_count": 5,
            "has_pending_request": false
        }
    ]
}
```

**Implementierung:**
```php
public function index(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($company)) {
        return response()->json([
            'message' => 'You are not a member of this company',
        ], 403);
    }

    $channels = $company->channels()
        ->withCount('members')
        ->get()
        ->map(fn ($channel) => [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_private' => $channel->is_private,
            'is_member' => $user->isMemberOfChannel($channel),
            'members_count' => $channel->members_count,
            'has_pending_request' => $channel->pendingJoinRequests()
                ->where('user_id', $user->id)
                ->exists(),
        ]);

    return response()->json(['channels' => $channels]);
}
```

**Akzeptanzkriterien:**
- [ ] Nur Firmenmitglieder sehen Channels
- [ ] is_member ist korrekt pro Channel
- [ ] has_pending_request zeigt offene Anfragen

---

### 2.2.3 Endpoint: POST /api/companies/{company}/channels
- [x] **Erledigt**

**Beschreibung:** Neuen Channel erstellen (nur Admin).

**Request:**
```json
{
    "name": "Development",
    "description": "Entwickler-Channel",
    "is_private": true
}
```

**Response (201):**
```json
{
    "message": "Channel created",
    "channel": { ... }
}
```

**Implementierung:**
```php
public function store(Request $request, Company $company): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($company)) {
        return response()->json([
            'message' => 'Only admins can create channels',
        ], 403);
    }

    $validated = $request->validate([
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],
        'is_private' => ['boolean'],
    ]);

    $channel = $company->channels()->create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'is_private' => $validated['is_private'] ?? true,
        'created_by' => $user->id,
    ]);

    // Ersteller hinzufügen
    $channel->addMember($user);

    // Bei öffentlichem Channel: Alle Firmenmitglieder hinzufügen
    if (!$channel->is_private) {
        $company->members->each(fn ($member) => $channel->addMember($member, $user));
    }

    return response()->json([
        'message' => 'Channel created',
        'channel' => [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_private' => $channel->is_private,
            'members_count' => $channel->members()->count(),
        ],
    ], 201);
}
```

**Unit Test:** `tests/Feature/Api/Channel/CreateChannelTest.php`
```php
<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_channel(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Neuer Channel',
                'is_private' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channels', [
            'company_id' => $company->id,
            'name' => 'Neuer Channel',
        ]);
    }

    public function test_user_cannot_create_channel(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id, ['role' => 'user']);

        $response = $this->actingAs($user)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Test Channel',
            ]);

        $response->assertStatus(403);
    }

    public function test_public_channel_includes_all_members(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($member->id, ['role' => 'user']);

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Public Channel',
                'is_private' => false,
            ]);

        $channel = Channel::where('name', 'Public Channel')->first();

        $this->assertTrue($admin->isMemberOfChannel($channel));
        $this->assertTrue($member->isMemberOfChannel($channel));
    }

    public function test_private_channel_only_includes_creator(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($member->id, ['role' => 'user']);

        $this->actingAs($admin)
            ->postJson("/api/companies/{$company->id}/channels", [
                'name' => 'Private Channel',
                'is_private' => true,
            ]);

        $channel = Channel::where('name', 'Private Channel')->first();

        $this->assertTrue($admin->isMemberOfChannel($channel));
        $this->assertFalse($member->isMemberOfChannel($channel));
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können erstellen
- [ ] Ersteller ist automatisch Mitglied
- [ ] Öffentlicher Channel hat alle Firmenmitglieder
- [ ] Privater Channel hat nur Ersteller

---

### 2.2.4 Endpoint: GET /api/channels/{channel}
- [x] **Erledigt**

**Beschreibung:** Channel-Details abrufen.

**Implementierung:**
```php
public function show(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($channel->company)) {
        return response()->json([
            'message' => 'You are not a member of this company',
        ], 403);
    }

    return response()->json([
        'channel' => [
            'id' => $channel->id,
            'company_id' => $channel->company_id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_private' => $channel->is_private,
            'members_count' => $channel->members()->count(),
            'created_at' => $channel->created_at->toIso8601String(),
        ],
        'is_member' => $user->isMemberOfChannel($channel),
    ]);
}
```

---

### 2.2.5 Endpoint: PUT /api/channels/{channel}
- [x] **Erledigt**

**Beschreibung:** Channel aktualisieren (nur Admin).

**Implementierung:**
```php
public function update(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can update channels',
        ], 403);
    }

    $validated = $request->validate([
        'name' => ['sometimes', 'string', 'min:2', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],
        'is_private' => ['sometimes', 'boolean'],
    ]);

    $wasPrivate = $channel->is_private;
    $channel->update($validated);

    // Wenn von privat zu öffentlich gewechselt: Alle Firmenmitglieder hinzufügen
    if ($wasPrivate && isset($validated['is_private']) && !$validated['is_private']) {
        $channel->company->members->each(fn ($member) => $channel->addMember($member, $user));
    }

    return response()->json([
        'message' => 'Channel updated',
        'channel' => [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_private' => $channel->is_private,
        ],
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Channel/UpdateChannelTest.php`
```php
<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_making_private_channel_public_adds_all_members(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($member->id, ['role' => 'user']);

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);
        $channel->members()->attach($admin->id);

        // Member ist nicht im privaten Channel
        $this->assertFalse($member->isMemberOfChannel($channel));

        // Channel auf öffentlich setzen
        $this->actingAs($admin)
            ->putJson("/api/channels/{$channel->id}", [
                'is_private' => false,
            ]);

        // Jetzt sollte Member auch drin sein
        $this->assertTrue($member->isMemberOfChannel($channel->fresh()));
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können ändern
- [ ] Private→Public fügt alle Firmenmitglieder hinzu

---

### 2.2.6 Endpoint: DELETE /api/channels/{channel}
- [x] **Erledigt**

**Beschreibung:** Channel löschen (nur Admin).

**Implementierung:**
```php
public function destroy(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can delete channels',
        ], 403);
    }

    // Prüfen ob letzter Channel
    if ($channel->company->channels()->count() === 1) {
        return response()->json([
            'message' => 'Cannot delete the last channel of a company',
        ], 422);
    }

    $channel->delete();

    return response()->json([
        'message' => 'Channel deleted',
    ]);
}
```

**Unit Test:** `tests/Feature/Api/Channel/DeleteChannelTest.php`
```php
<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_delete_last_channel(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $channel = Channel::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/channels/{$channel->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot delete the last channel of a company']);
    }

    public function test_admin_can_delete_channel_when_others_exist(): void
    {
        $admin = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($admin->id, ['role' => 'admin']);

        Channel::factory()->count(2)->create(['company_id' => $company->id]);
        $channelToDelete = $company->channels->first();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/channels/{$channelToDelete->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('channels', ['id' => $channelToDelete->id]);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können löschen
- [ ] Letzter Channel kann NICHT gelöscht werden
- [ ] Alle Nachrichten werden mitgelöscht (CASCADE)

---

### 2.2.7 Endpoint: GET /api/channels/{channel}/members
- [x] **Erledigt**

**Beschreibung:** Channel-Mitglieder auflisten.

**Implementierung:**
```php
public function members(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOfChannel($channel)) {
        return response()->json([
            'message' => 'You are not a member of this channel',
        ], 403);
    }

    $members = $channel->members()
        ->get()
        ->map(fn ($member) => [
            'id' => $member->id,
            'username' => $member->username,
            'avatar_url' => $member->avatar_url,
            'status' => $member->status,
            'joined_at' => $member->pivot->joined_at,
        ]);

    return response()->json(['members' => $members]);
}
```

---

### 2.2.8 Endpoint: POST /api/channels/{channel}/members
- [x] **Erledigt**

**Beschreibung:** Mitglied zum Channel einladen (nur Admin).

**Request:**
```json
{
    "user_id": 5
}
```

**Implementierung:**
```php
public function addMember(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can add members',
        ], 403);
    }

    $validated = $request->validate([
        'user_id' => ['required', 'exists:users,id'],
    ]);

    $userToAdd = User::find($validated['user_id']);

    if (!$userToAdd->isMemberOf($channel->company)) {
        return response()->json([
            'message' => 'User must be a member of the company first',
        ], 422);
    }

    if ($userToAdd->isMemberOfChannel($channel)) {
        return response()->json([
            'message' => 'User is already a member of this channel',
        ], 422);
    }

    $channel->addMember($userToAdd, $user);

    return response()->json([
        'message' => 'Member added to channel',
    ]);
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können einladen
- [ ] Nur Firmenmitglieder können hinzugefügt werden

---

### 2.2.9 Endpoint: DELETE /api/channels/{channel}/members/{userId}
- [x] **Erledigt**

**Beschreibung:** Mitglied aus Channel entfernen (nur Admin).

**Implementierung:**
```php
public function removeMember(Request $request, Channel $channel, int $userId): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can remove members',
        ], 403);
    }

    $channel->members()->detach($userId);

    return response()->json([
        'message' => 'Member removed from channel',
    ]);
}
```

---

### 2.2.10 Endpoint: POST /api/channels/{channel}/join-request
- [x] **Erledigt**

**Beschreibung:** Beitrittsanfrage für privaten Channel stellen.

**Request:**
```json
{
    "message": "Ich möchte beitreten"
}
```

**Response (201):**
```json
{
    "message": "Join request submitted",
    "request": {
        "id": 1,
        "status": "pending",
        "created_at": "..."
    }
}
```

**Implementierung:**
```php
public function requestJoin(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isMemberOf($channel->company)) {
        return response()->json([
            'message' => 'You must be a member of the company first',
        ], 403);
    }

    if ($user->isMemberOfChannel($channel)) {
        return response()->json([
            'message' => 'You are already a member of this channel',
        ], 422);
    }

    if ($channel->pendingJoinRequests()->where('user_id', $user->id)->exists()) {
        return response()->json([
            'message' => 'You already have a pending request',
        ], 422);
    }

    $validated = $request->validate([
        'message' => ['nullable', 'string', 'max:500'],
    ]);

    $joinRequest = ChannelJoinRequest::create([
        'channel_id' => $channel->id,
        'user_id' => $user->id,
        'message' => $validated['message'] ?? null,
        'status' => 'pending',
    ]);

    return response()->json([
        'message' => 'Join request submitted',
        'request' => [
            'id' => $joinRequest->id,
            'status' => $joinRequest->status,
            'created_at' => $joinRequest->created_at->toIso8601String(),
        ],
    ], 201);
}
```

**Unit Test:** `tests/Feature/Api/Channel/JoinRequestTest.php`
```php
<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use App\Models\ChannelJoinRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_member_can_request_to_join(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/join-request", [
                'message' => 'Ich möchte beitreten',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('channel_join_requests', [
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_submit_duplicate_pending_request(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($user->id);

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);

        // Erste Anfrage
        $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/join-request");

        // Zweite Anfrage
        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/join-request");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'You already have a pending request']);
    }

    public function test_non_company_member_cannot_request(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/join-request");

        $response->assertStatus(403);
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Firmenmitglieder können Anfragen stellen
- [ ] Keine doppelten pending Anfragen
- [ ] Bereits Mitglieder können keine Anfrage stellen

---

### 2.2.11 Endpoint: GET /api/channels/{channel}/join-requests
- [x] **Erledigt**

**Beschreibung:** Beitrittsanfragen auflisten (nur Admin).

**Response (200):**
```json
{
    "requests": [
        {
            "id": 1,
            "user": {
                "id": 2,
                "username": "Max",
                "email": "max@test.de",
                "avatar_url": "..."
            },
            "message": "Ich möchte beitreten",
            "created_at": "2024-..."
        }
    ]
}
```

**Implementierung:**
```php
public function joinRequests(Request $request, Channel $channel): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can view join requests',
        ], 403);
    }

    $requests = $channel->pendingJoinRequests()
        ->with('user:id,username,email,avatar_path')
        ->get()
        ->map(fn ($req) => [
            'id' => $req->id,
            'user' => [
                'id' => $req->user->id,
                'username' => $req->user->username,
                'email' => $req->user->email,
                'avatar_url' => $req->user->avatar_url,
            ],
            'message' => $req->message,
            'created_at' => $req->created_at->toIso8601String(),
        ]);

    return response()->json(['requests' => $requests]);
}
```

---

### 2.2.12 Endpoint: PUT /api/channels/{channel}/join-requests/{joinRequest}
- [x] **Erledigt**

**Beschreibung:** Beitrittsanfrage genehmigen oder ablehnen (nur Admin).

**Request:**
```json
{
    "action": "approve"
}
```

**Implementierung:**
```php
public function handleJoinRequest(Request $request, Channel $channel, ChannelJoinRequest $joinRequest): JsonResponse
{
    $user = $request->user();

    if (!$user->isAdminOf($channel->company)) {
        return response()->json([
            'message' => 'Only admins can handle join requests',
        ], 403);
    }

    if ($joinRequest->channel_id !== $channel->id) {
        return response()->json([
            'message' => 'Request does not belong to this channel',
        ], 404);
    }

    $validated = $request->validate([
        'action' => ['required', 'in:approve,reject'],
    ]);

    if ($validated['action'] === 'approve') {
        $joinRequest->approve($user);
        $message = 'Request approved';
    } else {
        $joinRequest->reject($user);
        $message = 'Request rejected';
    }

    return response()->json(['message' => $message]);
}
```

**Unit Test:** `tests/Feature/Api/Channel/HandleJoinRequestTest.php`
```php
<?php

namespace Tests\Feature\Api\Channel;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use App\Models\ChannelJoinRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandleJoinRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_request(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($user->id);

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);
        $channel->members()->attach($admin->id);

        $joinRequest = ChannelJoinRequest::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/channels/{$channel->id}/join-requests/{$joinRequest->id}", [
                'action' => 'approve',
            ]);

        $response->assertOk();
        $this->assertEquals('approved', $joinRequest->fresh()->status);
        $this->assertTrue($user->isMemberOfChannel($channel));
    }

    public function test_admin_can_reject_request(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->members()->attach($admin->id, ['role' => 'admin']);
        $company->members()->attach($user->id);

        $channel = Channel::factory()->create([
            'company_id' => $company->id,
            'is_private' => true,
        ]);

        $joinRequest = ChannelJoinRequest::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/channels/{$channel->id}/join-requests/{$joinRequest->id}", [
                'action' => 'reject',
            ]);

        $response->assertOk();
        $this->assertEquals('rejected', $joinRequest->fresh()->status);
        $this->assertFalse($user->isMemberOfChannel($channel));
    }
}
```

**Akzeptanzkriterien:**
- [ ] Nur Admins können entscheiden
- [ ] Approve fügt User zum Channel hinzu
- [ ] Reject ändert nur Status

---

## 2.3 Routes & Tests [BE]

### 2.3.1 Company & Channel Routes definieren
- [x] **Erledigt**

→ *Abhängig von 2.1.1 bis 2.2.12*

**Datei:** `routes/api.php` ergänzen:
```php
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ChannelController;

Route::middleware('auth:sanctum')->group(function () {
    // ... bestehende Routes ...

    // Companies
    Route::get('my-companies', [CompanyController::class, 'myCompanies']);
    Route::get('companies/search', [CompanyController::class, 'search']);

    Route::prefix('companies')->group(function () {
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('{company}', [CompanyController::class, 'show']);
        Route::put('{company}', [CompanyController::class, 'update']);
        Route::post('{company}/join', [CompanyController::class, 'join']);
        Route::post('{company}/leave', [CompanyController::class, 'leave']);
        Route::post('{company}/logo', [CompanyController::class, 'uploadLogo']);
        Route::get('{company}/members', [CompanyController::class, 'members']);
        Route::put('{company}/members/{userId}', [CompanyController::class, 'updateMember']);
        Route::delete('{company}/members/{userId}', [CompanyController::class, 'removeMember']);
        Route::get('{company}/channels', [ChannelController::class, 'index']);
        Route::post('{company}/channels', [ChannelController::class, 'store']);
    });

    // Channels
    Route::prefix('channels')->group(function () {
        Route::get('{channel}', [ChannelController::class, 'show']);
        Route::put('{channel}', [ChannelController::class, 'update']);
        Route::delete('{channel}', [ChannelController::class, 'destroy']);
        Route::get('{channel}/members', [ChannelController::class, 'members']);
        Route::post('{channel}/members', [ChannelController::class, 'addMember']);
        Route::delete('{channel}/members/{userId}', [ChannelController::class, 'removeMember']);
        Route::post('{channel}/join-request', [ChannelController::class, 'requestJoin']);
        Route::get('{channel}/join-requests', [ChannelController::class, 'joinRequests']);
        Route::put('{channel}/join-requests/{joinRequest}', [ChannelController::class, 'handleJoinRequest']);
    });
});
```

**Akzeptanzkriterien:**
- [ ] `php artisan route:list --path=api/companies` zeigt alle Company Routes
- [ ] `php artisan route:list --path=api/channels` zeigt alle Channel Routes

---

### 2.3.2 Alle Phase 2 Tests ausführen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan test --filter=Company
php artisan test --filter=Channel
php artisan test
```

**Akzeptanzkriterien:**
- [ ] Alle Tests grün
- [ ] Mindestens 50 Tests insgesamt

---

### 2.3.3 Git Commit & Tag
- [x] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 2: Company & Channel System"
git tag v0.2.0
```

---

## Phase 2 Zusammenfassung

### Erstellte Dateien
- 2 neue Controllers (CompanyController, ChannelController)
- ~15 neue Test-Dateien
- Route-Definitionen

### Neue API Endpoints

**Company Endpoints:**
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | /api/my-companies | Meine Firmen |
| GET | /api/companies/search | Firmen suchen |
| POST | /api/companies | Firma erstellen |
| GET | /api/companies/{id} | Firma-Details |
| PUT | /api/companies/{id} | Firma ändern |
| POST | /api/companies/{id}/join | Firma beitreten |
| POST | /api/companies/{id}/leave | Firma verlassen |
| POST | /api/companies/{id}/logo | Logo hochladen |
| GET | /api/companies/{id}/members | Mitglieder |
| PUT | /api/companies/{id}/members/{userId} | Rolle ändern |
| DELETE | /api/companies/{id}/members/{userId} | Mitglied entfernen |

**Channel Endpoints:**
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| GET | /api/companies/{id}/channels | Channels auflisten |
| POST | /api/companies/{id}/channels | Channel erstellen |
| GET | /api/channels/{id} | Channel-Details |
| PUT | /api/channels/{id} | Channel ändern |
| DELETE | /api/channels/{id} | Channel löschen |
| GET | /api/channels/{id}/members | Channel-Mitglieder |
| POST | /api/channels/{id}/members | Mitglied hinzufügen |
| DELETE | /api/channels/{id}/members/{userId} | Mitglied entfernen |
| POST | /api/channels/{id}/join-request | Beitrittsanfrage |
| GET | /api/channels/{id}/join-requests | Anfragen auflisten |
| PUT | /api/channels/{id}/join-requests/{id} | Anfrage bearbeiten |

### Nächste Phase
→ Weiter mit `phase-3-chat.md`
