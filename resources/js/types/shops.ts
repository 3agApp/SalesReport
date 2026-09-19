export type ShopPlatform = 'woocommerce';

export type ShopConnectionStatus =
    | 'unknown'
    | 'connected'
    | 'invalid_credentials'
    | 'insufficient_permissions'
    | 'not_found'
    | 'requires_https'
    | 'unreachable'
    | 'failed';

export type ShopSyncStatus =
    | 'pending'
    | 'backfilling'
    | 'syncing'
    | 'synced'
    | 'failed';

export type StatusTone = 'positive' | 'warning' | 'negative' | 'neutral';

/**
 * The shape shared by everything the shops table reports on: what the last
 * check found, why, and when.
 */
export type StatusIndicator = {
    status: string;
    statusLabel: string;
    tone: StatusTone;
    message: string | null;
    checkedAtDiff: string | null;
};

export type ShopConnection = StatusIndicator & {
    status: ShopConnectionStatus;
};

export type ShopSync = StatusIndicator & {
    status: ShopSyncStatus;
    orderCount: number;
};

export type Shop = {
    id: number;
    name: string;
    url: string;
    host: string;
    platform: ShopPlatform;
    platformLabel: string;
    /** The currency the store sells in, once a connection check has read it. */
    currency: string | null;
    consumerKeyHint: string;
    updatedAtDiff: string | null;
    connection: ShopConnection;
    sync: ShopSync;
};

export type ShopFilters = {
    search: string;
};

export type ShopPlatformOption = {
    value: ShopPlatform;
    label: string;
};

export type ShopStats = {
    total: number;
};
