# Data Scoping Rules

The only architectural difference between admin and client is **data access**:

- **Admin** sees all `Channel`, `MediaItem`, `Schedule`, etc.
- **Client** sees only what they own or are assigned to.

## The Two Primitives

Located in `app/Models/User.php`:

```php
// Returns a Collection of channel IDs the user can access
public function effectiveChannelIds(): Collection
{
    $owned = $this->channels()->pluck('channels.id');
    $assigned = $this->assignedChannels()->pluck('channels.id');
    return $owned->merge($assigned)->unique()->values();
}

// Returns true if the user can access a specific channel
public function canAccessChannel(Channel $channel): bool
{
    if ($channel->owner_id === $this->id) return true;
    if ($this->relationLoaded('assignedChannels')) {
        return $this->assignedChannels->contains('id', $channel->id);
    }
    return $this->assignedChannels()->where('channels.id', $channel->id)->exists();
}
```

## Client Controller Patterns

### Pattern A — Index/listing methods (filter the query)

Every client controller method that lists resources MUST filter by `effectiveChannelIds()`:

```php
public function index(Request $request)
{
    $user = $request->user();
    $channelIds = $user->effectiveChannelIds();
    $channels = Channel::whereIn('id', $channelIds)
        ->where('status', '!=', 'archived')
        ->orderBy('display_name')
        ->paginate(25);
    return view('client.channels.index', compact('channels', 'ownedChannelIds'));
}
```

Reference: `app/Http/Controllers/Client/ScheduleController.php:17`, `Client/DashboardController.php:17`.

### Pattern B — Mutating methods (check access before write)

Every client controller method that mutates a channel (update, store, delete) MUST check `canAccessChannel()` first:

```php
public function update(Request $request, Channel $channel)
{
    $user = $request->user();
    if (! $user->canAccessChannel($channel)) {
        abort(403, 'No tienes acceso a este canal.');
    }
    // ... proceed with update
}
```

Reference: `app/Http/Controllers/Client/ChannelController.php:32`.

### Pattern C — View data for showing "is owner" badges

The client views show owner-only buttons (Editar, Pantalla). To decide which rows get those buttons, the controller passes an `$ownedChannelIds` list:

```php
$ownedChannelIds = $user->channels()->pluck('channels.id')->toArray();
return view('client.channels.index', [
    'channels' => $channels,
    'ownedChannelIds' => $ownedChannelIds,  // for owner-only UI elements
]);
```

Then in the view:

```blade
@if(in_array($ch->id, $ownedChannelIds))
    <button>Editar</button>
@endif
```

## Admin Controller Patterns

**No scoping.** Admin controllers see all data. Don't add `effectiveChannelIds()` to admin code — it would silently hide data the admin needs.

## Anti-Patterns

❌ **Trusting view-level hiding.** Even if a button isn't shown in the client UI, the controller endpoint must still enforce scoping. Anyone with the URL can hit the endpoint directly.

❌ **Re-checking access on every query.** Use `whereIn('id', $effectiveChannelIds)` once at the top of the controller method. Don't try to chain scopes.

❌ **Hardcoding channel lists in tests/fixtures.** Use the model methods to keep tests aligned with the actual scoping rules.

## How to Use This Reference

When implementing a new client controller method:

1. Read the existing `Client/*Controller.php` for the same domain to see the established pattern.
2. For listings: copy Pattern A.
3. For mutations: copy Pattern B.
4. For UI badges: copy Pattern C and the matching view check.
5. Grep for `canAccessChannel` to verify the new method enforces it.
6. Grep for `effectiveChannelIds` to verify the new listing filters by it.