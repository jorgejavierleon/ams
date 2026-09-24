<?php

use App\Mcp\Servers\KolviServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/kolvi', KolviServer::class)
    ->middleware('auth:sanctum');
