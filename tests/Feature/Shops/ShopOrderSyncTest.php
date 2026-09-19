<?php

use App\Actions\Shops\ImportShopOrders;
use App\Enums\OrganizationRole;
use App\Enums\ShopSyncStatus;
use App\Jobs\Shops\SyncShopOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Build a WooCommerce order payload, shaped the way the REST API returns one.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function wooOrder(int $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'number' => (string) $id,
        'status' => 'completed',
        'currency' => 'chf',
        'total' => '120.50',
        'total_tax' => '8.60',
        'shipping_total' => '7.90',
        'shipping_tax' => '0.00',
        'cart_tax' => '8.60',
        'discount_total' => '0.00',
        'discount_tax' => '0.00',
        'customer_id' => 42,
        'billing' => [
            'first_name' => 'Anna',
            'last_name' => 'Meier',
            'email' => 'anna@example.test',
            'country' => 'CH',
        ],
        'payment_method_title' => 'TWINT',
        'date_created_gmt' => '2026-01-10T09:00:00',
        'date_paid_gmt' => '2026-01-10T09:01:00',
        'date_completed_gmt' => null,
        'date_modified_gmt' => '2026-01-10T09:01:00',
        'refunds' => [],
        'line_items' => [[
            'id' => $id * 10,
            'name' => 'Wooden train',
            'sku' => 'TRAIN-1',
            'product_id' => 501,
            'variation_id' => 0,
            'quantity' => 2,
            'subtotal' => '111.90',
            'subtotal_tax' => '8.60',
            'total' => '111.90',
            'total_tax' => '8.60',
        ]],
        ...$overrides,
    ];
}

/**
 * Build a shop whose credentials are known, so requests can be asserted on.
 */
function syncableShop(?Organization $organization = null): Shop
{
    return Shop::factory()
        ->for($organization ?? Organization::factory())
        ->connected()
        ->create(['url' => 'https://toysonline.test']);
}

test('the first sync imports the shop history and its line items', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1), wooOrder(2)])]);

    $shop = syncableShop();

    $result = app(ImportShopOrders::class)->handle($shop);

    expect($result->importedCount)->toBe(2)
        ->and($result->status)->toBe(ShopSyncStatus::Synced);

    $order = $shop->orders()->where('woo_id', 1)->sole();

    expect($order->status)->toBe('completed')
        ->and($order->currency)->toBe('CHF')
        ->and($order->total)->toBe('120.5000')
        ->and($order->total_tax)->toBe('8.6000')
        ->and($order->customer_name)->toBe('Anna Meier')
        ->and($order->customer_email)->toBe('anna@example.test')
        ->and($order->billing_country)->toBe('CH')
        ->and($order->placed_at->toDateTimeString())->toBe('2026-01-10 09:00:00')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->sku)->toBe('TRAIN-1')
        ->and($order->items->first()->quantity)->toBe(2);

    // The GMT timestamps carry no offset and must not be read as local time.
    expect($order->placed_at->timezone->getName())->toBe('UTC');
});

test('the first sync asks woocommerce for the oldest orders first, in gmt', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1)])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/wp-json/wc/v3/orders')
            && $query['orderby'] === 'date'
            && $query['order'] === 'asc'
            && $query['status'] === 'any'
            && $query['dates_are_gmt'] === 'true'
            && $query['per_page'] === '100';
    });
});

test('a backfill walks forward from where the last page ended', function () {
    config(['services.woocommerce.sync_page_size' => 2]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([
                wooOrder(1, ['date_created_gmt' => '2026-01-10T09:00:00']),
                wooOrder(2, ['date_created_gmt' => '2026-01-11T09:00:00']),
            ])
            ->push([wooOrder(3, ['date_created_gmt' => '2026-01-12T09:00:00'])]),
    ]);

    $shop = syncableShop();

    $result = app(ImportShopOrders::class)->handle($shop);

    expect($result->importedCount)->toBe(3)
        ->and($shop->orders()->count())->toBe(3);

    $state = $shop->syncState()->sole();

    expect($state->hasBackfilled())->toBeTrue()
        ->and($state->status)->toBe(ShopSyncStatus::Synced);

    // The second page picks up from the last order of the first, overlapping
    // by a second so nothing sharing a timestamp is skipped.
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'after=2026-01-11T08:59:59'));
});

