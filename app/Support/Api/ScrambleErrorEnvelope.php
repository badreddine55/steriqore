<?php

namespace App\Support\Api;

use Dedoc\Scramble\Exceptions\OpenApiReferenceTargetNotFoundException;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response as OAResponse;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Scramble infers per-exception response shapes (e.g. Laravel's default
 * {"message","errors"} for a ValidationException) by statically analysing
 * what each controller can throw. That's wrong here: every /api/* error is
 * rewritten by ApiExceptionRenderer into one envelope
 * {"error":{"code","message","details","request_id"}} — see CLAUDE.md's API
 * conventions. Without this transformer, the committed openapi.yaml would
 * silently drift from what the API actually returns. Registered via
 * Scramble::afterOpenApiGenerated() in AppServiceProvider.
 *
 * Note: an operation's response map is keyed by HTTP status at
 * Operation::toArray() time by resolving each entry's `Response::$code`
 * (reading straight through a Reference to its target). So this transformer
 * can't point every error response at one shared named response component —
 * that would collapse every status onto whatever single code the shared
 * component happened to carry. Instead each entry becomes its own inline
 * Response with the correct code, all pointing at the one shared
 * ApiErrorEnvelope *schema* (schemas don't have this keying problem).
 */
class ScrambleErrorEnvelope
{
    public static function apply(OpenApi $openApi): void
    {
        $components = $openApi->components;

        $errorObject = (new ObjectType)
            ->addProperty('code', new StringType)
            ->addProperty('message', new StringType)
            ->addProperty('details', new ObjectType)
            ->addProperty('request_id', new StringType)
            ->setRequired(['code', 'message', 'details', 'request_id']);

        $envelope = (new ObjectType)
            ->addProperty('error', $errorObject)
            ->setRequired(['error']);

        $components->addSchema('ApiErrorEnvelope', Schema::fromType($envelope));

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                if ($operation->responses === null) {
                    continue;
                }

                foreach ($operation->responses as $index => $entry) {
                    $code = self::resolveCode($entry);

                    if (is_int($code) && $code >= 400) {
                        $operation->responses[$index] = OAResponse::make($code)
                            ->setDescription('SteriQore API error envelope.')
                            ->setContent('application/json', $components->getSchemaReference('ApiErrorEnvelope'));
                    }
                }
            }
        }

        self::removeOrphanedResponseComponents($openApi);
    }

    /**
     * Built-in named responses (ValidationException, AuthenticationException,
     * ...) no longer have any references pointing at them once the loop
     * above ran — every operation that used to $ref one now carries its own
     * inline Response instead. Sweep anything components.responses no
     * longer points at, rather than guessing their exact (fully-qualified,
     * backslash-prefixed) component names.
     */
    private static function removeOrphanedResponseComponents(OpenApi $openApi): void
    {
        $components = $openApi->components;
        $usedNames = [];

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach (($operation->responses ?? []) as $entry) {
                    if ($entry instanceof Reference && $entry->referenceType === 'responses') {
                        $usedNames[$entry->fullName] = true;
                    }
                }
            }
        }

        foreach (array_keys($components->responses) as $name) {
            if (! isset($usedNames[$name])) {
                $components->removeResponse($name);
            }
        }
    }

    private static function resolveCode(OAResponse|Reference $entry): int|string|null
    {
        if ($entry instanceof OAResponse) {
            return $entry->code;
        }

        try {
            $resolved = $entry->resolve();
        } catch (OpenApiReferenceTargetNotFoundException) {
            return null;
        }

        return $resolved instanceof OAResponse ? $resolved->code : null;
    }
}
