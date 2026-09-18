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

export type ShopConnectionTone =
    | 'positive'
    | 'warning'
    | 'negative'
    | 'neutral';

export type ShopConnection = {
    status: ShopConnectionStatus;
    statusLabel: string;
    tone: ShopConnectionTone;
    message: string | null;
    checkedAtDiff: string | null;
};

export type Shop = {
    id: number;
    name: string;
    url: string;
    host: string;
    platform: ShopPlatform;
    platformLabel: string;
    consumerKeyHint: string;
    updatedAtDiff: string | null;
    connection: ShopConnection;
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
