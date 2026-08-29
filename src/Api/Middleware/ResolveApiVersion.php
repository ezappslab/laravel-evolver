<?php

declare(strict_types=1);

namespace Infinity\Evolver\Api\Middleware;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Infinity\Evolver\Api\ApiVersionContext;
use Infinity\Evolver\Api\ApiVersionContextStore;
use Infinity\Evolver\Api\ApiVersionRegistry;
use Infinity\Evolver\Api\ApiVersionState;
use Infinity\Evolver\Contracts\ApiVersionResolver;
use Infinity\Evolver\Exceptions\InvalidApiVersionException;
use Infinity\Evolver\Exceptions\UnsupportedApiVersionException;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveApiVersion
{
    public function __construct(
        private ApiVersionResolver $resolver,
        private ApiVersionRegistry $registry,
        private ApiVersionContextStore $contexts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $version = $this->resolver->resolve($request);
        } catch (InvalidApiVersionException $exception) {
            return $this->error('invalid_api_version', $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        if ($version === null) {
            return $this->error('missing_api_version', 'No API version was provided.', Response::HTTP_BAD_REQUEST);
        }

        $definition = $this->registry->find($version);

        if ($definition === null) {
            $exception = new UnsupportedApiVersionException($version);

            return $this->error(
                'unsupported_api_version',
                $exception->getMessage(),
                Response::HTTP_NOT_FOUND,
                $version->value,
            );
        }

        $state = $definition->lifecycle->stateAt(new DateTimeImmutable);

        if ($state === ApiVersionState::Sunset) {
            return $this->error(
                'sunset_api_version',
                "API version [{$version->value}] is no longer available.",
                Response::HTTP_GONE,
                $version->value,
            );
        }

        $this->contexts->set(new ApiVersionContext($definition, $state));
        $response = $next($request);

        if ($state === ApiVersionState::Deprecated) {
            $response->headers->set('Deprecation', 'true');

            if ($definition->lifecycle->sunsetAt !== null) {
                $response->headers->set(
                    'Sunset',
                    $definition->lifecycle->sunsetAt->setTimezone(new DateTimeZone('GMT'))->format(DATE_RFC7231),
                );
            }

            if ($definition->successorUrl !== null) {
                $response->headers->set('Link', "<{$definition->successorUrl}>; rel=\"successor-version\"");
            }
        }

        return $response;
    }

    private function error(string $code, string $message, int $status, ?string $version = null): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'error' => array_filter([
                'code' => $code,
                'version' => $version,
            ], static fn (mixed $value): bool => $value !== null),
        ], $status);
    }
}
