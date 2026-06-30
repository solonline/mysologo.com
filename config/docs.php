<?php

return [

    /*
  |--------------------------------------------------------------------------
  | Documentação da API (OpenAPI + Scalar)
  |--------------------------------------------------------------------------
  |
  | internal_public: em local, a doc interna fica acessível sem login.
  | Em produção, use false e proteja com auth staff (middleware docs.internal).
  |
  */

    'enabled' => env('DOCS_ENABLED', true),

    'internal_public' => env('DOCS_INTERNAL_PUBLIC', env('APP_ENV') === 'local'),

    'scalar_cdn' => env('DOCS_SCALAR_CDN', 'https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.60.0'),

    'openapi_path' => base_path('docs/openapi'),

    'guides_path' => base_path('docs/guides'),

];
