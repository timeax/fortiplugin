<?php

namespace Timeax\FortiPlugin\Exceptions;

use RuntimeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Timeax\FortiPlugin\Models\User;
use Timeax\FortiPlugin\Notifications\PermissionGrantNotification;

class PermissionDeniedException extends RuntimeException
{
    protected string $type;
    protected string $target;
    protected array|string|null $permissions;
    protected ?Request $request;

    public function __construct(
        string $type,
        string $target,
        array|string|null $permissions = null,
        ?Request $request = null,
        string $message = "",
        int $code = 0,
        Throwable $previous = null
    ) {
        $this->type = $type;
        $this->target = $target;
        $this->permissions = $permissions;
        $this->request = $request;
        $message = $message ?: "Permission denied for {$type}:{$target}" . ($permissions ? " (" . implode(',', (array)$permissions) . ")" : '');
        parent::__construct($message, $code, $previous);
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
                'target' => $this->target,
                'permissions' => $this->permissions,
                'message' => $this->getMessage(),
                'can_request_permission' => true,
                'request_data' => $this->getClonedRequestData(),
            ], 403);
        }

        // 2. All browser/inertia/other requests: redirect back with flash data only
        return redirect()->back()->with('plugin_permission_data', [
            'type' => $this->type,
            'target' => $this->target,
            'permissions' => $this->permissions,
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
                'target' => $this->target,
                'permissions' => $this->permissions,
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
    public function getTarget(): string { return $this->target; }
    public function getPermissions(): array|string|null { return $this->permissions; }
}