test('a backfill longer than one run stops and asks to be continued', function () {
    config([
        'services.woocommerce.sync_page_size' => 1,
        'services.woocommerce.sync_max_pages_per_run' => 2,
    ]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, ['date_created_gmt' => '2026-01-10T09:00:00'])])
            ->push([wooOrder(2, ['date_created_gmt' => '2026-01-11T09:00:00'])])
            ->push([wooOrder(3, ['date_created_gmt' => '2026-01-12T09:00:00'])]),
    ]);

    $shop = syncableShop();

    $result = app(ImportShopOrders::class)->handle($shop);

    expect($result->hasMore)->toBeTrue()
        ->and($result->status)->toBe(ShopSyncStatus::Backfilling)
        ->and($shop->orders()->count())->toBe(2);

    $state = $shop->syncState()->sole();

    expect($state->hasBackfilled())->toBeFalse()
        ->and($state->backfill_cursor->toDateTimeString())->toBe('2026-01-11 09:00:00');

    Http::assertSentCount(2);
});

test('once the history is in, later runs ask only for what changed', function () {
    Http::fake(['toysonline.test/*' => Http::response([])]);

    $shop = syncableShop();
    $shop->syncStateOrCreate()->update([
        'backfill_completed_at' => now()->subDay(),
        'last_synced_at' => now()->subHour(),
    ]);

    $result = app(ImportShopOrders::class)->handle($shop->fresh());

    expect($result->status)->toBe(ShopSyncStatus::Synced)
        ->and($result->message)->toBe('Already up to date.');

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['orderby'] === 'modified'
            && isset($query['modified_after'])
            // The window reaches back past the last run to tolerate clock skew.
            && $query['modified_after'] < now()->subHour()->toIso8601String();
    });
});

test('an incremental run that hits its page limit resumes where it stopped', function () {
    config([
        'services.woocommerce.sync_page_size' => 1,
        'services.woocommerce.sync_max_pages_per_run' => 2,
    ]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, ['date_modified_gmt' => '2026-02-01T10:00:00'])])
            ->push([wooOrder(2, ['date_modified_gmt' => '2026-02-01T11:00:00'])])
            ->push([wooOrder(3, ['date_modified_gmt' => '2026-02-01T12:00:00'])]),
    ]);

    $shop = syncableShop();
    $shop->syncStateOrCreate()->update([
        'backfill_completed_at' => now()->subDay(),
        'last_synced_at' => now()->subDay(),
    ]);

    $result = app(ImportShopOrders::class)->handle($shop->fresh());

    expect($result->hasMore)->toBeTrue()
        ->and($shop->orders()->count())->toBe(2);

    // Advancing the mark to "now" here would silently drop every order
    // modified after the ones this run managed to fetch.
    expect($shop->syncState()->sole()->last_synced_at->toDateTimeString())
        ->toBe('2026-02-01 11:00:00');
});

test('an incremental run brings a status change and a refund up to date', function () {
    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1)])
            ->push([wooOrder(1, [
                'status' => 'refunded',
                'refunds' => [['id' => 9, 'reason' => 'Returned', 'total' => '-120.50']],
            ])]),
    ]);

    $shop = syncableShop();

    app(ImportShopOrders::class)->handle($shop);

    expect($shop->orders()->sole())
        ->status->toBe('completed')
        ->refunded_total->toBe('0.0000');

    app(ImportShopOrders::class)->handle($shop->fresh());

    expect($shop->orders()->count())->toBe(1)
        ->and($shop->orders()->sole())
        ->status->toBe('refunded')
        ->refunded_total->toBe('120.5000');
});

test('a line item removed in woocommerce disappears here too', function () {
    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, [
                'line_items' => [
                    ['id' => 10, 'name' => 'Wooden train', 'quantity' => 1, 'total' => '50.00'],
                    ['id' => 11, 'name' => 'Track set', 'quantity' => 1, 'total' => '70.50'],
                ],
            ])])
            ->push([wooOrder(1, [
                'line_items' => [['id' => 10, 'name' => 'Wooden train', 'quantity' => 1, 'total' => '50.00']],
            ])]),
    ]);

    $shop = syncableShop();

    app(ImportShopOrders::class)->handle($shop);

    expect($shop->orders()->sole()->items)->toHaveCount(2);

    app(ImportShopOrders::class)->handle($shop->fresh());

    expect($shop->orders()->sole()->items->pluck('woo_id')->all())->toBe([10]);
    expect(OrderItem::count())->toBe(1);
});

