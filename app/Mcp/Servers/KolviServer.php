<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Leave\ApproveLeaveTool;
use App\Mcp\Tools\Leave\CancelLeaveTool;
use App\Mcp\Tools\Leave\CreateLeaveForEmployeeTool;
use App\Mcp\Tools\Leave\CreateLeaveTool;
use App\Mcp\Tools\Leave\RejectLeaveTool;
use App\Mcp\Tools\Leave\ViewOwnLeavesTool;
use App\Mcp\Tools\Leave\ViewTeamLeavesTool;
use App\Mcp\Tools\Overtime\ApproveOvertimeRequestTool;
use App\Mcp\Tools\Overtime\CreateOvertimeRequestTool;
use App\Mcp\Tools\Overtime\RejectOvertimeRequestTool;
use App\Mcp\Tools\Overtime\ViewOwnOvertimeRequestsTool;
use App\Mcp\Tools\Overtime\ViewTeamOvertimeRequestsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Kolvi')]
#[Version('0.0.1')]
#[Instructions('Tools act on behalf of the authenticated user with their exact permissions in the Kolvi web app, never more.')]
class KolviServer extends Server
{
    protected array $tools = [
        CreateLeaveTool::class,
        ViewOwnLeavesTool::class,
        CancelLeaveTool::class,
        ViewTeamLeavesTool::class,
        ApproveLeaveTool::class,
        RejectLeaveTool::class,
        CreateLeaveForEmployeeTool::class,
        CreateOvertimeRequestTool::class,
        ViewOwnOvertimeRequestsTool::class,
        ViewTeamOvertimeRequestsTool::class,
        ApproveOvertimeRequestTool::class,
        RejectOvertimeRequestTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
