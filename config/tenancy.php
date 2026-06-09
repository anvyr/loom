<?php

declare(strict_types=1);

/**
 * Tenancy Configuration
 */
return [
    /** Enable multi-tenancy */
    'enabled' => env('TENANCY_ENABLED', false),

    /** Default tenant id (used when resolver yields no match or in CLI) */
    'default' => env('TENANCY_DEFAULT', 'default'),

    /** Resolver: host, path, callback */
    'resolver' => env('TENANCY_RESOLVER', 'host'),

    /** Host resolver configuration */
    'host' => [
        /** Map full hostnames to tenant ids */
        'map' => [
            // 'example.com' => 'default',
            // 'tenant1.example.com' => 'tenant1',
        ],
        /** Strip leading www. from hostname before resolving */
        'strip_www' => true,
        /** Use subdomain as tenant id when root_domains match */
        'wildcard_subdomains' => false,
        /** Root domains used for wildcard subdomain resolution */
        'root_domains' => [
            // 'example.com',
        ],
    ],

    /** Path resolver configuration */
    'path' => [
        /** 1-based segment index for tenant id (/{tenant}/...) */
        'segment' => 1,
        /** Optional mapping of path segment to tenant id */
        'map' => [
            // 'tenant' => 'tenant-id',
        ],
    ],

    /** Callback resolver class (must implement TenantResolverInterface) */
    'callback' => null,

    /** Tenant-specific roots (relative to base_path) */
    'paths' => [
        'user_root' => 'user/tenants',
        'storage_root' => 'storage/tenants',
    ],

    /**
     * Database-per-tenant configuration
     *
     * When enabled, each tenant gets its own database connection.
     * This provides true data isolation between tenants.
     */
    'database' => [
        /** Enable database-per-tenant mode */
        'enabled' => env('TENANCY_DB_ENABLED', false),

        /**
         * Database name pattern ({tenant} replaced with tenant id)
         * Used when no explicit mapping exists for a tenant
         */
        'pattern' => env('TENANCY_DB_PATTERN', 'loom_{tenant}'),

        /**
         * Explicit tenant-to-database mapping
         * Can be a connection name (string) or full connection config (array)
         *
         * Examples:
         *   'tenant-a' => 'mysql_tenant_a',           // Use named connection
         *   'tenant-b' => ['database' => 'custom'],   // Override specific values
         */
        'map' => [
            // 'tenant-id' => 'connection-name',
            // 'tenant-id' => ['database' => 'custom_db'],
        ],
    ],
];
