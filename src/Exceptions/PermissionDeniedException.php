<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Timeax\FortiPlugin\Models\User;
use Timeax\FortiPlugin\Notifications\PermissionGrantNotification;

use Timeax\FortiPlugin\Permissions\Evaluation\Dto\Result;

class PermissionDeniedException extends RuntimeException
{
    protected string $type;
    protected string $action;
    protected array|string|null $meta;
    protected Result $result;
    protected ?Request $request;

    public function __construct(
        string $type,
        string $action,
        array|string|null $meta,
        Result $result,
        ?Request $request = null,
        ?Throwable $previous = null
    ) {
        $this->type = $type;
        $this->action = $action;
        $this->result = $result;
        $this->meta = $result->meta ?? $meta;
        $this->request = $request;

        $message = "Permission denied for {$type}:{$action}";
        if ($result->reason) {
            $message .= " (Reason: {$result->reason})";
        }

        parent::__construct($message, 0, $previous);
    }

    public function render($request = null): Response
    {
        /** @var Request|null $request */
        $request = $request ?: $this->request ?: (function_exists('request') ? request() : null);

        // If no request object (e.g. job, command, fallback context)
        if (!$request) {
            // Notify admins with relevant permissions
            $this->notifyPermissionAdmins();

            // Optionally, just throw a generic 403
            abort(403, "Permission denied. Your request has been forwarded to an administrator for review.");
        }

        // 1. API/axios/JSON requests
        if ($request->expectsJson() || $request->isXmlHttpRequest() || $request->wantsJson()) {
            return response()->json([
                'error' => 'plugin_permission_denied',
                'type' => $this->type,
                'action' => $this->action,
                'meta' => $this->meta,
                'reason' => $this->result->reason,
                'matched' => $this->result->matched?->toArray(),
                'context' => $this->result->context,
                'message' => $this->getMessage(),
                'can_request_permission' => true,
                'request_data' => $this->getClonedRequestData(),
            ], 403);
        }

        // 2. All browser/inertia/other requests: redirect back with flash data only
        return redirect()->back()->with('plugin_permission_data', [
            'type' => $this->type,
            'action' => $this->action,
            'meta' => $this->meta,
            'reason' => $this->result->reason,
            'matched' => $this->result->matched?->toArray(),
            'context' => $this->result->context,
            'message' => $this->getMessage(),
            'can_request_permission' => true,
            'request_data' => $this->getClonedRequestData(),
        ]);
    }

    protected function notifyPermissionAdmins(): void
    {
        // Find admins who can grant $this->permissions on $this->target of $this->type
        $admins = User::permission('can_grant_permission', 1)->get();

        foreach ($admins as $admin) {
            $admin->notify(new PermissionGrantNotification([
                'type' => $this->type,
                'action' => $this->action,
                'meta' => $this->meta,
                'reason' => $this->result->reason,
                'matched' => $this->result->matched?->toArray(),
                'context' => $this->result->context,
                'message' => $this->getMessage(),
                'request_data' => $this->getClonedRequestData(),
                // Add more details as needed
            ]));
        }
    }

    public function getClonedRequestData(): array
    {
        if (!$this->request) return [];
        return [
            'method' => $this->request->method(),
            'uri' => $this->request->getRequestUri(),
            'headers' => $this->request->headers->all(),
            'body' => $this->request->all(),
        ];
    }

    public function getType(): string { return $this->type; }
    public function getAction(): string { return $this->action; }
    public function getMeta(): array|string|null { return $this->meta; }
    public function getResult(): Result { return $this->result; }
}