test('an unreachable shop is recorded as a failed sync rather than crashing', function () {
    Http::fake(['toysonline.test/*' => Http::failedConnection()]);

    $shop = syncableShop();

    $result = app(ImportShopOrders::class)->handle($shop);

    expect($result->succeeded())->toBeFalse();

    $state = $shop->syncState()->sole();

    expect($state->status)->toBe(ShopSyncStatus::Failed)
        ->and($state->last_error)->not->toBeNull();
});

test('an error response is recorded with the reason the shop gave', function () {
    Http::fake(['toysonline.test/*' => Http::response(['message' => 'Sorry, you cannot list resources.'], 403)]);

    $shop = syncableShop();

    app(ImportShopOrders::class)->handle($shop);

    expect($shop->syncState()->sole())
        ->status->toBe(ShopSyncStatus::Failed)
        ->last_error->toContain('HTTP 403')
        ->last_error->toContain('Sorry, you cannot list resources.');
});

test('a failed sync recovers on the next run', function () {
    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push('', 500)
            ->push([wooOrder(1)]),
    ]);

    $shop = syncableShop();
    app(ImportShopOrders::class)->handle($shop);

    expect($shop->syncState()->sole()->status)->toBe(ShopSyncStatus::Failed);

    app(ImportShopOrders::class)->handle($shop->fresh());

    expect($shop->syncState()->sole())
        ->status->toBe(ShopSyncStatus::Synced)
        ->last_error->toBeNull();
});

test('orders are scoped to the shop they came from', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1)])]);

    $first = syncableShop();
    $second = syncableShop();

    app(ImportShopOrders::class)->handle($first);
    app(ImportShopOrders::class)->handle($second);

    // The same WooCommerce order id in two shops must stay two rows.
    expect(Order::count())->toBe(2)
        ->and($first->orders()->count())->toBe(1)
        ->and($second->orders()->count())->toBe(1);
});

test('deleting a shop removes its orders and line items', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1)])]);

    $shop = syncableShop();
    app(ImportShopOrders::class)->handle($shop);

    expect(Order::count())->toBe(1)->and(OrderItem::count())->toBe(1);

    $shop->delete();

    expect(Order::count())->toBe(0)->and(OrderItem::count())->toBe(0);
});

test('organization admins can queue a sync', function () {
    Queue::fake();

    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($admin, ['role' => OrganizationRole::Admin->value]);

    $shop = syncableShop($organization);

    $this
        ->actingAs($admin)
        ->from(route('shops.index', $organization))
        ->post(route('shops.sync.store', [$organization, $shop]))
        ->assertRedirect(route('shops.index', $organization))
        ->assertInertiaFlash('toast.type', 'success');

    Queue::assertPushed(SyncShopOrders::class, 1);
});

test('organization members cannot queue a sync', function () {
    Queue::fake();

    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->members()->attach($member, ['role' => OrganizationRole::Member->value]);

    $shop = syncableShop($organization);

    $this
        ->actingAs($member)
        ->post(route('shops.sync.store', [$organization, $shop]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('a shop belonging to another organization cannot be synced', function () {
    Queue::fake();

    $user = User::factory()->create();
    $shop = syncableShop();

    $this
        ->actingAs($user)
        ->post(route('shops.sync.store', [$user->currentOrganization, $shop]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('the tax inside a refund is imported alongside the refunded amount', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1, [
        'total' => '248.60',
        'total_tax' => '18.63',
        // WooCommerce reports each refund as a negative, tax included.
        'refunds' => [
            ['id' => 900, 'reason' => '', 'total' => '-149.90', 'total_tax' => '-11.23'],
        ],
    ])])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    $order = Order::sole();

    expect((float) $order->refunded_total)->toBe(149.90)
        ->and((float) $order->refunded_tax)->toBe(11.23);
});

test('an order with no refunds carries no refunded tax', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1)])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    expect((float) Order::sole()->refunded_tax)->toBe(0.0);
});

