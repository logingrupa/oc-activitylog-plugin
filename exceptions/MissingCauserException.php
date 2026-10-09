<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Exceptions;

/**
 * Thrown when a logged model is saved or deleted and no signed-in user can be named as the cause.
 */
final class MissingCauserException extends \RuntimeException
{
    /**
     * forAnonymousWrite names the model and the two ways a backend, console or queue writer sets the causer itself.
     */
    public static function forAnonymousWrite(string $sModelClass): self
    {
        return new self(sprintf(
            'No signed-in user exists to attribute the write of %s to. A backend, console or queue writer sets the causer explicitly with '
            .'Spatie\Activitylog\Facades\Activity::defaultCauser($obUser, fn () => ...) or app(CauserResolver::class)->withCauser($obUser, fn () => ...).',
            $sModelClass,
        ));
    }
}
