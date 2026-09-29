<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Documents\GenerateDocumentTool;
use App\Mcp\Tools\DocumentTemplates\CreateDocumentTemplateTool;
use App\Mcp\Tools\DocumentTemplates\DeleteDocumentTemplateTool;
use App\Mcp\Tools\DocumentTemplates\ListDocumentTemplatesTool;
use App\Mcp\Tools\DocumentTemplates\UpdateDocumentTemplateTool;
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
use App\Mcp\Tools\Reports\GetPayrollSummaryReportTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tools\ToolSearch;

#[Name('Kolvi')]
#[Version('0.0.1')]
#[Instructions('Tools act on behalf of the authenticated user with their exact permissions in the Kolvi web app, never more.')]
class KolviServer extends Server
{
    protected array $tools = [
        ToolSearch::class => [
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
            GetPayrollSummaryReportTool::class,
            CreateDocumentTemplateTool::class,
            UpdateDocumentTemplateTool::class,
            DeleteDocumentTemplateTool::class,
            ListDocumentTemplatesTool::class,
            GenerateDocumentTool::class,
        ],
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