/**
 * WooCommerce normalises both tax setups into the same shape before the REST
 * API ever sees them: a line item's `total` always excludes tax, and the
 * order's `total` always includes it, whether the shop enters prices with tax
 * in them or without. 3AG B2B enters them without and every other shop here
 * enters them with, and their orders arrive identical in structure.
 *
 * This pins that, because it is the reason nothing in the importer converts
 * anything — and the day it stops being true, the totals would quietly drift.
 */
test('orders arrive the same shape whether a shop prices with or without tax', function () {
    Http::fake(['toysonline.test/*' => Http::response([
        // Priced excluding tax: 1,935.15 of goods, 9.50 shipping, 157.46 tax.
        wooOrder(1, [
            'total' => '2102.11',
            'total_tax' => '157.46',
            'shipping_total' => '9.50',
            'shipping_tax' => '0.77',
            'cart_tax' => '156.69',
            'line_items' => [[
                'id' => 10, 'name' => 'Pallet', 'sku' => 'PAL-1', 'product_id' => 1,
                'variation_id' => 0, 'quantity' => 1,
                'subtotal' => '1935.15', 'subtotal_tax' => '156.69',
                'total' => '1935.15', 'total_tax' => '156.69',
            ]],
        ]),
    ])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    $order = Order::with('items')->sole();
    $item = $order->items->sole();

    // The order total is what the customer paid, tax included...
    expect((float) $order->total)->toBe(2102.11)
        ->and((float) $order->total_tax)->toBe(157.46)
        // ...and it is the line items plus shipping plus that tax.
        ->and((float) $item->total + (float) $order->shipping_total + (float) $order->total_tax)
        ->toBe(2102.11)
        // The line item's own total excludes tax, which is carried separately.
        ->and((float) $item->total)->toBe(1935.15)
        ->and((float) $item->total_tax)->toBe(156.69)
        // Which is the same as saying: cart tax plus shipping tax.
        ->and((float) $order->cart_tax + (float) $order->shipping_tax)->toBe(157.46);
});

/**
 * Every column the importer writes has to be listed twice: once on the row it
 * builds and once among the columns the upsert is allowed to refresh. Miss the
 * second and new orders look right while every order already in the table
 * keeps its old value forever, which is invisible until someone reconciles.
 */
test('every column the importer writes is refreshed on a re-import', function () {
    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1)])
            ->push([wooOrder(1, [
                'status' => 'refunded',
                'number' => '1-A',
                'total' => '250.00',
                'total_tax' => '18.75',
                'shipping_total' => '12.00',
                'shipping_tax' => '0.90',
                'cart_tax' => '17.85',
                'discount_total' => '5.00',
                'discount_tax' => '0.35',
                'customer_id' => 99,
                'billing' => ['first_name' => 'Ben', 'last_name' => 'Roth', 'email' => 'ben@example.test', 'country' => 'DE'],
                'payment_method_title' => 'Invoice',
                'currency' => 'eur',
                'refunds' => [['id' => 900, 'reason' => '', 'total' => '-250.00', 'total_tax' => '-18.75']],
            ])]),
    ]);

    $shop = syncableShop();

    app(ImportShopOrders::class)->handle($shop);
    $shop->syncStateOrCreate()->update(['backfill_completed_at' => now(), 'last_synced_at' => now()]);
    app(ImportShopOrders::class)->handle($shop->fresh());

    $order = Order::sole();

    expect($order->status)->toBe('refunded')
        ->and($order->number)->toBe('1-A')
        ->and($order->currency)->toBe('EUR')
        ->and((float) $order->total)->toBe(250.0)
        ->and((float) $order->total_tax)->toBe(18.75)
        ->and((float) $order->shipping_total)->toBe(12.0)
        ->and((float) $order->shipping_tax)->toBe(0.9)
        ->and((float) $order->cart_tax)->toBe(17.85)
        ->and((float) $order->discount_total)->toBe(5.0)
        ->and((float) $order->discount_tax)->toBe(0.35)
        ->and((float) $order->refunded_total)->toBe(250.0)
        ->and((float) $order->refunded_tax)->toBe(18.75)
        ->and($order->customer_woo_id)->toBe(99)
        ->and($order->customer_email)->toBe('ben@example.test')
        ->and($order->customer_name)->toBe('Ben Roth')
        ->and($order->billing_country)->toBe('DE')
        ->and($order->payment_method_title)->toBe('Invoice');
});

