<?php
declare(strict_types=1);

namespace Timeax\FortiPlugin\Http\Controllers\Ui;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Timeax\FortiPlugin\Ui\Embeds\EmbedResolveException;
use Timeax\FortiPlugin\Ui\Embeds\EmbedResolver;

final class EmbedResolveController
{
    public function __invoke(Request $request, EmbedResolver $resolver): JsonResponse
    {
        //pluginSlug
        $slug = trim((string)$request->query('plugin', ''));
        //embedPageName
        $name = trim((string)$request->query('name', ''));

        if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
            return $this->fail(422, "Invalid plugin slug.");
        }

        if ($name === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            return $this->fail(422, "Invalid embed name.");
        }

        try {
            $spec = $resolver->resolve($slug, $name);

            return response()->json($spec, 200, [
                'Cache-Control' => 'no-store',
            ]);
        } catch (EmbedResolveException $e) {
            return $this->fail($e->status, $e->getMessage());
        } catch (Throwable $e) {
            return $this->fail(500, $e->getMessage());
        }
    }

    private function fail(int $status, string $message): JsonResponse
    {
        return response()->json(
            ['error' => $message],
            $status >= 100 && $status <= 599 ? $status : Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