test('a full refund that records no tax still gives the tax back', function () {
    // A refund made from Stripe's dashboard, or written by a plugin, often
    // records the amount and leaves the tax at zero. Living Nature and
    // Tigerbox both have real orders shaped exactly like this.
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1, [
        'total' => '44.30',
        'total_tax' => '0.50',
        'refunds' => [['id' => 900, 'reason' => 'Refunded in the Stripe dashboard', 'total' => '-44.30', 'total_tax' => '0.00']],
    ])])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    $order = Order::sole();

    // Nothing was kept, so nothing is owed.
    expect((float) $order->refunded_total)->toBe(44.30)
        ->and((float) $order->refunded_tax)->toBe(0.50);
});

test('a partial refund that records no tax is left exactly as reported', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1, [
        'total' => '100.00',
        'total_tax' => '7.70',
        'refunds' => [['id' => 900, 'reason' => '', 'total' => '-30.00', 'total_tax' => '0.00']],
    ])])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    // There is nothing to work the split out from, and inventing one would
    // put a number in the books that nobody can trace back to a refund.
    expect((float) Order::sole()->refunded_tax)->toBe(0.0);
});

test('a refund that reports its own tax is taken at its word', function () {
    Http::fake(['toysonline.test/*' => Http::response([wooOrder(1, [
        'total' => '248.60',
        'total_tax' => '18.63',
        'refunds' => [['id' => 900, 'reason' => '', 'total' => '-248.60', 'total_tax' => '-18.63']],
    ])])]);

    app(ImportShopOrders::class)->handle(syncableShop());

    expect((float) Order::sole()->refunded_tax)->toBe(18.63);
});

test('a run stops when its time budget is spent and asks to be continued', function () {
    config([
        'services.woocommerce.sync_page_size' => 1,
        'services.woocommerce.sync_max_pages_per_run' => 50,
        // Spent before the run even starts.
        'services.woocommerce.sync_max_seconds_per_run' => 0,
    ]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, ['date_created_gmt' => '2026-01-10T09:00:00'])])
            ->push([wooOrder(2, ['date_created_gmt' => '2026-01-11T09:00:00'])]),
    ]);

    $shop = syncableShop();

    $result = app(ImportShopOrders::class)->handle($shop);

    // One page regardless, or a run with no budget left would chain forever
    // without ever importing anything.
    expect($result->hasMore)->toBeTrue()
        ->and($result->pagesFetched)->toBe(1)
        ->and($shop->orders()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('an incremental run stops on its time budget too', function () {
    config([
        'services.woocommerce.sync_page_size' => 1,
        'services.woocommerce.sync_max_pages_per_run' => 50,
        'services.woocommerce.sync_max_seconds_per_run' => 0,
    ]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, ['date_modified_gmt' => '2026-02-01T10:00:00'])])
            ->push([wooOrder(2, ['date_modified_gmt' => '2026-02-01T11:00:00'])]),
    ]);

    $shop = syncableShop();
    $shop->syncStateOrCreate()->update([
        'backfill_completed_at' => now()->subDay(),
        'last_synced_at' => now()->subHour(),
    ]);

    $result = app(ImportShopOrders::class)->handle($shop->fresh());

    expect($result->hasMore)->toBeTrue()
        ->and($result->pagesFetched)->toBe(1);

    Http::assertSentCount(1);
});

test('a run within its budget still walks the whole history', function () {
    config([
        'services.woocommerce.sync_page_size' => 1,
        'services.woocommerce.sync_max_pages_per_run' => 50,
        'services.woocommerce.sync_max_seconds_per_run' => 300,
    ]);

    Http::fake([
        'toysonline.test/*' => Http::sequence()
            ->push([wooOrder(1, ['date_created_gmt' => '2026-01-10T09:00:00'])])
            ->push([wooOrder(2, ['date_created_gmt' => '2026-01-11T09:00:00'])])
            ->push([]),
    ]);

    $result = app(ImportShopOrders::class)->handle(syncableShop());

    expect($result->hasMore)->toBeFalse()
        ->and($result->status)->toBe(ShopSyncStatus::Synced)
        ->and($result->importedCount)->toBe(2);
});
